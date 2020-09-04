<?php
namespace App\Controller;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Exceptions\CustomException;

abstract class BaseController
{
	protected $view;
	protected $logger;
	protected $database_adapter;
	protected $router;

	public function __construct(ContainerInterface $container)
	{
		$this->view = $container->get('view');
		$this->logger = $container->get('logger');
		$this->database_adapter = $container->get("db");
		$this->router = $container->get(\Slim\Interfaces\RouteParserInterface::class);
	}

	protected function render(Request $request, Response $response, string $template, array $params = []): Response
	{
		$params['uinfo'] = $request->getAttribute('uinfo');

		return $this->view->render($response, $template, $params);
	}

    protected function validateRequiredFields ($model_class_name, Array $user_input, Array $override_fields = null) {

      if (!empty($model_class_name) ) {
        // create a dynamic model and check it if it implments the ModelValidationInterface
        $model = new $model_class_name();
        if (!($model instanceof ModelValidationInterface))
          throw new CustomException(1002, "Model $model_class_name needs to implements 'ModelValidationInterface'", 500);
      }
      
      // everything is ok just validate the logic
      $required_fields = $override_fields ?? $model->getRequiredProperties();
      $required = array_diff($required_fields, $user_input);

      if (count($required) != 0 ){
          throw new CustomException(1001, $required, 400);
      }

    }
}
