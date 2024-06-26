<?PHP
declare(strict_types=1);
namespace PSwag\Model;

use Exception;

class TypeSchema
{
    /**
     * @param string $type
     * @param bool $isEnum
     * @param bool $isCustomDto
     * @param bool $isRequired
     * @param ?string $format
     * @param ?TypeSchema $arraySubTypeSchema
     * @param $parserFunc
     */
    function __construct(
        private string $type,
        private bool $isEnum,
        private bool $isCustomDto,
        private bool $isRequired,
        private ?string $format = null,
        private ?TypeSchema $arraySubTypeSchema = null,
        private $parserFunc = null,
    ) {}

    public function getType(): string {
        return $this->type;
    }


    public function isEnum(): bool {
        return $this->isEnum;
    }

    public function isCustomDto(): bool {
        return $this->isCustomDto;
    }

    public function isRequired(): bool {
        return $this->isRequired;
    }

    public function getFormat(): ?string {
        return $this->format;
    }

    public function getArraySubTypeSchema(): ?TypeSchema {
        return $this->arraySubTypeSchema;
    }

    public function isVoid(): bool {
        return $this->type == 'void';
    }

    public function isArray(): bool {
        return $this->type === 'array';
    }

    public function parse(mixed $value): mixed {
        if ($this->parserFunc==null) {
            return $value;
        }

        $fun = $this->parserFunc;
        return $fun($value);
    }

    public function __toString()
    {
        if ($this->isArray()) return $this->arraySubTypeSchema->__toString() . '[]';
        return ($this->isRequired ? '' : '?') . $this->type . ($this->format ? '\'' . $this->format : '');
    }
}
?>
