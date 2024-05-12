<?PHP
declare(strict_types=1);
namespace PSwag\Example\Application\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSwag\Interfaces\ApiKeyAuthMiddlewareInterface;
use PSwag\Interfaces\ApiKeyInType;

class ApiKeyAuthMiddleware implements ApiKeyAuthMiddlewareInterface
{
    public function getName(): string {
        return "api_key";
    }

    public function getIn(): ApiKeyInType {
        return ApiKeyInType::Header;
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        // TODO: check api key
        return $handler->handle($request);
    }
}
?>
