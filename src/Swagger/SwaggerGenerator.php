<?PHP
declare(strict_types=1);
namespace PSwag\Swagger;

use Exception;
use PSwag\EndpointDefinition;
use PSwag\Interfaces\ApiKeyAuthMiddlewareInterface;
use PSwag\Interfaces\ApiKeyInType;
use PSwag\Interfaces\AuthMiddlewareInterface;
use PSwag\Interfaces\BasicAuthMiddlewareInterface;
use PSwag\Interfaces\BasicAuthSchemeType;
use PSwag\Model\CustomResult;
use PSwag\Model\Property;
use PSwag\Model\TypeSchema;
use PSwag\PSwagRegistry;
use PSwag\Reflection\ReflectionHelper;

class SwaggerGenerator
{
    function __construct(
        private PSwagRegistry $registry,
        private ReflectionHelper $reflectionHelper
    ) {}

    public function generate($applicationName, $version, $pathPrefix): array
    {        
        $schemaDefinitions = [
            "schemas" => [
                "types" => [],
                "definitions" => []
            ],
            "securitySchemes" => [
                "cache" => [],
                "definitions" => []
            ]
        ];

        return array(
            "openapi" => "3.0.2",
            "info" => [
                "title" => $applicationName,
                "version" => $version
            ],
            "servers" => [
                [
                    "url" => $pathPrefix
                ]
            ],
            "paths" => $this->generatePaths($schemaDefinitions),
            "components" => [
                "schemas" => $schemaDefinitions["schemas"]["definitions"],
                "securitySchemes" => $schemaDefinitions["securitySchemes"]["definitions"]
            ]
        );
    }

    private function generatePaths(array &$schemaDefinitions) : array
    {
        $result = [];
        foreach ($this->registry->getAll() as $endpoint) {
            /** @var EndpointDefinition $endpoint */
            foreach ($endpoint->getMethods() as $method) {
                $path = $this->generatePath($method, $endpoint, $schemaDefinitions);
                
                $pattern = $endpoint->getPattern();
                if (!array_key_exists($pattern, $result)) $result[$pattern] = [];
                $result[$pattern][strtolower($method)] = $path;
            }
        }

        return $result;
    }

    private function generatePath(string $method, EndpointDefinition $endpoint, array &$schemaDefinitions) : array
    {
        $isQueryRequest = in_array($method, ['GET', 'DELETE']);

        $pathParameters = [];
        $methodParameters = [];
        $requestBody = null;
        
        // Path variables (e.g. "GET /cars/{id}")
        $pathVariables = $endpoint->getPathVariableNames();
        $pathVariableProperties = [];
        
        // Method parameters
        $properties = $this->reflectionHelper->getPropertiesFromMethodParameters($endpoint->getApplicationServiceClass(), $endpoint->getApplicationServiceMethod(), $isQueryRequest);
        if ($isQueryRequest) {
            // If there is only one single property and it is of custom dto type, overwrite $properties with its properties
            if (count($properties) == 1 && $properties[0]->getTypeSchema()->isCustomDto()) {
                $typeSchema = $properties[0]->getTypeSchema();
                $properties = $this->reflectionHelper->getPropertiesFromClassProperties($typeSchema->getType(), false);
            }
            
            foreach ($properties as $p) {
                // ignore this property as it will later be considered in path variables
                $typeSchema = $p->getTypeSchema();
                if (!$this->setupSchemaOfMatchingPathVariable($p, $pathVariables, $pathVariableProperties)) {
                    $methodParameter = [
                        "name" => $p->getName(),
                        "in" => 'query',
                        "required" => $typeSchema->isRequired(),
                        "schema" => $this->getSchema($typeSchema, $schemaDefinitions)
                    ];
                    if ($p->getDescription()!=null) $methodParameter["description"] = $p->getDescription();
                    $methodParameters[] = $methodParameter;
                }
            }
        }
        else {
            $schema = null;
            // If there is only one single property and it is of custom dto type, take its schema directly
            if (count($properties) == 1 && $properties[0]->getTypeSchema()->isCustomDto()) {
                $typeSchema = $properties[0]->getTypeSchema();

                // try to identify whether path variables exist that match first-level properties of dto
                $dtoProperties = $this->reflectionHelper->getPropertiesFromClassProperties($typeSchema->getType(), false);
                $filteredProperties = $this->getPropertiesWithRemovedPathVariables($dtoProperties, $pathVariables, $pathVariableProperties);
                if (count($dtoProperties) === count($filteredProperties)) {
                    $schema = $this->getSchema($typeSchema, $schemaDefinitions);
                }
                else {
                    // When path variable is found in first level dto, schema is different to dto type and can only be returned as anonymous schema description
                    $schema = $this->createObjectSchemaFromDtoProperties($filteredProperties, $schemaDefinitions);
                }
            } else if (count($properties)>0) {
                $filteredProperties = $this->getPropertiesWithRemovedPathVariables($properties, $pathVariables, $pathVariableProperties);
                $schema = $this->createObjectSchemaFromDtoProperties($filteredProperties, $schemaDefinitions);
            }

            $requestBody = $schema == null ? [] : [
                "required" => true,
                "content" => [
                    "application/json" => [
                        "schema" => $schema
                    ]
                ]
            ];
        }

        // path variables
        foreach ($pathVariables as $pvar) {
            $schema = [
                'type' => 'string' // not defined, can be anything
            ];

            $description = null;
            if (array_key_exists($pvar, $pathVariableProperties)) {
                $prop = $pathVariableProperties[$pvar];
                $schema = $this->getSchema($prop->getTypeSchema(), $schemaDefinitions);
                $description = $prop->getDescription();
            }

            $pathParameter = [
                "name" => $pvar,
                "in" => 'path',
                "required" => true,
                "schema" => $schema
            ];
            if ($description != null) {
                $pathParameter["description"] = $description;
            }

            $pathParameters[] = $pathParameter;
        }
        
        // determine tags: always first path, after a "/" prefix. E.g. /ThisIsTag/GetById
        $routeParts = explode("/", $endpoint->getPattern(), 3);
        $tags = count($routeParts) >= 2  ? [$routeParts[1]] : [];
        $result = [
            "tags" => $tags,
            "operationId" => $endpoint->getApplicationServiceMethod(),
            "responses" => [
                "200" => [
                    "description" => "Successful operation"
                ]
            ]
        ];
        $parameters = array_merge($pathParameters, $methodParameters);
        if (count($parameters) > 0) $result["parameters"] = $parameters;
        if ($requestBody) $result["requestBody"] = $requestBody;

        // summary
        $summary = $this->reflectionHelper->getMethodSummaryFromDoc($endpoint->getApplicationServiceClass(), $endpoint->getApplicationServiceMethod());
        if ($summary != null) {
            $result["summary"] = $summary;
        }

        // security
        $authMiddlewares = $endpoint->getAuthMiddlewares();
        if (count($authMiddlewares) > 0) {
            $securitySchemes = [];
            foreach($authMiddlewares as $authMiddleware) {
                $securitySchemaName = $this->createSecuritySchemaIfNeededAndReturnName($authMiddleware, $schemaDefinitions);
                $securitySchemes[$securitySchemaName] = [];  // possibility to create logial AND; inside array, scopes can be defined
            }
            $result["security"][] = $securitySchemes; // possibility to create logial AND
            
            // TODO: add more information about specific auth method
            $result["responses"]["401"] = [
                "description" => "Authentication is missing or invalid"
            ];
        }

        // Return type
        $returnTypeSchema = $this->reflectionHelper->getTypeSchemaFromMethodReturnType($endpoint->getApplicationServiceClass(), $endpoint->getApplicationServiceMethod());
        if ($returnTypeSchema->getType()==CustomResult::class) {
            $result["responses"]["200"]["content"] = [
                "application/octet-stream" => [
                    "schema" => [
                        "type" => "string",
                        "format" => "binary"
                    ]
                ]
            ];
        } else if (!$returnTypeSchema->isVoid()) {
            $returnSchema = $this->getSchema($returnTypeSchema, $schemaDefinitions);
            if ($returnSchema) $result["responses"]["200"]["content"] = [
                "appliation/json" => [
                    "schema" => $returnSchema
                ]
            ];
        }

        if (!$returnTypeSchema->isVoid()) {
            // Return description from doc
            $returnDescription = $this->reflectionHelper->getReturnDescriptionFromDoc($endpoint->getApplicationServiceClass(), $endpoint->getApplicationServiceMethod());
            if ($returnDescription != '') {
                $result["description"] = $returnDescription;
            }
        }

        return $result;
    }

    /**
     * @param Property $property,
     * @param string[] $pathVariables
     * @param TypeSchema[] $pathVariableProperties
     * @return bool
     */
    private function setupSchemaOfMatchingPathVariable(Property $property, array $pathVariables, array &$pathVariableProperties): bool
    {
        $matchingPathVars = array_filter($pathVariables, function($pvar) use ($property) { return $pvar === $property->getName(); });
        $matchingPathVar = count($matchingPathVars) > 0 ? reset($matchingPathVars) : null;
        if (!$matchingPathVar) return false;

        $pathVariableProperties[$matchingPathVar] = $property;
        return true;
    }

    /**
     * @param Property[] $properties
     * @param string[] $pathVariables
     * @param TypeSchema[] $pathVariableProperties
     * @return Property[]
     */
    private function getPropertiesWithRemovedPathVariables(array $properties, array $pathVariables, array &$pathVariableSchemas): array
    {
        $newProperties = $properties;
        foreach ($properties as $p) {
            // remove from dto properties when it is a path variable
            if ($this->setupSchemaOfMatchingPathVariable($p, $pathVariables, $pathVariableSchemas)) {
                unset($newProperties[array_search($p, $newProperties)]);
            }
        }
        return $newProperties;
    }

    private function getSchema(TypeSchema $typeSchema, array &$schemaDefinitions, $preventSchemaRef = false): ?array
    {
        if (($typeSchema->isEnum() || $typeSchema->isCustomDto()) && !$preventSchemaRef) {
            return [
                "\$ref" => $this->createObjectSchemaFromTypeSchemaIfNeededAndReturnRef($typeSchema, $schemaDefinitions)
            ];
        } else if ($typeSchema->isEnum() && $preventSchemaRef) {
            return $this->createObjectSchemaFromEnum($typeSchema->getType());
        } else if ($typeSchema->isCustomDto() && $preventSchemaRef) {
            $properties = $this->reflectionHelper->getPropertiesFromClassProperties($typeSchema->getType(), false);
            return $this->createObjectSchemaFromDtoProperties($properties, $schemaDefinitions);
        } else if ($typeSchema->isVoid()){
            return null;
        } else {
            $schema = [
                "type" => $typeSchema->getType()
            ];
            if ($typeSchema->getFormat()) $schema["format"] = $typeSchema->getFormat();
            if ($typeSchema->getArraySubTypeSchema() != null) {
                $schema["items"] = $this->getSchema($typeSchema->getArraySubTypeSchema(), $schemaDefinitions);
            }
            return $schema;
        }
    }

    /**
     * @param string $type Enum type
     * @return ?array
     */
    private function createObjectSchemaFromEnum(string $type): ?array {
        return [
            "type" => 'string',
            "enum" => array_map(function($enum) { return $enum->name; }, ($type::cases())),
        ];
    }

    /**
     * @param Property[] $properties
     * @param array $schemaDefinitions
     * @return ?array
     */
    private function createObjectSchemaFromDtoProperties(array $properties, array &$schemaDefinitions): ?array
    {
        $schema = [
            "type" => 'object',
        ];
        
        if (count($properties) > 0) {
            $propertyResult = [];
            foreach ($properties as $property) {
                $propertySchema = $this->getSchema($property->getTypeSchema(), $schemaDefinitions);
                if ($property->getDescription()!=null) $propertySchema["description"] = $property->getDescription();
                $propertyResult[$property->getName()] = $propertySchema;
            }
            $schema["properties"] = $propertyResult;

            $requiredProperties = array_filter($properties, function($prop) { return $prop->getTypeSchema()->isRequired(); });
            if (count($requiredProperties) > 0) $schema["required"] = array_values(array_map(function($prop) { return $prop->getName(); }, $requiredProperties));
        }

        return $schema;
    }

    private function createObjectSchemaFromTypeSchemaIfNeededAndReturnRef(TypeSchema $typeSchema, array &$schemaDefinitions) : string
    {
        $parts = explode('\\', $typeSchema->getType());
        $simpleName = end($parts);
        $hash = json_encode($typeSchema);

        if (!array_key_exists($simpleName, $schemaDefinitions["schemas"]["types"])) {
            // First setup $schemaDefinitions in order to support Dto recursivness
            $schemaDefinitions["schemas"]["types"][$simpleName] = $hash;

            if ($typeSchema->isEnum()) {
                $schemaDefinition = $this->createObjectSchemaFromEnum($typeSchema->getType());
            } else if ($typeSchema->isCustomDto()) {
                $properties = $this->reflectionHelper->getPropertiesFromClassProperties($typeSchema->getType(), false);
                $schemaDefinition = $this->createObjectSchemaFromDtoProperties($properties, $schemaDefinitions);
            } else {
                throw new \Exception("Can not create object definition for type '" . $typeSchema->getType() . "'.");
            }

            $schemaDefinitions["schemas"]["definitions"][$simpleName] = $schemaDefinition;
        }
        else if ($schemaDefinitions["schemas"]["types"][$simpleName] !== $hash) {
            throw new \Exception("Same DTO name '" . $simpleName . "' is used more than once.");
        }

        return "#/components/schemas/" . $simpleName;
    }

    private function createSecuritySchemaIfNeededAndReturnName(AuthMiddlewareInterface $authMiddleware, array &$schemaDefinitions) : string
    {
        $stringifiedObject = spl_object_id($authMiddleware);
        if (array_key_exists($stringifiedObject, $schemaDefinitions["securitySchemes"]["cache"])) {
            return $schemaDefinitions["securitySchemes"]["cache"][$stringifiedObject];
        }
        
        $definition = [];
        $nameSuggestion = "";

        // BasicAuth
        if ($authMiddleware instanceof BasicAuthMiddlewareInterface) {
            $nameSuggestion = "BasicAuth";
            $definition["type"] = "http";
            $scheme = $authMiddleware->getScheme();
            $definition["scheme"] = match ($authMiddleware->getScheme()) {
                BasicAuthSchemeType::Basic => "basic",
                BasicAuthSchemeType::Bearer => "bearer",
                default => throw new Exception("Value '".$authMiddleware->getScheme()."' is not allowed for parameter 'scheme' for basic authentication."),
            };
            if ($scheme==BasicAuthSchemeType::Bearer) {
                $bearerFormat = $authMiddleware->getBearerFormat();
                if ($bearerFormat != null) $definition["bearerFormat"] = $bearerFormat;
            }
        }        

        // ApiKey
        else if ($authMiddleware instanceof ApiKeyAuthMiddlewareInterface) {
            $nameSuggestion = "ApiKey";
            $definition["type"] = "apiKey";
            $definition["name"] = $authMiddleware->getName();
            $definition["in"] = match ($authMiddleware->getIn()) {
                ApiKeyInType::Header => "header",
                ApiKeyInType::Cookie => "cookie",
                ApiKeyInType::Query => throw new Exception("Value '".$authMiddleware->getIn()."' for parameter 'in' is not yet supported for ApiKey authentication."), // "query", // not yet supported, query param needs to be ignored by mapper or it would throw an error
                default => throw new Exception("Value '".$authMiddleware->getIn()."' is not allowed for parameter 'in' for ApiKey authentication."),
            };
        }

        else {
            throw new Exception("AuthMiddleware '".get_class($authMiddleware)."' must implement one of following types: BasicAuthMiddlewareInterface, ApiKeyAuthMiddlewareInterface.");
        }

        // find unique name
        $name = $nameSuggestion;
        $i = 2;
        while (true) {
            if (!array_key_exists($name, $schemaDefinitions["securitySchemes"]["definitions"])) break;
            $name = $nameSuggestion . $i++;
        }

        $schemaDefinitions["securitySchemes"]["definitions"][$name] = $definition;
        $schemaDefinitions["securitySchemes"]["cache"][$stringifiedObject] = $name;
        return $name;
    }
}
?>
