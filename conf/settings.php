<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {
	$rootPath = realpath(__DIR__ . '/..');

	// Global Settings Object
	$containerBuilder->addDefinitions([
		'settings' => [
			// Base path
			'base_path' => '',

			// Is debug mode
			'debug' => (getenv('APPLICATION_ENV') != 'production'),

			// 'Temprorary directory
			'temporary_path' => $rootPath . '/var/tmp',

			// Route cache
			'route_cache' => $rootPath . '/var/routes.cache',

			// View settings
			'view' => [
				'template_path' => $rootPath . '/template',
				'twig' => [
					'cache' =>false,//$rootPath . '/var/cache/twig',
					'debug' => (getenv('APPLICATION_ENV') != 'production'),
					'auto_reload' => true,
				],
			],

			'database_source' => [
	    	'dbhost' => getenv('DB_HOST'),
	    	'dbuser' => "",//getenv('DB_USER'),
	    	'dbpass' => "",//getenv('DB_PASS'),
	    	'dbname' => "",//getenv('DB_NAME'),
	    	'charset' => 'utf8',
      ],

			// monolog settings
			'logger' => [
				'name' => 'app',
				'path' =>  getenv('docker') ? 'php://stdout' : $rootPath . '/var/log/app.log',
				'level' => (getenv('APPLICATION_ENV') != 'production') ? Logger::DEBUG : Logger::INFO,
			],

			// database
	        'database_source' => [
	            'dbhost' => $_ENV['DB_HOST'],
	            'dbuser' => $_ENV['DB_USER'],
	            'dbpass' => $_ENV['DB_PASS'],
	            'dbname' => $_ENV['DB_NAME'],
	            'charset' => 'utf8',
	        ],

        	// Renderer settings
	        'renderer' => [
	            'template_path' => __DIR__ . '/../templates/',
	            'cache_path' => __DIR__ . '/../cache/',
	        ],

	        // email verification
	        'signup_verification' => [
	            'html_template' => 'verification.twig',
	            'smtp_host' => $_ENV['EMAIL_SMTP'],
	            'email_username' => $_ENV['EMAIL_USER'],
	            'email_password' => $_ENV['EMAIL_PASS'],
	            'email_security' => $_ENV['EMAIL_SECURITY'],
	            'email_port' => $_ENV['EMAIL_PORT'],
	        ],

            // App android ios settings
	        's3_bucket' => [
	            'access_key' => getenv('S3_ACCESS_KEY'),
	            'secret_key' => getenv('S3_SECRET_KEY'),
	            'bucket_name' => getenv('S3_BUCKET_NAME'),
	            'bucket_tmp' => getenv('S3_BUCKET_TMP'),
	            'bucket_version' => getenv('S3_BUCKET_VERSION'),
	            'bucket_region' => getenv('S3_BUCKET_REGION'),
	        ],

	        // S3 skip domains
	        's3_skip_domains' => [
	           "https://"
	        ],

		],
	]);

	if (getenv('APPLICATION_ENV') == 'production') { // Should be set to true in production
		$containerBuilder->enableCompilation($rootPath . '/var/cache');
	}
};