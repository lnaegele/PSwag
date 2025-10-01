<?php
declare(strict_types=1);
namespace PSwag\Swagger;

use Exception;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSwag\PSwagRegistry;
use PSwag\Reflection\ReflectionHelper;
use PSwag\Swagger\SwaggerGenerator;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\RouteCollectorInterface;
use Slim\Psr7\Response;

class SwaggerUiMiddleware implements MiddlewareInterface
{
    private SwaggerGenerator $swaggerGenerator;

    public function __construct(
        private string $pattern,
        private string $applicationName,
        private string $version,
        private string $pathToSwaggerUiDist,
        PSwagRegistry $registry,
        private RouteCollectorInterface $routeCollector,
        ?ContainerInterface $container)
    {
        $this->pathToSwaggerUiDist .= str_ends_with($this->pathToSwaggerUiDist, '/') ? '' : '/';
        
        $reflectionHelper = new ReflectionHelper($container);
        $this->swaggerGenerator = new SwaggerGenerator($registry, $reflectionHelper);
    }

    /**
     * Swagger UI midleware
     *
     * @param ServerRequestInterface $request PSR-7 request
     * @param RequestHandlerInterface $handler PSR-15 request handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() == 'GET') {            
            $route = $request->getUri()->getPath();
            $basePath = $this->routeCollector->getBasePath();
            $pathPrefix = rtrim($basePath . $this->pattern, '/');

            // redirect to path
            if ($route == $pathPrefix) {
                return (new Response())->withStatus(302)->withHeader('Location', $pathPrefix . '/');
            }

            // provide swagger configuration
            if ($route == $pathPrefix . '/swagger.json') {
                $swaggerJson = $this->swaggerGenerator->generate($this->applicationName, $this->version, $basePath);
                $resultJson = json_encode($swaggerJson, JSON_UNESCAPED_SLASHES);
                if ($resultJson===false) {
                    throw new Exception("Can not generate JSON from prepared swagger configuration.");
                }
                return $this->createResponseFromStringContent($resultJson, 'application/json; charset=utf-8');
            }

            // proxy to original swagger ui files defined in vendor
            if ($route && str_starts_with($route, $pathPrefix . '/')) {

                // prevent browsing attacks
                $fileName = substr($route, strlen($pathPrefix)+1);
                if (!$fileName || $fileName == '') $fileName = 'index.html';
                if (str_contains($fileName, '..')) {
                    throw new HttpBadRequestException($request, '\'..\' is not allowed in subpaths of swagger ui dir.');
                }
            
                // substitute initializer with proper swagger.json location
                if ($fileName == 'swagger-initializer.js') {
                    $javaScript = 'window.onload=function(){window.ui=SwaggerUIBundle({url:"swagger.json",dom_id:"#swagger-ui",deepLinking:true,presets:[SwaggerUIBundle.presets.apis,SwaggerUIStandalonePreset],plugins:[SwaggerUIBundle.plugins.DownloadUrl],layout:"StandaloneLayout"});};';
                    return $this->createResponseFromStringContent($javaScript, 'application/javascript; charset=utf-8');
                }
            
                // file does not exist in swagger ui folder
                $filePath = $this->pathToSwaggerUiDist . $fileName;
                if (!file_exists($filePath) || !is_file($filePath)) {
                    throw new HttpNotFoundException($request, 'Not able to find swagger UI dist files (\'' . $fileName . '\') at specified location \'' . $this->pathToSwaggerUiDist . '\'');
                }

                // read original file and return with proper mime type
                $contentType = 'text/plain';
                if (str_ends_with($fileName, '.html')) $contentType = 'text/html; charset=utf-8';
                else if (str_ends_with($fileName, '.js')) $contentType = 'application/javascript; charset=utf-8';
                else if (str_ends_with($fileName, '.css')) $contentType = 'text/css; charset=utf-8';
                else if (str_ends_with($fileName, '.png')) $contentType = 'image/png';
                return $this->createResponseFromStringContent(file_get_contents($filePath), $contentType);
            }
        }

        // nothing to do - pass to next middleware
        return $handler->handle($request);
    }

    private function createResponseFromStringContent(string $content, string $contentType, int $statusCode=200): ResponseInterface {
        $response = (new Response())->withStatus($statusCode);
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', $contentType);
    }
}