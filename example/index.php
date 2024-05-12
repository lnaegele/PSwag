<?php
chdir(__DIR__);
require_once "vendor\\autoload.php";

use DI\Container;
use PSwag\Example\Application\Middlewares\ApiKeyAuthMiddleware;
use PSwag\Example\Application\Middlewares\BasicAuthMiddleware;
use PSwag\PSwagApp;
use PSwag\Example\Application\Services\PetApplicationService;
use Slim\Factory\AppFactory;

// if you use dependency injection, PSwag does class loading for you. If you do not use DI, you must ensure to include all dtos explicitly. E.g.:
// require_once('application/dtos/Pet.php');
AppFactory::setContainer(new Container());
$slimApp = AppFactory::create();

// create wrapper PSwagApp
$app = new PSwagApp($slimApp);

// this is important to make swagger UI work
$isRewritingEnabled = false;
$app->setBasePath(substr($_SERVER['SCRIPT_NAME'], 0, strlen($_SERVER['SCRIPT_NAME']) - ($isRewritingEnabled ? strlen('/index.php') : 0)));

// add routing middleware first, otherwise it would try to resolve route before swagger middleware can react
$app->addRoutingMiddleware();

// add swagger middleware: specify url pattern under which swagger UI shall be accessbile, and provide relative path to swagger ui dist.
$app->addSwaggerUiMiddleware('/', 'PSwag example', '1.0.0', 'vendor/swagger-api/swagger-ui/dist/');

// register endpoints by specifying class and method name
$app->get('/pet/{petId}', [PetApplicationService::class, 'getPetById'])->addMiddleware(new ApiKeyAuthMiddleware());
$app->delete('/pet/{petId}', [PetApplicationService::class, 'deletePetById'])->addMiddleware(new BasicAuthMiddleware());
$app->post('/pet', [PetApplicationService::class, 'createNewPet']);
$app->put('/pet', [PetApplicationService::class, 'updatePetById']);

$app->run();
?>
