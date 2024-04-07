<?PHP
declare(strict_types=1);
namespace PSwag\Reflection;

use Doctrine\Common\Annotations\TokenParser;
use Psr\Container\ContainerInterface;
use PSwag\Model\Property;
use PSwag\Model\TypeSchema;

class ReflectionHelper
{
    function __construct(private ?ContainerInterface $container) {}

    /**
     * When method has only one parameter and this is a custom dto type, properties of this type are returned.
     * When method has multiple parameters, these are returned as properties.
     * @param string $className
     * @param string $methodName
     * @param bool $ensureOnlySimpleTypes
     * @return Property[]
     */
    public function getPropertiesFromMethodParameters(string $className, string $methodName, bool $ensureOnlySimpleTypes): array {
        $definingClass = new \ReflectionClass($className);
        $reflectionMethod = new \ReflectionMethod($className, $methodName);
        $reflectionParams = $reflectionMethod->getParameters();

        // special case when only simple types are allowed but only one parameter exists and this is a complex DTO => take params from DTO
        if ($ensureOnlySimpleTypes && count($reflectionParams)==1) {
            $param = $reflectionParams[0];
            $paramType = $param->getType();
            if ($paramType instanceof \ReflectionNamedType && !$paramType->isBuiltin())
            {
                // TODO: allow dto instead just flat simple types, but only in depth 1 and not infinite!
                $ensureOnlySimpleTypes = false;
            }
        }

        // only builtin types => flat param list
        return array_map(function($param) use ($className, $reflectionMethod, $ensureOnlySimpleTypes, $definingClass) {
            $typeHint = $this->getParamTypeHintFromDoc($param->getName(), $reflectionMethod);
            $providedIn = 'Parameter \'' . $param->getName() . '\' of ' . $className . ':' . $reflectionMethod->getShortName();
            $description = $this->getParamDescriptionFromDoc($param->getName(), $reflectionMethod);
            $typeSchema = $this->getTypeSchemaFromType($param->getType(), $providedIn, $typeHint, $ensureOnlySimpleTypes, $definingClass);
            return new Property($param->getName(), $description, $typeSchema);
        }, $reflectionParams);
    }

    /**
     * Analyze schema of return type of specified method.
     * @param string $className
     * @param string $methodName
     * @return TypeSchema
     */
    public function getTypeSchemaFromMethodReturnType(string $className, string $methodName): TypeSchema {
        $definingClass = new \ReflectionClass($className);
        $reflectionMethod = new \ReflectionMethod($className, $methodName);
        $reflectionReturnType = $reflectionMethod->getReturnType();

        $typeHint = $this->getReturnTypeHintFromDoc($reflectionMethod);
        $providedIn = 'Return type of ' . $className . ':' . $reflectionMethod->getShortName();
        return $this->getTypeSchemaFromType($reflectionReturnType, $providedIn, $typeHint, false, $definingClass);
    }

    /**
     * @param string $className
     * @param bool $ensureOnlySimpleTypes
     * @return Property[]
     */
    public function getPropertiesFromClassProperties(string $className, bool $ensureOnlySimpleTypes): array {
        $class = new \ReflectionClass($className);
        return array_map(function($prop) use ($class, $ensureOnlySimpleTypes) {
            $providedIn = $class . '->' . $prop->getName();
            $this->checkPublicOrProtectedModifier($prop, $providedIn);
            $typeHint = $this->getPropertyTypeHintFromDoc($prop->getName(), $prop);
            $description = $this->getPropertyDescriptionFromDoc($prop->getName(), $prop);
            $typeSchema = $this->getTypeSchemaFromType($prop->getType(), $providedIn, $typeHint, $ensureOnlySimpleTypes, $class);
            return new Property($prop->getName(), $description, $typeSchema);
        }, $class->getProperties());
    }

    public function executeMethod(string $className, string $methodName, array $args)
    {
        $serviceInstance = $this->instantiateClass($className, true);
        $reflectionMethod = new \ReflectionMethod($className, $methodName);
        return $reflectionMethod->invoke($serviceInstance, ...$args);
    }
    
    public function instantiateClass(string $className, bool $useDependencyInjection): object {
        if ($useDependencyInjection && $this->container != null) {
            return $this->container->get($className);
        }

        $clazz = new \ReflectionClass($className);

        // Check constructor has no properties
        $cParams = $clazz->getConstructor()?->getParameters();
        if ($cParams != null && count($cParams)>0) {
            throw new \Exception('Class ' . $className . ' can not be instantiated as it does not provide a parameterless constructor.');
        }

        return $clazz->newInstanceArgs();
    }

    private function getTypeSchemaFromType(?\ReflectionType $type, string $providedIn, ?string $typeHint, bool $ensureOnlySimpleTypes, \ReflectionClass $definingClass): TypeSchema {
        if ($type && !($type instanceof \ReflectionNamedType))  throw new \Exception($providedIn . ' is either intersection or union which is not supported.');

        // first try with direct type
        $exception = null;
        if ($type != null) {
            try {
                return $this->getTypeSchemaFromTypeOrTypeHint($type->getName(), $type->allowsNull(), $providedIn, $ensureOnlySimpleTypes, $definingClass);
            } catch (\Exception $e1) {
                $exception = $e1;
            }
        }

        // try with type hint. Return most meaningful exception.
        try {
            return $this->getTypeSchemaFromTypeOrTypeHint($typeHint, false, $providedIn, $ensureOnlySimpleTypes, $definingClass);
        } catch (\Exception $e2) {
            throw $exception != null && $typeHint == null ? $exception : $e2;
        }
    }

    public function getMethodSummaryFromDoc(string $className, string $methodName): ?string {
        $reflectionMethod = new \ReflectionMethod($className, $methodName);
        return $this->parseFromDoc($reflectionMethod->getDocComment(), '/\*\s*([^\@\*\s][^\@\*\n]+)/');
    }

    private function getParamDescriptionFromDoc(string $paramName, \ReflectionMethod $reflectionMethod): ?string {
        return $this->parseFromDoc($reflectionMethod->getDocComment(), '/@param\s+[^\s]+\s+\$' . $paramName . '\s+([^\*\/]*)/');
    }
    
    private function getPropertyDescriptionFromDoc(string $propertyName, \ReflectionProperty $reflectionProperty): ?string {
        return $this->parseFromDoc($reflectionProperty->getDocComment(), '/@var\s+[^\s]+\s+\$' . $propertyName . '\s+([^\*\/]*)/');
    }

    public function getReturnDescriptionFromDoc(string $className, string $methodName): ?string {
        $reflectionMethod = new \ReflectionMethod($className, $methodName);
        return $this->parseFromDoc($reflectionMethod->getDocComment(), '/@return\s+[^\s]+\s+([^\*\/]*)/');
    }
    
    private function getParamTypeHintFromDoc(string $paramName, \ReflectionMethod $reflectionMethod): ?string {
        return $this->parseFromDoc($reflectionMethod->getDocComment(), '/@param\s+([^\s]+)\s+\$' . $paramName . '/');
    }
    
    private function getPropertyTypeHintFromDoc(string $propertyName, \ReflectionProperty $reflectionProperty): ?string {
        return $this->parseFromDoc($reflectionProperty->getDocComment(), '/@var\s+([^\s]+)\s+\$' . $propertyName . '/');
    }

    private function getReturnTypeHintFromDoc(\ReflectionMethod $reflectionMethod): ?string {
        return $this->parseFromDoc($reflectionMethod->getDocComment(), '/@return\s+([^\s]+)/');
    }

    private function parseFromDoc(string|false $docComment, string $pattern): ?string {
        if ($docComment) {
            if (preg_match($pattern, $docComment, $matches)) {
                list(, $type) = $matches;
                return $type;
            }
        }

        return null;
    }

    private function getTypeSchemaFromTypeOrTypeHint(?string $type, bool $isNullAllowed, string $providedIn, bool $ensureOnlySimpleTypes, \ReflectionClass $definingClass): TypeSchema {
        if ($type == null) throw new \Exception($providedIn . ' does not have a type description. Either provide type hint or annotation.');

        $isRequired = true;
        if (substr($type, 0, 1) == "?") {
            $isRequired = false;
            $type = substr($type, 1);
        }
        if ($isNullAllowed) {
            $isRequired = false;
        }

        switch($type) {
            case 'void':
                return new TypeSchema('void', false, $isRequired);
            case \bool::class:
                return new TypeSchema('boolean', false, $isRequired, null, null, function(string $value) { return $value=="true" || $value==1 || $value===true; });
            case \byte::class:
            case \sbyte::class:
            case \char::class:
            case \string::class:
                return new TypeSchema('string', false, $isRequired, null, null, function(string $value) { return $value; });
            case \float::class:
                return new TypeSchema('number', false, $isRequired, 'float', null, function(string $value) { return floatval($value); });
            case \double::class:
                return new TypeSchema('number', false, $isRequired, 'double', null, function(string $value) { return doubleval($value); });
            case \int::class:
                return new TypeSchema('integer', false, $isRequired, 'int32', null, function(string $value) { return intval($value); });
            case \long::class:
                return new TypeSchema('integer', false, $isRequired, 'int64', null, function(string $value) { return +$value; });
        }

        /*
        decimal	System.Decimal
        uint	System.UInt32
        nint	System.IntPtr
        nuint	System.UIntPtr
        ulong	System.UInt64
        short	System.Int16
        ushort	System.UInt16
        */

        if (str_ends_with($type, "[]")) {
            $arrayType = substr($type, 0, strlen($type)-2);
            return new TypeSchema('array', false, $isRequired, null, $this->getTypeSchemaFromTypeOrTypeHint($arrayType, false, $providedIn, $ensureOnlySimpleTypes, $definingClass));
        }

        if (!$ensureOnlySimpleTypes) {
            // find full class name
            $useAliases = $this->getUseAliasesFromClass($definingClass);
            $type = array_key_exists(strtolower($type), $useAliases) ? $useAliases[strtolower($type)] : $type;

            // check if class can be loaded (needed in order to have classes autoloaded before calling get_declared_classes)
            try {
                if ($this->container != null) $this->container->get($type);
            } catch (\Exception $e) {
                // ignore exceptions, we just want the class to be loaded even if it is not resolvable successfully
            }

            foreach (get_declared_classes() as $className) {
                if ($className === $type) {
                    if ($this->isEnumClass($className)) {
                        return new TypeSchema('string', false, $isRequired, null); // enums are always exchanged as string // TODO: encode enum options in swagger?
                    }
                    else {
                        return new TypeSchema($type, true, $isRequired);
                    }
                }
            }
        }
        
        throw new \Exception($providedIn . ' is of type \'' . $type . '\' but this type can not be mapped. Please make sure the type is correctly specified, e.g. by annotations, and that class definition is properly included when not using dependency injection.');
    }

    private static function checkPublicOrProtectedModifier(\ReflectionProperty $property, string $providedIn): void {
        if (!$property->isPublic() && !$property->isProtected()) throw new \Exception($providedIn . ' needs to be either protected or public.');
    }

    private static function isEnumClass(string $class): bool {
        try {
            new \ReflectionEnum($class);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @param ReflectionClass $class
     * @return string[]
     */
    private static function getUseAliasesFromClass(\ReflectionClass $class): array {
        $namespace = $class->getNamespaceName();
        $aliases = array();
        $classFile = $class->getFileName();
        if ($classFile) {
            $classCode = file_get_contents($classFile);
            $tokenParser = new TokenParser($classCode);
            $aliases = $tokenParser->parseUseStatements($namespace);
            return $aliases;
        }
    }
}
?>
