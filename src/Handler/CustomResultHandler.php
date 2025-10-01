<?PHP
declare(strict_types=1);
namespace PSwag\Handler;

use Psr\Http\Message\ResponseInterface;
use PSwag\Model\CustomResult;

class CustomResultHandler
{
    function __construct(
    ) {}

    public function handle(CustomResult $result, ResponseInterface $response): ResponseInterface {
        $response->getBody()->write(''.($result->body));
        $response = $response->withHeader('Content-Type', $result->mimeType);
        if ($result->fileName!=null) $response = $response->withHeader('Content-disposition', 'inline;filename="'.str_replace('"', '_', $result->fileName).'"');
        if ($result->cacheMaxAge!=null) $response = $response->withHeader('Cache-control', 'max-age='.$result->cacheMaxAge)->withHeader('Expires', gmdate(DATE_RFC1123,time()+$result->cacheMaxAge));
        if ($result->lastModifiedTime!=null) $response = $response->withHeader('Last-Modified', gmdate(DATE_RFC1123,$result->lastModifiedTime));

        return $response;
    }
}