<?PHP
declare(strict_types=1);
namespace PSwag;

use PSwag\Authentication\Interfaces\AuthMiddlewareInterface;

class EndpointDefinition {

    /** @var AuthMiddlewareInterface[] $authMiddlewares */
    private array $authMiddlewares = [];

    public function __construct(
        private string $pattern,
        /** @var string[] $methods */
        private array $methods,
        private string $applicationServiceClass,
        private string $applicationServiceMethod,
        /** @var ?string[] $applicationTags */
        private ?array $applicationTags,
    ) {}

    public function addAuthMiddleware(AuthMiddlewareInterface $middleware): void {
        $this->authMiddlewares[] = $middleware;
    }

    /**
     * @return AuthMiddlewareInterface[]
     */
    public function getAuthMiddlewares(): array {
        return $this->authMiddlewares;
    }
    
    public function getPattern(): string {
        return $this->pattern;
    }
    
    public function setPattern(string $pattern): void {
        $this->pattern = $pattern;
    }
    
    /**
     * @return string[]
     */
    public function getMethods(): array {
        return $this->methods;
    }
    
    /**
     * @param string[] $methods
     */
    public function setMethods($methods): void {
        $this->methods = $methods;
    }

    public function getApplicationServiceClass():string {
        return $this->applicationServiceClass;
    }

    public function getApplicationServiceMethod(): string {
        return $this->applicationServiceMethod;
    }

    public function getApplicationTags(): ?array {
        return $this->applicationTags;
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