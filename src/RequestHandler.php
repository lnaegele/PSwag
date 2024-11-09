<?PHP
declare(strict_types=1);
namespace PSwag;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PSwag\EndpointDefinition;
use PSwag\Model\CustomResult;
use PSwag\Model\Property;
use PSwag\Model\TypeSchema;
use PSwag\Reflection\ReflectionHelper;
use Slim\Exception\HttpBadRequestException;

class RequestHandler
{
    function __construct(
        private EndpointDefinition $endpoint,
        private ReflectionHelper $reflectionHelper
    ) {}

    public function execute(ServerRequestInterface $request, ResponseInterface $response, array $pathVariables) : ResponseInterface
    {
        $method = $request->getMethod();
        if (!in_array($method, $this->endpoint->getMethods()) || !in_array($method, ['GET', 'DELETE', 'POST', 'PUT', 'PATCH'])) {
            throw new HttpBadRequestException($request, "Unsupported method '" . $method . "'.");
        }

        if (in_array($method, ['GET', 'DELETE'])) {
            $parameters = $this->getParameterValuesFromQuery();
            return $this->routeToAppService($request, $response, $parameters, $pathVariables, true);
        }
        else if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $parameters = $this->getParameterValuesFromBody();
            return $this->routeToAppService($request, $response, $parameters, $pathVariables, false);
        }
    }
    
    private function routeToAppService(ServerRequestInterface $request, ResponseInterface $response, array $parameterValues, array $pathVariables, bool $isQueryRequest) : ResponseInterface
    {
        // union of parameter values and path variables
        $pathVariableKeys = [];
        foreach ($pathVariables as $key => $value) {
            if (array_key_exists($key, $parameterValues)) throw new \Exception('Value for \'' . $key . '\' is both provided as path variable and as query/body variable.');
            $parameterValues[$key] = $value;
            $pathVariableKeys[] = $key;
        }

        $className = $this->endpoint->getApplicationServiceClass();
        $methodName = $this->endpoint->getApplicationServiceMethod();
        $properties = $this->reflectionHelper->getPropertiesFromMethodParameters($className, $methodName, $isQueryRequest);
        
        // get method argument names
        $args = [];
        if (count($properties)==1 && $properties[0]->getTypeSchema()->isCustomDto())
        {
            // only one parameter and not a simple type => DTO
            $args = [$this->createDtoFromValues($request, $properties[0]->getTypeSchema(), $parameterValues, $pathVariableKeys)];
        }
        else
        {
            $args = $this->getCheckedArguments($request, $properties, $parameterValues, $pathVariableKeys);
        }

        ob_start();
        try {
            $result = $this->reflectionHelper->executeMethod($className, $methodName, $args);
            $returnType = $this->reflectionHelper->getTypeSchemaFromMethodReturnType($className, $methodName);
            if ($returnType->getType()==CustomResult::class) {
                /** @var CustomResult $result */
                $response->getBody()->write(''.($result->body));
                $response = $response->withAddedHeader('Content-Type', $result->mimeType);
                if ($result->fileName!=null) $response = $response->withAddedHeader('Content-disposition', 'inline;filename="'.str_replace('"', '_', $result->fileName).'"');
                if ($result->cacheMaxAge!=null) $response = $response->withAddedHeader('Cache-control', 'max-age='.$result->cacheMaxAge)->withAddedHeader('Expires', gmdate(DATE_RFC1123,time()+$result->cacheMaxAge));
                if ($result->lastModifiedTime!=null) $response = $response->withAddedHeader('Last-Modified', gmdate(DATE_RFC1123,$result->lastModifiedTime));
            } else if (!$returnType->isVoid()) {
                $resultJson = json_encode($result, JSON_UNESCAPED_SLASHES);
                $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
                $response->getBody()->write(''.$resultJson);
            }
            return $response;
        } finally {
            $output = ob_get_clean();
            if ($output != null && $output != '') {
                throw new \Exception("Route execution created unexpected output: \"" . $output . "\"");
            }
        }
    }

    private function getParameterValuesFromBody()
    {
        $json = json_decode(file_get_contents('php://input'), null, 512, JSON_OBJECT_AS_ARRAY);
        $propertyValues = [];
        foreach ($json ?? [] as $key => $value) $propertyValues[$key] = $value;
        return $propertyValues;
    }
    
    private function getParameterValuesFromQuery()
    {
        $propertyValues = [];
        
        // Read GET parameters from server params, because repeating names are overwritten in $_GET, eg. 'ids=2&ids=3' will become: $_GET['ids']=3
        $arrayConversions = [];
        foreach (explode('&', $_SERVER['QUERY_STRING']) as $var) {
            $paths = explode("=", $var);
            $varName = urldecode(array_shift($paths));
            $varValue = urldecode(implode("=", $paths));

            if ($varName == '') continue;
            
            // do not overwrite if key is present multiple times (array)
            if (array_key_exists($varName, $propertyValues)) {
                if (in_array($varName, $arrayConversions)) {
                    $propertyValues[$varName][] = $varValue;
                } else {
                    $tmp = $propertyValues[$varName];
                    $propertyValues[$varName] = array($tmp, $varValue);
                    $arrayConversions[] = $varName;
                }
            } else {
                $propertyValues[$varName] = $varValue;
            }
        }

        return $propertyValues;
    }
    
    /**
     * Create a DTO object with defined values
     * @param string[] $pathVariableKeys the names of all variables that are provided inside path
     * @return object the instantiated DTO
     */
    private function createDtoFromValues(ServerRequestInterface $request, TypeSchema $typeSchema, array $propertyValues, array $pathVariableKeys): object
    {
        if ($propertyValues==null) {
            if (!$typeSchema->isRequired()) return null;
            $propertyValues = [];
        }
        
        // read properties of Dto
        $fullyQualifiedClassName = $typeSchema->getType();
        $properties = $this->reflectionHelper->getPropertiesFromClassProperties($fullyQualifiedClassName, false);
        $propertyValues = $this->getCheckedArguments($request, $properties, $propertyValues, $pathVariableKeys);
        
        $input = $this->reflectionHelper->instantiateClass($fullyQualifiedClassName, false);
        foreach ($propertyValues as $key => $value) {    
            $input->{$key} = $value;
        }
    
        return $input;
    }
    
    /**
     * Check all required properties are provided and no unexpected property is provided. Also consider path variables of route.
     * @param Property[] $expectedProperties
     * @param string[] $pathVariableKeys the names of all variables that are provided inside path
     * @return mixed[] map from propery name => value
     */
    private function getCheckedArguments(ServerRequestInterface $request, array $expectedProperties, array $providedPropertyValues, array $pathVariableKeys)
    {
        $expectedPropertyNames = [];
        foreach ($expectedProperties as $param) $expectedPropertyNames[] = $param->getName();

        // check no unused properties
        foreach ($providedPropertyValues as $key => $value) {
            if (!in_array($key, $expectedPropertyNames)) {
                throw new HttpBadRequestException($request, "Property '" .$key . "' is provided but not expected. Expected properties: " . implode(", ", $expectedPropertyNames));
            }
        }
        
        // check all required properties are provided
        $result = [];
        foreach ($expectedProperties as $expectedProperty) {
            $key = $expectedProperty->getName();
            $expectedType = $expectedProperty->getTypeSchema();

            // Allow empty arrays even if required and nothing is passed (swagger is passing null instead of empty arrays)
            if ($expectedType->isRequired() && !array_key_exists($key, $providedPropertyValues) && $expectedType->isArray()) {
                $providedPropertyValues[$key] = [];
            }

            // Check if required properties are provided
            if ($expectedType->isRequired() && !array_key_exists($key, $providedPropertyValues)) {
                throw new HttpBadRequestException($request, "Property '" .$key . "' is required but not provided.");
            }
    
            $providedPropertyValue = null;
            if (array_key_exists($key, $providedPropertyValues)) {

                // this variable value is provided as path variable it needs to be converted from string
                if (in_array($key, $pathVariableKeys)) {
                    $rawProvidedPropertyValue = $expectedType->parse($providedPropertyValues[$key]);
                } else {
                    $rawProvidedPropertyValue = $providedPropertyValues[$key];
                }

                $providedPropertyValue = $this->mapValueToTypeSchemaValue($request, $rawProvidedPropertyValue, $expectedType);
            }

            $result[$key] = $providedPropertyValue;
        }
    
        return $result;
    }

    private function mapValueToTypeSchemaValue(ServerRequestInterface $request, mixed $value, TypeSchema $expectedType): mixed
    {
        // expected parameter is array but provided is flat value?
        if ($expectedType->isArray()) {
            if (!is_array($value)) $value = array($value);

            // create sub dtos
            $_array = $value;
            $_result = [];
            foreach ($_array AS $value) {
                $_result[] = $this->mapValueToTypeSchemaValue($request, $value, $expectedType->getArraySubTypeSchema());
            }
            $value = $_result;
        }

        // if not simple data type but sub DTO => convert
        else if ($expectedType->isCustomDto()) {
            $value = $this->createDtoFromValues($request, $expectedType, $value, []);
        }

        // convert enums from string, booleans from string, path vars (e.g. numbers) from string
        else {
            $value = $expectedType->parse($value);
        }

        return $value;
    }
}
?>
