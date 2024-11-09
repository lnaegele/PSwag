<?PHP
declare(strict_types=1);
namespace PSwag\Model;

class CustomResult
{
    /**
     * @param string $mimeType
     * @param string $body
     * @param ?string $fileName
     */
    function __construct(
        public string $mimeType,
        public string $body,
        public ?string $fileName=null,
        public ?int $lastModifiedTime=null,
        public ?int $cacheMaxAge=null,
    ) {}
}
?>
