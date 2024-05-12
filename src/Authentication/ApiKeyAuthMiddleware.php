<?PHP
declare(strict_types=1);
namespace PSwag\Authentication;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSwag\Authentication\Interfaces\AuthMiddlewareInterface;
use Slim\Psr7\Response;

abstract class ApiKeyAuthMiddleware implements AuthMiddlewareInterface
{
    public abstract function getName(): string;

    public abstract function getIn(): ApiKeyInType;

    public abstract function isApiKeyValid(string $apiKey): bool;

    public final function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $apiKey = match ($this->getIn()) {
            ApiKeyInType::Header => $this->getApiKeyFromHeader($request),
            ApiKeyInType::Cookie => $this->getApiKeyFromCookie($request),
            ApiKeyInType::Query => $this->getApiKeyFromQuery($request),
        };

        if ($apiKey == null || !$this->isApiKeyValid($apiKey)) {
            return new Response(StatusCodeInterface::STATUS_UNAUTHORIZED);
        }
        
        return $handler->handle($request);
    }

    private function getApiKeyFromHeader(ServerRequestInterface $request): ?string
    {
        $headers = $request->getHeader($this->getName());
        foreach ($headers as $header) {
            return $header;
        }

        return null;
    }

    private function getApiKeyFromCookie(ServerRequestInterface $request): ?string
    {
        $cookieName = $this->getName();
        $cookieParams = $request->getCookieParams();
        if (array_key_exists($cookieName, $cookieParams)) {
            return $cookieParams[$cookieName];
        }

        return null;
    }

    private function getApiKeyFromQuery(ServerRequestInterface $request): ?string
    {
        $paramName = $this->getName();
        $queryParams = $request->getQueryParams();
        if (array_key_exists($paramName, $queryParams)) {
            return $queryParams[$paramName];
        }

        return null;
    }
}

enum ApiKeyInType {
    case Header;
    case Query;
    case Cookie;
}
?>
