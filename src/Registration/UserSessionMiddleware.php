<?php
namespace App\Registration;

use App\Exceptions\CaterlyException;

class UserSessionMiddleware {

    protected $database_adapter;

    public function __construct($database_adapter) {
        $this->database_adapter = $database_adapter;
    }

    public function __invoke ($request, $response, $next) {
        $headers = $request->getHeaders();  
        $routeParams = $request->getAttribute('routeInfo')[2];
        
        if (!isset($headers['HTTP_AUTHORIZATION']))
            throw new CaterlyException(2000, "Supplied Token is invalid or expired", 401);

        $bearer_token = $headers['HTTP_AUTHORIZATION'];

        $token = explode(" ", $bearer_token[0]);
        $user_id = $routeParams['user_id'];

        $pdo_mapper = new PDOMapper($this->database_adapter);
        
        if (!$pdo_mapper->checkToken($user_id, $token[1]))
            throw new CaterlyException(2000, "Supplied Token is invalid or expired", 401);

        return $next($request, $response);
    }
}

