<?PHP
declare(strict_types=1);
namespace PSwag\Example\Application\Middlewares;

use PSwag\Authentication\BasicAuthMiddleware;

class MyBasicAuthMiddleware extends BasicAuthMiddleware
{
    public function isUserCredentialValid(string $username, string $password): bool {
        return $username == "user" && $password == "1234";
    }
}
?>
