<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
//use \Slim\Http\Response as Response;
//use \Slim\Http\Request as Request;
use App\Mappers\UsersMapper;

final class UserAPIController extends BaseController
{
	public function index(Request $request, Response $response, array $args = [])
	{
		$mapper = new UsersMapper($this->database_adapter);
		$results = $mapper->fetch();

		//$this->logger->info("Home page action dispatched");

		//$data = ['name' => 'Bob', 'age' => 40];
		//$payload = json_encode($data);

		$payload = json_encode($results, JSON_PRETTY_PRINT);
		$response->getBody()->write($payload);
    	return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
	}
}
