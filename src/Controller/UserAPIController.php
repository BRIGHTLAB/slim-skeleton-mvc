<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class UserAPIController extends BaseController
{
	public function index(Request $request, Response $response, array $args = []): Response
	{
		$this->logger->info("Home page action dispatched");

		$data = ['name' => 'Bob', 'age' => 40];
		$payload = json_encode($data);

		$response->getBody()->write($payload);
		return $response->withHeader('Content-Type', 'application/json');
	}

}
