<?php

namespace App\Exceptions;
use Crell\ApiProblem\ApiProblem;

class CustomHandler {

  protected $error_codes;

  public function __construct (Array $error_codes) {
      $this->error_codes = $error_codes;
  }

  public function __invoke($request, $response, $exception) {

    // $status_code = 500;

    // $problem = new ApiProblem();
    // $problem['code'] = $exception->getCode();
    // $problem
    //   ->setTitle( $this->error_codes[$exception->getCode()]["title"]);

    //   if(method_exists($exception, "getDetail")){
    //     $problem->setDetail($exception->getDetail());
    //     if(!is_array($exception->getDetail()))
    //       $problem->setDetail( trim($exception->getMessage(),'"'));

    //     $status_code = $exception->getStatusCode();
    //   }else{
    //     // the following is only for the default excpetion
    //     $problem->setDetail( (string) $exception->getMessage());
    //   }

    //   // ->setInstance("http://example.net/account/12345/msgs/abc");

    //   return $response->withStatus($status_code)
    //                   ->withHeader('Content-Type', 'text/json')
    //                   ->write( $problem->asJson() );


      //$error_codes = $container->get('error_codes');

      $exception->setDescription($this->error_codes[$exception->getCode()]);

      $payload = [
      'title' => $exception->getTitle(),
      'message' => $exception->getMessage(), 
      'description' => $exception->getDescription(),
      'code' => $exception->getCode()];

      $response = $app->getResponseFactory()->createResponse();
      $response->getBody()->write(
          json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
      return $response->withHeader('Access-Control-Allow-Origin', '*')
                      ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                      ->withHeader('Content-Type', 'application/json')
                      ->withStatus($exception->getStatusCode());
   }

}

