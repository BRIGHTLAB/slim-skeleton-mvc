<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class HealthCheckAPIController extends BaseController
{

	public function index(Request $request, Response $response, array $args = []): Response
	{

		$data = ['status' => 'running'];
		$payload = json_encode($data);

		$response->getBody()->write($payload);
		return $response->withHeader('Content-Type', 'application/json');
	}

}
