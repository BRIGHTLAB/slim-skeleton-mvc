<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
//use \Slim\Http\Response as Response;
//use \Slim\Http\Request as Request;
use App\Mappers\UsersMapper;

final class UserAPIController extends BaseController
{

	public function index(Request $request, Response $response, array $args = []): Response
	{
		$mapper = new UsersMapper($this->database_adapter);
		$results = $mapper->fetch();

        // Paginate the responses
        $count = count($results);
        $url_route = $this->router->urlFor('fetch_users');
	    $paginated_response = \App\Helpers\PaginationHelper::WrapPrevNextPages($url_route,$request,$results,$count);
		$payload = json_encode($paginated_response, JSON_PRETTY_PRINT);
		$response->getBody()->write($payload);
    	return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
	}

	public function env(Request $request, Response $response, array $args = []): Response
	{

		$data = ['db_host' => getenv('DB_HOST')];
		$payload = json_encode($data);

		$response->getBody()->write($payload);
		return $response->withHeader('Content-Type', 'application/json');
	}
}
