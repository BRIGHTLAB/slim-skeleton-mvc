<?php
declare(strict_types=1);

use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use Slim\Routing\RouteCollectorProxy;
Use App\Middleware\CustomJwtMiddleware;
Use App\Controller\UsersAPIController;
Use App\Controller\S3UploaderController;
Use App\Controller\ArticlesAPIController;
Use App\Controller\EducationalVideosAPIController;
Use App\Controller\ModulesAPIController;
Use App\Controller\MeetingReportsAPIController;
Use App\Controller\CongressUpdatesAPIController;
Use App\Controller\WebinarsAPIController;
Use App\Controller\WebinarsAgendaAPIController;
Use App\Controller\ContactAPIController;
Use App\Controller\SpecialityAPIController;
Use App\Controller\HomepageAPIController;
Use App\Controller\SearchAPIController;
Use App\Controller\TechnicalGuidelinesAPIController;
Use App\Controller\ModulesUsersAPIController;
Use App\Controller\UniversitiesRequestAPIController;
Use App\Controller\ReportsController;
Use App\Controller\TestController;
Use App\Controller\VbdiAPIController;
Use App\Helpers\JwtHelper;
use PsrJwt\JwtAuthMiddleware;


use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app) {

	// Redirect any category.* route to website
	// $app->any('/category[/{params:.*}]', function ($request, $response, $args) {
	// 	return $response->withHeader('Location', 'https://www')->withStatus(302);
	// });

	// CORS
	$app->options('/{routes:.+}', function ($request, $response, $args) {
		return $response;
	});
	
	$app->add(function ($request, $handler) {
		$response = $handler->handle($request);
		return $response
				->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
				->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
	});

	// initilize the proper reuqired middlewares
	$container = $app->getContainer();
	$settings  = $container->get('settings');

	// Add Handler to Middleware.
	$CustomJwtMiddleware = new JwtAuthMiddleware(new CustomJwtMiddleware($settings['jwt']));

	$app->get('/', \App\Controller\HomeController::class . ':index')->setName('home');

	// for iOS deeplinking
	//$app->get('/apple-app-site-association', \App\Controller\HomeController::class . ':apsa');

	$app->group('/v1', function(RouteCollectorProxy $group) use ($CustomJwtMiddleware){

		$group->post('/signup', \App\Registration\UserRegistration::class . ':signup')->setName('api_signup_user');
		$group->post('/login', \App\Registration\UserRegistration::class . ':login')->setName('api_login_user');
		//$group->get('/change_password', \App\Registration\UserRegistration::class . ':changePassword');
    	$group->get('/reset_password', \App\Registration\UserRegistration::class . ':forgetPassword');
    	$group->post('/verify_code', \App\Registration\UserRegistration::class . ':verifyCode');
	    $group->get('/reset-password/{reset_token}', \App\Registration\UserRegistration::class . ':resetPassView')->setName('api_resetpassview_user');
    	$group->post('/reset-password-api', \App\Registration\UserRegistration::class . ':resetPassAPI')->setName('api_resetpass_user');
		$group->get('/verification/{activate_token}&{lang}', \App\Registration\UserRegistration::class . ':verify')->setName('api_verify_user');
		//$group->post('/send_sms', \App\Registration\UserRegistration::class . ':sendSMSCode');
		//$group->post('/activate_sms', \App\Registration\UserRegistration::class . ':activateSMSCode');
		$group->post('/refresh_token', \App\Registration\UserRegistration::class . ':regenerateToken');
		//$group->get('/reset-email/{reset_token}', \App\Registration\UserRegistration::class . ':resetEmailView')->setName('reset_email_view');
		//$group->get('/reset-email-api/{reset_token}', \App\Registration\UserRegistration::class . ':validateEmail')->setName('validate_email');
		//$group->post('/reset_password_sms', \App\Registration\UserRegistration::class . ':resetPasswordBySMS');
		//$group->post('/validate_password_sms', \App\Registration\UserRegistration::class . ':validateResetPasswordBySMS');

		// Contact us / Message us
		$group->post('/contact_us', ContactAPIController::class . ':contactUs')->setName('message_us');

		// Test endpoints
		$group->post('/test/email', TestController::class . ':testEmail');


		// G E T -  No Authenticaion
		$group->get('/specialities', SpecialityAPIController::class . ':fetchAll');
		$group->get('/homepage',  HomepageAPIController::class . ':fetchHomepage')->setName('fetch_homepage');
		$group->get('/articles', ArticlesAPIController::class . ':fetchAll')->setName("fetch_articles");
		$group->get('/educational_videos', EducationalVideosAPIController::class . ':fetchAll')->setName("fetch_educational_videos");
		$group->get('/congress_updates', CongressUpdatesAPIController::class . ':fetchAll')->setName("fetch_congress_updates");
		$group->get('/reports', MeetingReportsAPIController::class . ':fetchAll');
		

		// REPORTS
		$group->group('/reports', function(RouteCollectorProxy $report_group) {
			$report_group->get('/users', ReportsController::class . ':downloadUsers');
			$report_group->get('/elearning_requests', ReportsController::class . ':downloadUsersRequestedAccess');
		});

		// User Group - Authenticated
		$group->group('/users', function(RouteCollectorProxy $user_group) {
			
			// G E T
			$user_group->get('/{users_id}', UsersAPIController::class . ':fetchUserProfile');
			$user_group->get('/{users_id}/articles/{articles_id}', ArticlesAPIController::class . ':fetch');
			$user_group->get('/{users_id}/educational_videos/{videos_id}', EducationalVideosAPIController::class . ':fetch')->setName("fetch_educational_video");
			$user_group->get('/{users_id}/modules/{modules_type}', ModulesAPIController::class . ':fetchAll')->setName("fetch_modules");
			$user_group->get('/{users_id}/modules/{modules_id}/objectives', ModulesAPIController::class . ':fetchObjectives');
			$user_group->get('/{users_id}/congress_updates/{congress_id}', CongressUpdatesAPIController::class . ':fetch');
			$user_group->get('/{users_id}/webinars', WebinarsAPIController::class . ':fetchAll')->setName("fetch_webinars");
			$user_group->get('/{users_id}/webinars/{webinars_id}/agenda',  WebinarsAgendaAPIController::class . ':fetch')->setName('fetch_webinars_agenda');
			$user_group->get('/{users_id}/search',  SearchAPIController::class . ':search');
			$user_group->get('/{users_id}/technical_guidelines',  TechnicalGuidelinesAPIController::class . ':fetchAll')->setName('fetch_technical_guidelines');
			$user_group->get('/{users_id}/vbdi',  VbdiAPIController::class . ':fetch')->setName('fetch_vbdi');

			// P O S T
			$user_group->post('/{users_id}', \App\Registration\UserRegistration::class . ':updateUser');
			$user_group->post('/{users_id}/send_email', \App\Registration\UserRegistration::class . ':sendEmail');
			$user_group->post('/{users_id}/upload_image', UsersAPIController::class . ':uploadImage');
			$user_group->post('/{users_id}/reset-email', \App\Registration\UserRegistration::class . ':resetEmail');
			$user_group->post('/{users_id}/webinars/{webinars_id}/attend',  WebinarsAPIController::class . ':attendWebinar');
			$user_group->post('/{users_id}/modules/{modules_id}/access',  ModulesUsersAPIController::class . ':insert');
			$user_group->post('/{users_id}/vbdi/{vbdi_id}',  VbdiAPIController::class . ':insert');

			// D E L E T E 
		
		})->add($CustomJwtMiddleware);
	});
};
