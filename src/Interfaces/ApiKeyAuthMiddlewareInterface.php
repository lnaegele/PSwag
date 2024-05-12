<?PHP
declare(strict_types=1);
namespace PSwag\Interfaces;

interface ApiKeyAuthMiddlewareInterface extends AuthMiddlewareInterface
{
    public function getName(): string;

    public function getIn(): ApiKeyInType;
}

enum ApiKeyInType {
    case Header;
    case Query;
    case Cookie;
}
?>
