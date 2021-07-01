<?php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

use App\Exceptions\CustomException;
use App\Mappers\PDOMapper;

class GuardMiddleware
{

    protected $database_adapter;

    public function __construct($database_adapter) {
        $this->database_adapter = $database_adapter;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response {

        $response = $handler->handle($request);

        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $arguments = $route->getArguments();

        if (!$request->hasHeader('Authorization')) {
            throw new CustomException(2000,'Invalid Authorization','Supplied Token is invalid or expired.',403);
        }
        // if (!isset($headers['HTTP_AUTHORIZATION']))
        // {

        //     $exception = new CustomException($request);
        //     $exception->setExceptionFields(403,"Invalid Authorization","Supplied Token is invalid or expired.","");
        //     throw $exception;
        // }

        $bearer_token = $request->getHeader('Authorization');
        $token = explode(" ", $bearer_token[0]);
        $user_id = $arguments['users_id'];

        $pdo_mapper = new PDOMapper($this->database_adapter, null);

        if (!$pdo_mapper->checkToken($user_id, $token[1]))
        {
            throw new CustomException(2000,'Invalid Authorization','Supplied Token is invalid or expired.',403);
        }

        return $response;
    }
}
