<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class HomeController extends BaseController
{
	public function index(Request $request, Response $response, array $args = []): Response
	{
		$this->logger->info("Home page action dispatched");
		return $this->render($request, $response, 'index.twig');
	}

	public function viewPost(Request $request, Response $response, array $args = []): Response
	{
		$this->logger->info("View post using Doctrine with Slim 4");
		$post = [];
		return $this->render($request, $response, 'post.twig', ['post' => $post]);
	}

	public function getEnv(Request $request, Response $response, array $args = []): Response
	{
		print_r($this->settings);
		exit;
		print_r(getenv());
		print_r($_ENV);
		exit;
		$this->logger->info("View post using Doctrine with Slim 4");
		$post = [];
		return $this->render($request, $response, 'post.twig', ['post' => $post]);
	}

	// testing only
	public function getRecaptcha(Request $request, Response $response, array $args = []): Response
	{
        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();
		$recaptcha_value = $parsed_body['recaptcha'] ?? null;

		print_r($parsed_body);
		
		if($this->recaptcha->verify($recaptcha_value)){
			echo "yes";
		}else{
			print_r($this->recaptcha->getError());
		}
		exit;

	}

	// for iOS deeplinking
	public function apsa(Request $request, Response $response, array $args = []): Response
	{
		$obj = [
			"activitycontinuation" => [
				"apps" => [
					"VMWM5LE3PX.com.brightlab.apotex"
				]
			],
			"webcredentials" => [
				"apps"=> [
					"VMWM5LE3PX.com.brightlab.apotex"
				]
			],
			"applinks"=> [
				"apps"=> [],
				"details"=> [
					[
						"appIDs"=> ["VMWM5LE3PX.com.brightlab.apotex"],
						"components" => [
							[
								"/" => "/category/*",
								"comment" => "Matches any URL whose path starts with /category" 
							],
							[
								"/" => "/v1/*",
								"exclude" => true,
								"comment" => "Matches any URL whose path starts with /v1 and exclude it"	
							]
						],
					],
					[
						"appID"=> "VMWM5LE3PX.com.brightlab.apotex",
						"paths"=> [
							"/category/*"
						]
					]
				]
			]
		];
		$payload = json_encode($obj, JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
	}
}
