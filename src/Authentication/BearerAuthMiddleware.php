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
    public abstract function isAuthTokenValid(string $authToken): bool;

    public abstract function getBearerFormat(): ?string;

    public final function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $authToken = $this->getAuthTokenFromRequest($request);

        if ($authToken == null || !$this->isAuthTokenValid($authToken)) {
            return new Response(StatusCodeInterface::STATUS_UNAUTHORIZED);
        }
        
        return $handler->handle($request);
    }

    private function getAuthTokenFromRequest(ServerRequestInterface $request): ?string
    {
        $headers = $request->getHeader("Authorization");
        foreach ($headers as $header) {
            if (str_starts_with($header, "Bearer ")) {
                return substr($header, 7);
            }
        }

        return null;
    }
}
?>
