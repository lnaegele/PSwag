<?PHP
declare(strict_types=1);
namespace PSwag\Authentication;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSwag\Authentication\Interfaces\AuthMiddlewareInterface;
use Slim\Psr7\Response;

abstract class BearerAuthMiddleware implements AuthMiddlewareInterface
{
    public abstract function getBearerFormat(): ?string;
    
    public abstract function isBearerTokenValid(string $bearerToken): bool;

    public final function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $bearerToken = HeaderHelper::getAuthorizationHeader($request, "Bearer ");

        if ($bearerToken == null || !$this->isBearerTokenValid($bearerToken)) {
            return new Response(StatusCodeInterface::STATUS_UNAUTHORIZED);
        }
        
        return $handler->handle($request);
    }
}