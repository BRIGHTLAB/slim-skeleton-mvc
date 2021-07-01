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

			// monolog settings
			'logger' => [
				'name' => 'app',
				'path' =>  getenv('docker') ? 'php://stdout' : $rootPath . '/var/log/app.log',
				'level' => (getenv('APPLICATION_ENV') != 'production') ? Logger::DEBUG : Logger::INFO,
			],

			// database
	        'database_source' => [
	            'dbhost' => empty($_ENV['DB_HOST']) ? getenv('DB_HOST') : $_ENV['DB_HOST'],
	            'dbuser' => empty($_ENV['DB_USER']) ? getenv('DB_USER') : $_ENV['DB_USER'],
	            'dbpass' => empty($_ENV['DB_PASS']) ? getenv('DB_PASS') : $_ENV['DB_PASS'],
	            'dbname' => empty($_ENV['DB_NAME']) ? getenv('DB_NAME') : $_ENV['DB_NAME'],
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
	            'smtp_host' => empty($_ENV['EMAIL_SMTP']) ? getenv('EMAIL_SMTP') : $_ENV['EMAIL_SMTP'],
	            'email_username' => empty($_ENV['EMAIL_USER']) ? getenv('EMAIL_USER') : $_ENV['EMAIL_USER'],
	            'email_password' => empty($_ENV['EMAIL_PASS']) ? getenv('EMAIL_PASS') : $_ENV['EMAIL_PASS'],
	            'email_security' => empty($_ENV['EMAIL_SECURITY']) ? getenv('EMAIL_SECURITY') : $_ENV['EMAIL_SECURITY'],
	            'email_port' => empty($_ENV['EMAIL_PORT']) ? getenv('EMAIL_PORT') : $_ENV['EMAIL_PORT'],
	        ],

            // App android ios settings
	        's3_bucket' => [
	            'access_key' => empty($_ENV['S3_ACCESS_KEY']) ? getenv('S3_ACCESS_KEY') : $_ENV['S3_ACCESS_KEY'],
	            'secret_key' => empty($_ENV['S3_SECRET_KEY']) ? getenv('S3_SECRET_KEY') : $_ENV['S3_SECRET_KEY'],
	            'bucket_name' => empty($_ENV['S3_BUCKET_NAME']) ? getenv('S3_BUCKET_NAME') : $_ENV['S3_BUCKET_NAME'],
	            'bucket_tmp' => empty($_ENV['S3_BUCKET_TMP']) ? getenv('S3_BUCKET_TMP') : $_ENV['S3_BUCKET_TMP'],
	            'bucket_version' => empty($_ENV['S3_BUCKET_VERSION']) ? getenv('S3_BUCKET_VERSION') : $_ENV['S3_BUCKET_VERSION'],
	            'bucket_region' => empty($_ENV['S3_BUCKET_REGION']) ? getenv('S3_BUCKET_REGION') : $_ENV['S3_BUCKET_REGION'],
	        ],

	        // S3 skip domains
	        's3_skip_domains' => [
	           "https://"
			],

			// Default email for smtp
			'smtp_default_email' => "no-reply@diabafrica.com",
			
			// JWT Settings
	        'jwt' => [
	            'secret' => "Secret123!456$$",
	            'tokenKey' => "jwt"
	        ],

			// SMS Settings
	        // 'sms' => [
	        //     'key' => empty($_ENV['SMS_KEY']) ? getenv('SMS_KEY') : $_ENV['SMS_KEY'],
	        //     'from' => empty($_ENV['SMS_FROM']) ? getenv('SMS_FROM') : $_ENV['SMS_FROM']
	        // ],

			// Google Recaptcha
			// 'recaptcha' => [
			// 	'secret' => empty($_ENV['GOOGLE_RECAPTCHA_SECRET_KEY']) ? getenv('GOOGLE_RECAPTCHA_SECRET_KEY') : $_ENV['GOOGLE_RECAPTCHA_SECRET_KEY'],
			// 	'host' => "" // fix in production TO DO
			// ],

			// AWS Key pair id
			'aws_key_pair' => [
				'cf_pair_id' => empty($_ENV['CF_PAIR_ID']) ? getenv('CF_PAIR_ID') : $_ENV['CF_PAIR_ID'],
				'cf_pk_key' => empty($_ENV['CF_PK_KEY']) ? getenv('CF_PK_KEY') : $_ENV['CF_PK_KEY'],
				'cf_domain' => empty($_ENV['CF_DOMAIN']) ? getenv('CF_DOMAIN') : $_ENV['CF_DOMAIN'],
			],

			// Main server domain
			'main_domain' => [
				'url' => empty($_ENV['MAIN_DOMAIN']) ? getenv('MAIN_DOMAIN') : $_ENV['MAIN_DOMAIN'],
			],
		],
	]);

	if (getenv('APPLICATION_ENV') == 'production') { // Should be set to true in production
		$containerBuilder->enableCompilation($rootPath . '/var/cache');
	}
};