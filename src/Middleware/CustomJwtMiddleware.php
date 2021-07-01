<?php
namespace App\Middleware;

use PsrJwt\Auth\Authorise;
use PsrJwt\JwtAuthMiddleware;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Nyholm\Psr7\Response;
use App\Exceptions\CustomException;
use Slim\Routing\RouteContext;
use App\Helpers\JwtHelper;

class CustomJwtMiddleware extends Authorise implements RequestHandlerInterface
{
    protected $jwt;

    public function __construct($settings)
    {
        $this->jwt = $settings;
        parent::__construct($this->jwt['secret'], $this->jwt['tokenKey']);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $auth = $this->authorise($request);

        if (!$request->hasHeader('Authorization')) {
            throw new CustomException(2000,'Invalid Authorization','Supplied Token is invalid or expired',403);
        }

        try {

            $bearer_token = $request->getHeader('Authorization');
            $token = explode(" ", $bearer_token[0]);
    
            // Parse token - get its content
            $jwt_controller = new JwtHelper($this->jwt);
            $parsed_content = $jwt_controller->getPayload($token[1]);
            $token_date = $parsed_content['date']['date'];

            // Get uid from the route
            $routeContext = RouteContext::fromRequest($request);
            $route = $routeContext->getRoute();
            $uid = $route->getArgument('users_id');
            // Check if uid is the same as the token
            if($uid != $parsed_content['uid'])
            {
                throw new CustomException(2000,'Invalid Authorization','Supplied Token is invalid or expired.',406);
            }
 
            $date_now = new \DateTime();
            $date2    = new \DateTime($token_date);

            // Check if token date has expired
            if ($date_now > $date2) {

                throw new CustomException(2006,'Invalid Authorization','Token is expired.',406);
            }

        } catch (\Exception $e) {

            throw new CustomException(2000,'Invalid Authorization','Supplied Token is invalid or expired',403);
        }

        return new Response(
            $auth->getCode(),
            [],
            'The Response Body',
            '1.1',
            $auth->getMessage()
        );
    }
}
