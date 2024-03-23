<?PHP
declare(strict_types=1);
namespace PSwag\Model;

class Property
{
    /**
     * @param string $name
     * @param TypeSchema $typeSchema
     */
    function __construct(
        private string $name,
        private ?string $description,
        private TypeSchema $typeSchema
    ) {}

    public function getName(): string {
        return $this->name;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function getTypeSchema(): TypeSchema {
        return $this->typeSchema;
    }

    public function __toString(): string {
        return $this->name . ': ' . $this->typeSchema->__toString();
    }
}
?>
