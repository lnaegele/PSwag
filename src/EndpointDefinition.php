<?PHP
declare(strict_types=1);
namespace PSwag;

class EndpointDefinition {

    public function __construct(
        private string $pattern,
        private string $method,
        private string $applicationServiceClass,
        private string $applicationServiceMethod) {}
    
    public function getPattern(): string {
        return $this->pattern;
    }
    
    public function getMethod(): string {
        return $this->method;
    }

    public function getApplicationServiceClass():string {
        return $this->applicationServiceClass;
    }

    public function getApplicationServiceMethod(): string {
        return $this->applicationServiceMethod;
    }

    /**
     * Returns path variable names, e.g. from "GET /cars/{id}"
     * @return string[]
     */
    public function getPathVariableNames(): array {
        preg_match_all('/\{(?<match>[^\{]+)\}/s', $this->pattern, $matches, PREG_OFFSET_CAPTURE);
        return array_map(function ($match) { return $match[0]; }, $matches['match']);
    }
}
?>