<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

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
		'view' => function (ContainerInterface $container) {
			$settings = $container->get('settings');
			return Twig::create($settings['view']['template_path'], $settings['view']['twig']);
		},
		'database_adapter' => function (ContainerInterface $container) {
			$settings = $container->get('settings')['database_source'];

			$dbpass = $settings['dbpass'];
    	$dbuser = $settings['dbuser'];
    	$dbhost = $settings['dbhost'];
    	$dbname = $settings['dbname'];
    	$charset = $settings['charset'];
    	$dsn = "mysql:host=$dbhost;dbname=$dbname;charset=$charset";

    	// return new \PDO($dsn, $dbuser, $dbpass);
		}
	]);
};
