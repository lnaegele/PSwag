<?PHP
declare(strict_types=1);
namespace PSwag\Example\Application\Middlewares;

use PSwag\Authentication\BearerAuthMiddleware;

class MyBearerAuthMiddleware extends BearerAuthMiddleware
{
    public function getBearerFormat(): ?string {
        return null; // "JWT"
    }
    
    public function isBearerTokenValid(string $bearerToken): bool {
        return $bearerToken == "1234";
    }
}