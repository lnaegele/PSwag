<?PHP
declare(strict_types=1);
namespace PSwag\Authentication;

use Psr\Http\Message\ServerRequestInterface;

final class HeaderHelper
{
    public static function getAuthorizationHeader(ServerRequestInterface $request, $headerValuePrefix): ?string
    {
        $headers = $request->getHeader("Authorization");
        foreach ($headers as $header) {
            if (str_starts_with($header, $headerValuePrefix)) {
                return substr($header, strlen($headerValuePrefix));
            }
        }

        // legacy: try to find auth header after redirects (behind proxies)
        $keys = array_keys($_SERVER);
        $headerKeys = preg_grep("/^(REDIRECT\_)*HTTP\_AUTHORIZATION$/", $keys);
        foreach ($headerKeys as $headerKey) {
            $header = $_SERVER[$headerKey];
            if (str_starts_with($header, $headerValuePrefix)) {
                return substr($header, strlen($headerValuePrefix));
            }
        }
        
        return null;
    }
}