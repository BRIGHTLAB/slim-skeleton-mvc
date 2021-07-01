<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

// Set the absolute path to the root directory.
$rootPath = realpath(__DIR__ . '/..');

// Include the composer autoloader.
include_once($rootPath . '/vendor/autoload.php');

// Instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

// loading enviroment variables
// If env file not found, load variables from dockerfile
if(file_exists(__DIR__.'/../.env'))
{
	$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__.'/../');
	$dotenv->load();
}

// Set up settings
$settings = require $rootPath . '/conf/settings.php';
$settings($containerBuilder);

// Set up dependencies
$dependencies = require $rootPath . '/conf/dependencies.php';
$dependencies($containerBuilder);

// Build PHP-DI Container instance
$container = $containerBuilder->build();

$settings = $container->get('settings');

//$error_codes = $dependencies('error_codes');
// print_r($container->get('error_codes')[1001]);
// exit;

// Instantiate the app
$app = AppFactory::createFromContainer($container);
$app->setBasePath($settings['base_path']);

$routeParser = $app->getRouteCollector()->getRouteParser();
$container->set(\Slim\Interfaces\RouteParserInterface::class, $routeParser);

// Register routes
$routes = require $rootPath . '/conf/routes.php';
$routes($app);

// Register middleware
$middleware = require $rootPath . '/conf/middleware.php';
$middleware($app);


// Set the cache file for the routes. Note that you have to delete this file
// whenever you change the routes.
if (!$settings['debug']) {
	$app->getRouteCollector()->setCacheFile($settings['route_cache']);
}

// Add the routing middleware.
$app->addRoutingMiddleware();



$customErrorHandler = function (
    ServerRequestInterface $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails,
    ?LoggerInterface $logger = null
) use ($app, $container) {

    $error_codes = $container->get('error_codes');

    // Default status code error
    $default_status = 500;
    
    // Define the payload based on which exception class is being used
    switch (get_class($exception)) {
        
        case 'CustomException':

            // Set our own default status
            $default_status = $exception->getStatusCode();
            // Set our own description
            $exception->setDescription($error_codes[$exception->getCode()]["title"]);
            // Form our own payload
            $payload = [
                'code' => $exception->getCode(),
                'title' => $exception->getTitle(),
                'message' => $exception->getMessage(), 
                'status' => $default_status];
            break;
        
        case 'App\Exceptions\CustomException':

            // Set our own default status
            $default_status = $exception->getStatusCode();
            // Set our own description
            $exception->setDescription($error_codes[$exception->getCode()]["title"]);
            // Form our own payload
            $payload = [
                'code' => $exception->getCode(),
                'title' => $exception->getTitle(),
                'message' => $exception->getMessage(), 
                'status' => $default_status];
            break;
        
        default:
            $payload = [
                'code' => $default_status,
                'message' => $exception->getMessage()];
            break;
    }

    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(
        json_encode($payload, JSON_UNESCAPED_UNICODE)
    );
    return $response->withHeader('Access-Control-Allow-Origin', '*')
                    ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                    ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus($default_status);
};

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware(false, false, false, null);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);


// Run the app
$app->run();
