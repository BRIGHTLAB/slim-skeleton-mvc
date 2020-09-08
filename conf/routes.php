<?php
declare(strict_types=1);

use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
	$app->get('/', 'App\Controller\HomeController:index')->setName('home');

	$app->get('/post/{id}', 'App\Controller\HomeController:viewPost')->setName('post');

	$app->get('/users', 'App\Controller\UserAPIController:index')->setName('fetch_users');
	$app->get('/users1', 'App\Controller\UserAPIController:index')->setName('login');
	$app->get('/users2', 'App\Controller\UserAPIController:index')->setName('logout');
	$app->get('/env', 'App\Controller\UserAPIController:env')->setName('env');
	$app->get('/health-check', 'App\Controller\HealthCheckAPIController:index')->setName('health-check');

};
