<?PHP
declare(strict_types=1);
namespace PSwag\Example\Application\Middlewares;

use PSwag\Authentication\ApiKeyAuthMiddleware;
use PSwag\Authentication\ApiKeyInType;

class MyApiKeyAuthMiddleware extends ApiKeyAuthMiddleware
{
    public function getName(): string {
        return "X-API-KEY";
    }

    public function getIn(): ApiKeyInType {
        return ApiKeyInType::Cookie;
    }
    
    public function isApiKeyValid(string $apiKey): bool {
        return $apiKey == "1234";
    }
}