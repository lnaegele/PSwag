<?PHP
declare(strict_types=1);
namespace PSwag\Interfaces;

interface BasicAuthMiddlewareInterface extends AuthMiddlewareInterface
{
    public function getScheme(): BasicAuthSchemeType;

    public function getBearerFormat(): ?string;
}

enum BasicAuthSchemeType {
    case Basic;
    case Bearer;
}
?>
