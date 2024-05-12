<?PHP
declare(strict_types=1);
namespace PSwag;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use PSwag\Model\RouteWrapper;
use PSwag\Reflection\ReflectionHelper;
use PSwag\Swagger\SwaggerUiMiddleware;
use Slim\App;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Interfaces\MiddlewareDispatcherInterface;
use Slim\Interfaces\RouteCollectorInterface;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Slim\Interfaces\RouteGroupInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteResolverInterface;
use Slim\Middleware\BodyParsingMiddleware;
use Slim\Middleware\ErrorMiddleware;
use Slim\Middleware\RoutingMiddleware;

class PSwagApp implements RouteCollectorProxyInterface
{
    private PSwagRegistry $registry;

    public function __construct(private App $app)
    {
        $this->registry = new PSwagRegistry();
    }

    /**
     * Add the PSwag built-in swagger middleware to the app middleware stack
     *
     * @return SwaggerUiMiddleware
     */
    public function addSwaggerUiMiddleware(string $pattern, string $applicationName, string $version, string $pathToSwaggerUiDist): SwaggerUiMiddleware
    {
        $swaggerUiMiddleware = new SwaggerUiMiddleware($pattern, $applicationName, $version, $pathToSwaggerUiDist, $this->registry, $this->getRouteCollector(), $this->getContainer());
        $this->add($swaggerUiMiddleware);
        return $swaggerUiMiddleware;
    }

    /**
     * @return RouteResolverInterface
     */
    public function getRouteResolver(): RouteResolverInterface
    {
        return $this->app->getRouteResolver();
    }

    /**
     * @return MiddlewareDispatcherInterface
     */
    public function getMiddlewareDispatcher(): MiddlewareDispatcherInterface
    {
        return $this->app->getMiddlewareDispatcher();
    }

    /**
     * @param MiddlewareInterface|string|callable $middleware
     */
    public function add($middleware): self
    {
        $this->app->add($middleware);
        return $this;
    }

    /**
     * @param MiddlewareInterface $middleware
     */
    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->app->addMiddleware($middleware);
        return $this;
    }

    /**
     * Add the Slim built-in routing middleware to the app middleware stack
     *
     * This method can be used to control middleware order and is not required for default routing operation.
     *
     * @return RoutingMiddleware
     */
    public function addRoutingMiddleware(): RoutingMiddleware
    {
        return $this->app->addRoutingMiddleware();
    }

    /**
     * Add the Slim built-in error middleware to the app middleware stack
     *
     * @param bool                 $displayErrorDetails
     * @param bool                 $logErrors
     * @param bool                 $logErrorDetails
     * @param LoggerInterface|null $logger
     *
     * @return ErrorMiddleware
     */
    public function addErrorMiddleware(
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
        ?LoggerInterface $logger = null
    ): ErrorMiddleware {
        return $this->app->addErrorMiddleware($displayErrorDetails, $logErrors, $logErrorDetails, $logger);
    }

    /**
     * Add the Slim body parsing middleware to the app middleware stack
     *
     * @param callable[] $bodyParsers
     *
     * @return BodyParsingMiddleware
     */
    public function addBodyParsingMiddleware(array $bodyParsers = []): BodyParsingMiddleware
    {
        return $this->app->addBodyParsingMiddleware($bodyParsers);
    }

    /**
     * Run application
     *
     * This method traverses the application middleware stack and then sends the
     * resultant Response object to the HTTP client.
     *
     * @param ServerRequestInterface|null $request
     * @return void
     */
    public function run(?ServerRequestInterface $request = null): void
    {
        $this->app->run($request);
    }

    /**
     * Handle a request
     *
     * This method traverses the application middleware stack and then returns the
     * resultant Response object.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->app->handle($request);
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseFactory(): ResponseFactoryInterface
    {
        return $this->app->getResponseFactory();
    }

    /**
     * {@inheritdoc}
     */
    public function getCallableResolver(): CallableResolverInterface
    {
        return $this->app->getCallableResolver();
    }

    /**
     * {@inheritdoc}
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->app->getContainer();
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteCollector(): RouteCollectorInterface
    {
        return $this->app->getRouteCollector();
    }

    /**
     * {@inheritdoc}
     */
    public function getBasePath(): string
    {
        return $this->app->getBasePath();
    }

    /**
     * {@inheritdoc}
     */
    public function setBasePath(string $basePath): RouteCollectorProxyInterface
    {
        return $this->app->setBasePath($basePath);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $pattern, $callable): RouteInterface
    {
        return $this->map(['GET'], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $pattern, $callable): RouteInterface
    {
        return $this->map(['POST'], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $pattern, $callable): RouteInterface
    {
        return $this->map(['PUT'], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $pattern, $callable): RouteInterface
    {
        return $this->map(['PATCH'], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $pattern, $callable): RouteInterface
    {
        return $this->map(['DELETE'], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function options(string $pattern, $callable): RouteInterface
    {
        return $this->map(['OPTIONS'], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function any(string $pattern, $callable): RouteInterface
    {
        return $this->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function map(array $methods, string $pattern, $callable): RouteInterface
    {
        // TODO: only allow class name + method name, or class with __invoke method, forbid lamdas as they do not work with reflection(?)
        if (!is_array($callable) || count($callable) != 2) {
            return $this->app->map($methods, $pattern, $callable);
        }

        $endpoint = new EndpointDefinition($pattern, $methods,  $callable[0], $callable[1]);
        $this->registry->register($endpoint);

        $reflectionHelper = new ReflectionHelper($this->getContainer());        
        $route = $this->app->map($methods, $pattern, function (ServerRequestInterface $request, ResponseInterface $response, array $pathVariables) use ($endpoint, &$reflectionHelper) {
            $handler = new RequestHandler($endpoint, $reflectionHelper);
            return $handler->execute($request, $response, $pathVariables);
        });
        return new RouteWrapper($route, $endpoint);
    }

    /**
     * {@inheritdoc}
     */
    public function group(string $pattern, $callable): RouteGroupInterface
    {
        // TODO: implement own RouteGroupInterface which is adding swagger logic
        return $this->app->group($pattern, $callable);
    }

    /**
     * {@inheritdoc}
     */
    public function redirect(string $from, $to, int $status = 302): RouteInterface
    {
        // TODO: implement own swagger logic
        return $this->app->redirect($from, $to, $status);
    }
}
?>
