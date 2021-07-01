<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Mappers\CategoriesMapper;
use App\Exceptions\CustomException;
use App\Helpers\S3Uploader;

final class S3UploaderController extends BaseController
{

	public function createSignUrl (Request $request, Response $response, $args) {

		$query_params = $request->getQueryParams();
        if(!isset($query_params['objectName']))
        {
			throw new CustomException(1001,'Missing Required Fields','objectName',400);
            // $exception = new CustomException($request);
			// $exception->setExceptionFields(500,"objectName is missing","Slim Application Error","");
			// throw $exception;
        }

		$name = $query_params['objectName'];
		$ext = explode('.', $name);

		// TODO CHANGE S3 BUCKET SETTINGS TO NOVO AFRICA
		// hardocded url
		$config = [
			's3_bucket' => [
				'bucket_tmp' => "tmp.apotex.academy",
				'bucket_name' => "static.apotex.academy",
			]
		];

	    $s3_uploader = new S3Uploader($config,true);
        $results = $s3_uploader->createSignUrl($ext[count($ext) - 1]);

        $payload = json_encode($results, JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  	}
}
