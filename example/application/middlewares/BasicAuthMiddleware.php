<?PHP
declare(strict_types=1);
namespace PSwag\Example\Application\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PSwag\Interfaces\BasicAuthMiddlewareInterface;
use PSwag\Interfaces\BasicAuthSchemeType;

class BasicAuthMiddleware implements BasicAuthMiddlewareInterface
{
    public function getScheme(): BasicAuthSchemeType {
        return BasicAuthSchemeType::Bearer;
    }

    public function getBearerFormat(): ?string {
        return "JWT";
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        // TODO: check basic auth
        return $handler->handle($request);
    }
}
?>
