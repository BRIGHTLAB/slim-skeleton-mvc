<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use App\Helpers\JwtHelper;

return function (ContainerBuilder $containerBuilder) {
	$containerBuilder->addDefinitions([
		'logger' => function (ContainerInterface $container) {
			$settings = $container->get('settings');

			$loggerSettings = $settings['logger'];
			$logger = new Logger($loggerSettings['name']);

			$processor = new UidProcessor();
			$logger->pushProcessor($processor);

			$handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
			$logger->pushHandler($handler);

			return $logger;
		},
		'session' => function (ContainerInterface $container) {
			return new \App\Middleware\SessionMiddleware;
		},
		'query' => function (ContainerInterface $container) {
			return new \App\Middleware\QueryMiddleware;
		},
		'view' => function (ContainerInterface $container) {
			$settings = $container->get('settings');
			return Twig::create($settings['view']['template_path'], $settings['view']['twig']);
		},
		'db' => function(ContainerInterface $container){
			$settings = $container->get('settings')['database_source'];
		    $dbpass = $settings['dbpass'];
		    $dbuser = $settings['dbuser'];
		    $dbhost = $settings['dbhost'];
		    $dbname = $settings['dbname'];
		    $charset = $settings['charset'];
		    $dsn = "mysql:host=$dbhost;dbname=$dbname;charset=$charset";
		    return new \PDO($dsn, $dbuser, $dbpass);
		},
		'error_codes' => function(ContainerInterface $container)
		{
			$codes = include  __DIR__.'/error_codes.php';
			return $codes;
		},
		'errorHandler' => function(ContainerInterface $container){
			$error_codes = $container->get('error_codes');
			return new \App\Exceptions\CustomHandler($error_codes);
		},
		's3_helper' => function(ContainerInterface $container){
			$settings = $container->get('settings');
		    $s3_helper = new \App\Helpers\S3Uploader($settings,true);
		    $s3_helper->setSkipDomains($settings['s3_skip_domains']);
		    return $s3_helper;
		},
		'jwt' => function(ContainerInterface $container){
			$settings = $container->get('settings');
		    $jwt_controller = new JwtHelper($settings['jwt'] ?? []);
		    return $jwt_controller;
		},
		// 'sms_helper' => function(ContainerInterface $container){
		// 	$settings = $container->get('settings');
		//     $sms_helper = new \App\Helpers\SMSHelper($settings);
		//     return $sms_helper;
		// },
		// 'google_recaptcha' => function(ContainerInterface $container){
		// 	$settings = $container->get('settings');
		// 	$recaptcha = new \App\Helpers\GoogleRecaptcha([
		// 		'secret' => $settings['recaptcha']['secret'],
		// 		'host' => $settings['recaptcha']['host'],
		// 	]);
		// 	return $recaptcha;
		// }

	]);
};