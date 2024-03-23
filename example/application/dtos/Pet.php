<?php
namespace PSwag\Example\Application\Dtos;

use PSwag\Example\Application\Dtos\Tag; // needed, as Tag is not automatically loaded otherwise. This is, it is just used inside a comment

class Pet
{
    public ?int $id;

    public ?Category $category;
    
    public string $name;
    
    /** @var string[] $photoUrls */
    public array $photoUrls;
    
    /** @var ?Tag[] $tags */
    public ?array $tags;
    
    public ?string $status;
}
?>