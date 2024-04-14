# PSwag - easily enhance your PHP code with Swagger

Easily create a REST API for your PHP functions - same way as you might know from ABP framework's application services - without the need to juggle with Requests and Responses anymore. All you need to do is providing proper type definitions for method parameters and return types of your endpoint functions.

## PSwag is an extension (wrapper) to Slim
- It automatically maps your custom method signatures to REST API endpoints
- It provides an always-up-to-date OpenAPI 3.0 specification of your REST API endpoints
- It embeds Swagger UI
- It supports GET, PUT, DELETE, PATCH, POST
- It supports PHP inbuilt types, enums, custom classes, arrays (of both, inbuilt and custom types), nullables
- Code annotations are directly used to show as descriptions in Swagger

## How to use with Composer
1. Edit your composer.json and add following:
```json
"require": {
    "...": "...",
    "pswag/pswag": "dev-main"
},
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/lnaegele/PSwag.git"
    }
]
```
2. Execute ```composer install``` in terminal.

## Example
Let's create an example for a Petstore. To specify an endpoint for our REST API, we first create a method  ```getPetById``` that takes an id and returns an object of type ```Pet```.
```php
class PetApplicationService
{
    /**
     * Find pet by ID
     * @param int $petId ID of pet to return
     * @return Pet Returns a single pet
     */
    public function getPetById(int $petId): Pet {
        return new Pet(10, 'doggie', new Category(1, 'Dog'), ['photo1.jpg'], [new Tag(0, 'cute')], 'available');
    }
}
```
Note that all parameters and also return type need to be properly typed in order to enable PSwag to derive the OpenAPI specification. When using custom types, e.g. classes ```Pet```, ```Category``` and ```Tag```, all of their properties need to be typed as well. Have a look at class ```Pet```:
```php
class Pet
{
    public ?int $id;

    public string $name;

    public ?Category $category;
    
    /** @var string[] $photoUrls */
    public array $photoUrls;
    
    /** @var ?PSwag\Example\Application\Dtos\Tag[] $tags */
    public ?array $tags;
    
    public ?string $status;
}
?>
```
For ```$photoUrls``` the type ```array``` is not sufficient. In such cases, its unique datatype can be specified as annotation with ```/** @var string[] $photoUrls */```. Now, PSwag knows how to use it in our endpoints. Same applies to ```$tags```, but in addition we're using a custom class inside the array.

IMPORTANT: When not in the same namespace as ```Pet```, class ```Tag``` must be referenced with fully qualified namespace in order to be resolvable by PSwag.

Finally, we create a Slim application in our index.php and register our method to it:
```php
<?php
require_once "vendor\\autoload.php";

use DI\Container;
use PSwag\PSwagApp;
use PSwag\Example\Application\Services\PetApplicationService;
use Slim\Factory\AppFactory;

// if you use dependency injection, PSwag does class loading for you. If you do not use DI, you must ensure to include all dtos explicitly. E.g.: require_once('application/dtos/Pet.php');
AppFactory::setContainer(new Container());
$slimApp = AppFactory::create();

// create wrapper PSwagApp
$app = new PSwagApp($slimApp);

// add routing middleware first, otherwise it would try to resolve route before swagger middleware can react
$app->addRoutingMiddleware();

// add swagger middleware: specify url pattern under which swagger UI shall be accessbile, and provide relative path to swagger ui dist.
$app->addSwaggerUiMiddleware('/', 'PSwag example', '1.0.0', 'vendor/swagger-api/swagger-ui/dist/');

// register endpoints by specifying class and method name
$app->get('/pet/{petId}', [PetApplicationService::class, 'getPetById']);
$app->delete('/pet/{petId}', [PetApplicationService::class, 'deletePetById']);
$app->post('/pet', [PetApplicationService::class, 'createNewPet']);
$app->put('/pet', [PetApplicationService::class, 'updatePetById']);

$app->run();
?>
```

You see that in GET and DELETE endpoints path variables are used. When specified, PSwag tries to automatically map them to method parameters of target function having the same name. If there isn't, it will add an additional input of type string to swagger - however, its value will never be passed to target function then.

When calling index.php, this is what we'll finally get:

![image](https://github.com/lnaegele/PSwag/assets/2114595/14c56bb3-196a-456b-8607-8892a23aaa0d)

That's it! I hope it is useful to you :-)
