<?php

namespace App\Registration;

use Interop\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;


use App\Helpers\PDOConditionMapper,
    App\Helpers\RequiredFields;

use App\Models\UserModel;
use App\Exceptions\SSCCException;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

use App\Mappers\UserMapper;
use App\Mappers\UsersInterestsMapper;

class UserRegistration
{

    protected $pdo;
    protected $logger;
    protected $settings;
    protected $view;
    protected $router;
    protected $mapper;
    protected $full_url;

    public function __construct(ContainerInterface $container) {
        $this->pdo = $container->get("db");
        $this->logger = $container->get("logger");
        $this->settings = $container->get("settings");
        $this->view = $container->get("view");
        //$this->router = $container->get("router");
        $this->router = $container->get(\Slim\Interfaces\RouteParserInterface::class);

        // $this->full_url = $this->settings['domain'];
        // we can also do this
        $this->full_url = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'];

        $this->mapper = new PDOMapper($this->pdo);
        $this->mapper ->setTableName("users")
                ->setResetTokenColumn("reset_token")
                ->setActivateTokenColumn("activate_token")
                ->setColumns([
                    "username",
                    "salt_hash",
                    "password",
                    "email",
                ]);
    }

    public function login(Request $request, Response $response, $args){
        $parsed_body = $request->getParsedBody();

        $required_fields = [
            "username", "password"
        ];

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        $username = $parsed_body['username'];
        $password = $parsed_body['password'];

        // get the token back
        $result = $this->mapper->login( $username, $password);

        if ($result) {
            $token = $this->generateString(50);
            // insert a new token in the database
            $this->mapper->insertToken($result['id'], $token);
            return $response->withJson(['token' => $token,'id'=>$result['id']], 200);
        }

        throw new SSCCException(2002, "Invalid Credentials", 400);
        //return $response->withStatus(400);
    }
    
    public function profLogin(Request $request, Response $response, $args){
        $parsed_body = $request->getParsedBody();

        $required_fields = [
            "username", "password"
        ];

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        $username = $parsed_body['username'];
        $password = $parsed_body['password'];

        // get the token back
        $result = $this->mapper->profLogin( $username, $password);

        if ($result) {
            $token = $this->generateString(50);
            // insert a new token in the database
            $this->mapper->insertToken($result['id'], $token);
            return $response->withJson(['token' => $token,'id'=>$result['id']], 200);
        }

        throw new SSCCException(2002, "Invalid Credentials", 400);
    }

    public function signup (Request $request, Response $response, $args) {

        $parsed_body = $request->getParsedBody();
        $required_fields = [
            "email",
            "first_name",
            "last_name",
            "username",
            "password",
            "country",
            "city",
            "speciality",
            "profession",
            "organization"
        ];

        // parse the field and check if one of them is empty
        $parsed_body = $this->fixEmptyUserInput($parsed_body);

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        // Check if email already exists
        $user_mapper = new UserMapper($this->pdo);
        $user_mapper_results = $user_mapper->fetchUserByEmail($parsed_body["email"]);
        // If mapper returned results, it means that the email is already in use
        if($user_mapper_results)
        {
            throw new SSCCException(2004, "User already exists", 400);
        }

        try
        {
            /*
              Insert the converted model into the database
            *///
            $token = $this->generateString(50);
            $salt_hash = $this->generateString(20);

            $email = $parsed_body['email'];
            $name = $parsed_body["first_name"] ? $parsed_body["first_name"] : "";

            // create a password if not supplied
            if(isset($parsed_body['password'])) {
                $parsed_body['salt_hash'] = $salt_hash;
            }

            // Check if passwords match
            if(trim($parsed_body['confirm_password']) != trim($parsed_body['password']) || empty(trim($parsed_body['confirm_password'])))
                throw new SSCCException(2005, "Password does not match.", 400);

            // Insert user in the database
            $id = $this->mapper->insert($parsed_body, $token);
            // Insert users interests in the database
            $users_interests_mapper = new UsersInterestsMapper($this->pdo);
            $users_interests_mapper->insert($id, $parsed_body["interests"]);


            // send an email to the user for the registartion process
            if (!is_null($email)) {
                $this->sendVerificationEmail($email, $token, $name);
            }

            // everything went ok
            return $response->withStatus(201);

        }catch(\Exception $e){
        throw new SSCCException(2003, "Could not register.", 400);
      }

    }
 
    public function forgetPassword (Request $request, Response $response, $args) {

        $query_params = $request->getQueryParams();
        $email = $query_params['email']; // email
        $token = $this->generateString(20);

        if(!$this->mapper->forgetPassword($email, $token))
            return $response->withStatus(400);

        // we need to send an email_security
        $this->sendRequestToResetPass($email, $token);

        return $response->withStatus(200);
    }

    public function resetPassView (Request $request, Response $response, $args) {
        $token = $args['reset_token'];
        $full_name = $this->mapper->getUserBasedOnResetToken($token);
        
        $link = $this->full_url . $this->router->urlFor('api_resetpass_user', ["reset_token" => $token]);
        $this->view->render($response, 'signup_verification/reset_password_view.twig', [
            "full_name" => trim($full_name['full_name']),
            "url" => $link
        ]);
        return $response;
    }


    // -- MADE FOR SSCC ----
    public function changePassword (Request $request, Response $response, $args) {

        $query_params = $request->getQueryParams();
        $user_id = (int) $query_params['user_id'];
        $new_pass = $query_params['password'];
        $retype_pass = $query_params['repassword'];
        $old_password = $query_params['old_password'];

        // Check if new password retype match
        if($new_pass != $retype_pass)
            throw new SSCCException(2003, "Passwords do not match.", 400);


        // Fake reset token
        $token = $this->generateString(50);
        // Create salt hash for password
        $salt_hash = $this->generateString(20);

        // Insert the fake reset token in the reset_token column
        $this->mapper->resetTokenByUserId($user_id,$token);

        // Call reset password
        if (!$this->mapper->resetPassword($new_pass, $token, $salt_hash)) {
            return $response->withStatus(401);
        }

        return $response->withStatus(202);
    }

    // -- MADE FOR SSCC ----
    public function sendEmail (Request $request, Response $response, $args) {

        $parsed_body = $request->getParsedBody();
        $name = $parsed_body["name"]; // from who
        $subject = $parsed_body["subject"]; // the subject
        $email = $parsed_body["email"]; // from who - email
        $message = $parsed_body["message"]; // what is the msg

        $mail = new PHPMailer(true);
        try {
            //Server settings
            $mail->SMTPDebug = 0;                                         // Enable verbose debug output
            $mail->isSMTP();                                              // Set mailer to use SMTP
            $mail->Host       = $this->settings["signup_verification"]["smtp_host"];  // Specify main and backup SMTP servers
            $mail->SMTPAuth   = true;                                     // Enable SMTP authentication
            $mail->Username   = $this->settings["signup_verification"]["email_username"];  // SMTP username
            $mail->Password   = $this->settings["signup_verification"]["email_password"];  // SMTP password
            $mail->SMTPSecure = $this->settings["signup_verification"]["email_security"];  // Enable TLS encryption, `ssl` also accepted
            $mail->Port       = $this->settings["signup_verification"]["email_port"];  // TCP port to connect to

            // Sebd to multiple Recipients
            //$address = array('cmerheb@bright-lab.com','cdaccache@bright-lab.com');
            $address = array('kfarhbab@sscc.edu.lb', 'tony.mouarkech@kfarhbab.sscc.edu.lb', 'eliane.barhouch@kfarhbab.sscc.edu.lb');
            // while (list ($key, $val) = each ($address)) 
            // {
            //     $mail->AddAddress($val);
            // }

            foreach ($address as $key => $val) {
                $mail->AddAddress($val);
            }

            $mail->setFrom($email, $name);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            //$mail->AltBody = 'Thank you for signing up. Please click on the following link '.$link.' to activate your account. if you can\'t click on the link, please copy and paste it in your browser.';

            $mail->send();
            // echo 'Message has been sent';
        } catch (Exception $e) {
            // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        return true;

        return $response->withStatus(200);
    }


    public function resetPassAPI (Request $request, Response $response, $args) {

        $query_params = $request->getQueryParams();
        $new_pass = $query_params['new_pass'];
        $retype_pass = $query_params['retype_old_pass'];

        $token = $args['reset_token'];
        $salt_hash = $this->generateString(20);

        if (!$this->mapper->resetPassword($new_pass, $token, $salt_hash)) {
            return $response->withStatus(401);
        }

        // code here
        return $response->withStatus(200);
    }

    public function verify (Request $request, Response $response, $args) {

        $token = $args['activate_token'];
        if ($this->mapper->verify($token) && $token != "1") {
            $this->view->render($response, 'signup_verification/activation_success.twig', [
                "name" => "User"
            ]);
        }else{
            $this->view->render($response, 'signup_verification/activation_failure.twig', [

            ]);
        }

        return $response;
    }

    // custom function
    protected function validateRequiredFields (Array $user_input, Array $required_fields) {

        // everything is ok just validate the logic
        $required = array_diff($required_fields, $user_input);

        if (count($required) != 0 ) {
            throw new SSCCException(1001, array_values($required), 400);
        }
    }

    protected function fixEmptyUserInput (Array $user_input) {

        // check the empty fileds
        $filtered_user_input = [];
        foreach($user_input as $key => $row){
            if(!empty(trim($row)))
                $filtered_user_input[$key] = $row;
        }
        return $filtered_user_input;
    }

    public function show_env (Request $request, Response $response, $args) {

        $host = $this->settings["signup_verification"]["smtp_host"];
        $username = $this->settings["signup_verification"]["email_username"];
        $password = $this->settings["signup_verification"]["email_password"];
        $email_security = $this->settings["signup_verification"]["email_security"];
        $port = $this->settings["signup_verification"]["email_port"];

        $data = [];
        $data["host"] = $host;
        $data["username"] = $username;
        $data["email_security"] = $email_security;
        $data["password"] = $password;
        $data["post"] = $port;

        return $response->withJson($data,200);
    }

    protected function sendRequestToResetPass ($email, $token) {

        // Instantiation and passing `true` enables exceptions
        $mail = new PHPMailer(true);
        try {
            //Server settings
            $mail->SMTPDebug = 0;                                         // Enable verbose debug output
            $mail->isSMTP();                                              // Set mailer to use SMTP
            $mail->Host       = $this->settings["signup_verification"]["smtp_host"];  // Specify main and backup SMTP servers
            $mail->SMTPAuth   = true;                                     // Enable SMTP authentication
            $mail->Username   = $this->settings["signup_verification"]["email_username"];  // SMTP username
            $mail->Password   = $this->settings["signup_verification"]["email_password"];  // SMTP password
            $mail->SMTPSecure = $this->settings["signup_verification"]["email_security"];  // Enable TLS encryption, `ssl` also accepted
            $mail->Port       = $this->settings["signup_verification"]["email_port"];  // TCP port to connect to

            //Recipients
            $mail->setFrom($this->settings["signup_verification"]["email_username"], 'no-reply');
            $mail->addAddress($email);     // Add a recipient

            $link = $this->full_url . $this->router->urlFor('api_resetpassview_user', ["reset_token" => $token]);
            $body = $this->view->fetch("signup_verification/reset_password_email.twig", ["link"=> $link]);

            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = 'Request Password Reset';
            $mail->Body    = $body;
            $mail->AltBody = 'You have requested to reset your password. Please click on this link '.$link.' or copy and paste it in your browser.';

            $mail->send();
            // echo 'Message has been sent';
        } catch (Exception $e) {
            // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        return true;
    }

    protected function sendVerificationEmail ($email, $token, $name) {

        // Instantiation and passing `true` enables exceptions
        $mail = new PHPMailer(true);
        try {
            //Server settings
            $mail->SMTPDebug = 0;                                         // Enable verbose debug output
            $mail->isSMTP();                                              // Set mailer to use SMTP
            $mail->Host       = $this->settings["signup_verification"]["smtp_host"];  // Specify main and backup SMTP servers
            $mail->SMTPAuth   = true;                                     // Enable SMTP authentication
            $mail->Username   = $this->settings["signup_verification"]["email_username"];  // SMTP username
            $mail->Password   = $this->settings["signup_verification"]["email_password"];  // SMTP password
            $mail->SMTPSecure = $this->settings["signup_verification"]["email_security"];  // Enable TLS encryption, `ssl` also accepted
            $mail->Port       = $this->settings["signup_verification"]["email_port"];  // TCP port to connect to

            //Recipients
            $mail->setFrom($this->settings["signup_verification"]["email_username"], 'no-reply');
            $mail->addAddress($email);     // Add a recipient

            $link = $this->full_url . $this->router->urlFor('api_verify_user', ["activate_token" => $token]);
            $body = $this->view->fetch("signup_verification/activation_verification_email.twig", ["link"=> $link, "name"=> $name]);

            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = 'Email Varification';
            $mail->Body    = $body;
            $mail->AltBody = 'Thank you for signing up. Please click on the following link '.$link.' to activate your account. if you can\'t click on the link, please copy and paste it in your browser.';

            $mail->send();
            // echo 'Message has been sent';
        } catch (Exception $e) {
            // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        return true;
    }

    protected function generateString ($length) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ.=_';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }

        return $randomString;
    }


    // --------- A D M I N    A C T I O N S ----------
    //------------------------------------------------

    // Fetch list of users - paginated
    public function fetchUsers (Request $request, Response $response, $args) {

        // Get the pagination condition mapper
        $condition_mapper = $request->getAttribute('QUERY_PAGINATION');
        $condition_mapper->where(' `is_active` = ? ', [1]);

        // Prepare user mapper
        $mapper = new UserMapper($this->pdo);
        $results = $mapper->fetch($condition_mapper);
        $count = $mapper->fetchTotalCount()['count']; // Get the count for the pagination
      
        $url_route = $this->router->urlFor('fetch_users', ['admin_id' => $args['admin_id']]);
        $paginated_response = \App\Helpers\PaginationHelper::WrapPrevNextPages($url_route ,$request,$results,$count);

        return $response->withJson($paginated_response, 200);
    }

    // UPDATE USER DETAILS
    public function updateUser (Request $request, Response $response, $args) {

        $user_id = (int) $args['users_id'];
        $parsed_body = $request->getParsedBody();

        // Fetch the user
        $user_mapper = new UserMapper($this->pdo);
        $user = $user_mapper->fetchUser($user_id);

        // Check parsed body content, and apply it to the user
        $user_model = new UserModel($user);
        $user_model->hydrate($parsed_body);

        try {
        $condition_mapper = new PDOConditionMapper();
        $condition_mapper->where(' `id` = ?', [$user_id]);
        $condition_mapper->update("`username` = ?, `is_active` = ?", [$user_model->getUsername(), $user_model->getIsActive() ]);

        // Update user
        $user_mapper->update($condition_mapper);

        }catch(\Exception $e){
            return $response->withStatus(400);
        }

        // code here
        return $response->withStatus(200);
    }

    // UPDATE USER EMAIL AND PASSWORD
    public function updateCredentials (Request $request, Response $response, $args) {

        $user_id = (int) $args['users_id'];
        $parsed_body = $request->getParsedBody();
       
        if(isset($parsed_body['password']))
        {
            $password = $parsed_body['password'];
            // Fake reset token
            $token = $this->generateString(50);
            // Create salt hash for password
            $salt_hash = $this->generateString(20);

            // Insert the fake reset token in the reset_token column
            $this->mapper->resetTokenByUserId($user_id,$token);

            // Call reset password
            if (!$this->mapper->resetPassword($password, $token, $salt_hash))
                return $response->withStatus(401);
        }
        
        if(isset($parsed_body['email']))
        {   
            $email = $parsed_body['email'];
            // Call reset email
            $user_model = new UserModel();
            $user_model->setId($user_id);
            $user_model->setEmail($email);
            $user_mapper = new UserMapper($this->pdo);
            $user_mapper->resetEmail($user_model); 
        }
        
        // code here
        return $response->withStatus(200);
    }


    // DE-ACTIVATE USER
    public function deleteUser (Request $request, Response $response, $args) {

        $user_model = new UserModel();
        $user_model->setId($args['users_id']);

        $mapper = new UserMapper($this->pdo);
        $success = $mapper->delete($user_model);

        if(!$success)
            return $response->withStatus(400);

        return $response->withStatus(200);
    }

    //------------------------------------------------
    //------------------------------------------------

}
