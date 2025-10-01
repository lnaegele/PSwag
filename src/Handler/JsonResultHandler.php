<?PHP
declare(strict_types=1);
namespace PSwag\Handler;

use Psr\Http\Message\ResponseInterface;

class JsonResultHandler
{
    function __construct(
    ) {}

    public function handle(mixed $result, ResponseInterface $response): ResponseInterface {
        $resultJson = json_encode($result, JSON_UNESCAPED_SLASHES);
        $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write(''.$resultJson);
        return $response;
    }
}