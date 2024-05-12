<?PHP
declare(strict_types=1);
namespace PSwag\Model;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use PSwag\EndpointDefinition;
use PSwag\Interfaces\AuthMiddlewareInterface;
use Slim\Interfaces\InvocationStrategyInterface;
use Slim\Interfaces\RouteInterface;

class RouteWrapper implements RouteInterface
{
    /**
     * @param string $name
     * @param TypeSchema $typeSchema
     */
    function __construct(
        private RouteInterface $wrappedInstance,
        private EndpointDefinition $endpoint,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getInvocationStrategy(): InvocationStrategyInterface
    {
        return $this->wrappedInstance->getInvocationStrategy();
    }

    /**
     * {@inheritdoc}
     */
    public function setInvocationStrategy(InvocationStrategyInterface $invocationStrategy): RouteInterface
    {
        throw new Exception("Not supported by PSwag");
    }

    /**
     * {@inheritdoc}
     */
    public function getMethods(): array
    {
        return $this->wrappedInstance->getMethods();
    }

    /**
     * {@inheritdoc}
     */
    public function getPattern(): string
    {
        return $this->wrappedInstance->getPattern();
    }

    /**
     * {@inheritdoc}
     */
    public function setPattern(string $pattern): RouteInterface
    {
        $this->wrappedInstance = $this->wrappedInstance->setPattern($pattern);
        $this->endpoint->setPattern($pattern);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCallable()
    {
        return $this->wrappedInstance->getCallable();
    }

    /**
     * {@inheritdoc}
     */
    public function setCallable($callable): RouteInterface
    {
        throw new Exception("Not supported by PSwag");
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): ?string
    {
        return $this->wrappedInstance->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function setName(string $name): RouteInterface
    {
        $this->wrappedInstance = $this->wrappedInstance->setName($name);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifier(): string
    {
        return $this->wrappedInstance->getIdentifier();
    }

    /**
     * {@inheritdoc}
     */
    public function getArgument(string $name, ?string $default = null): ?string
    {
        return $this->wrappedInstance->getArgument($name, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function getArguments(): array
    {
        return $this->wrappedInstance->getArguments();
    }

    /**
     * {@inheritdoc}
     */
    public function setArgument(string $name, string $value): RouteInterface
    {
        $this->wrappedInstance = $this->wrappedInstance->setArgument($name, $value);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setArguments(array $arguments): self
    {
        $this->wrappedInstance = $this->wrappedInstance->setArguments($arguments);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function add($middleware): self
    {
        $this->wrappedInstance = $this->wrappedInstance->add($middleware);
        $this->checkAdd($middleware);
        return $this;
    }

    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->wrappedInstance = $this->wrappedInstance->addMiddleware($middleware);
        $this->checkAdd($middleware);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(array $arguments): self
    {
        $this->wrappedInstance = $this->wrappedInstance->prepare($arguments);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function run(ServerRequestInterface $request): ResponseInterface
    {
        return $this->wrappedInstance->run($request);
    }

    private function checkAdd($middleware): void {
        if ($middleware instanceof AuthMiddlewareInterface) {
            $this->endpoint->addAuthMiddleware($middleware);
        }
    }
}
?>
