<?PHP
declare(strict_types=1);
namespace PSwag;

class PSwagRegistry {
    private array $endpoints = [];

    public function __construct() {}

    public function register(EndpointDefinition $endpoint) {
        $this->endpoints[] = $endpoint;
    }

    public function getAll(): array {
        return $this->endpoints;
    }
}