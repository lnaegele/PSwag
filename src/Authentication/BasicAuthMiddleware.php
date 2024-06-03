<?PHP
declare(strict_types=1);
namespace PSwag\Authentication;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSwag\Authentication\Interfaces\AuthMiddlewareInterface;
use Slim\Psr7\Response;

abstract class BasicAuthMiddleware implements AuthMiddlewareInterface
{
    public abstract function isUserCredentialValid(string $username, string $password): bool;

    public final function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $authToken = HeaderHelper::getAuthorizationHeader($request, "Basic ");

        if ($authToken == null) {
            return new Response(StatusCodeInterface::STATUS_UNAUTHORIZED);
        }
        
        $decoded = base64_decode($authToken);
        if ($decoded===false) {
            return new Response(StatusCodeInterface::STATUS_UNAUTHORIZED);
        }
        
        $parts = explode(':', $decoded, 2);
        if (count($parts)!=2 || !$this->isUserCredentialValid($parts[0], $parts[1])) {
            return new Response(StatusCodeInterface::STATUS_UNAUTHORIZED);
        }
        
        return $handler->handle($request);
    }
}
?>
