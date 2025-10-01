<?PHP
declare(strict_types=1);
namespace PSwag\Model;

class FileStreamResult
{
    function __construct(
        public string $filePath,
        public ?string $fileName,
        public ?int $cacheMaxAge = null,
    ) {}
}