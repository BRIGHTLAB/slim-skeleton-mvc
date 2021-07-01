<?php

namespace App\Registration;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Respect\Validation\Rules;

use App\Helpers\PDOConditionMapper,
    App\Helpers\RequiredFields;

use App\Models\UserModel;
use App\Exceptions\CustomException;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

use App\Mappers\UserMapper;

use App\Helpers\JwtHelper;

class UserRegistration
{

    protected $pdo;
    protected $logger;
    protected $settings;
    protected $view;
    protected $router;
    protected $mapper;
    protected $full_url;
    protected $jwt;
    protected $recaptcha;

    public function __construct(ContainerInterface $container) {
        $this->pdo = $container->get("db");
        $this->logger = $container->get("logger");
        $this->settings = $container->get("settings");
        $this->jwt = $container->get("jwt");
        $this->view = $container->get("view");
        //$this->sms_helper = $container->get("sms_helper");
        //$this->recaptcha = $container->get("google_recaptcha");
        
        //$this->router = $container->get("router");
        $this->router = $container->get(\Slim\Interfaces\RouteParserInterface::class);

        // $this->full_url = $this->settings['domain'];
        // we can also do this
        $this->full_url = 'https://'.$_SERVER['SERVER_NAME'];

        $this->mapper = new PDOMapper($this->pdo);
        $this->mapper ->setTableName("users")
                ->setResetTokenColumn("reset_token")
                ->setActivateTokenColumn("activate_token")
                ->setColumns([
                    "first_name",
                    "last_name",
                    "email",
                    "salt_hash",
                    "enc_password",
                    "speciality_id",
                    "country"
                ]);
    }

    public function login(Request $request, Response $response, $args){

        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();

        $required_fields = [
            "username", "password", "recaptcha"
        ];


        $skip_recaptcha = isset($parsed_body['skip_recaptcha123']) ?? false;
        // for dev only, will be removed later on
        if($skip_recaptcha)
            unset($required_fields[2]);

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        $username = $parsed_body['username'];
        $password = $parsed_body['password'];

        if(!$skip_recaptcha){
            $recaptcha = $parsed_body['recaptcha'];
            // check for automated attacks
            if(!$this->recaptcha->verify($recaptcha)){
                print_r($this->recaptcha->getError());
                throw new CustomException(2012,'Invalid Recaptcha','The recaptcha validation is incorrect', 400);
            }
        }
        // get the token back
        $result = $this->mapper->login( $username, $password);

        if ($result) {

            // Generate JWT tokens
            $token = $this->jwt->generateToken(["uid" => $result['id']]);
            $refresh_token = $this->jwt->generateRefreshToken(["uid" => $result['id']]);

            $payload = json_encode(['id'=>$result['id'] ,'token' => $token, 'refresh_token' => $refresh_token], JSON_PRETTY_PRINT);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }

        throw new CustomException(2005,'Invalid Credentials','The credentials are incorrect',400);
    }
    
    public function signup (Request $request, Response $response, $args) {

        // Get language set by header request
        $lang = \App\Helpers\HeaderLanguageHelper::match($request);
        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();

        $required_fields = [
            "first_name","last_name","email", "speciality_id","country"
        ];

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        // Validate sign up body fields (email, etc..)
        $this->validateSignupFields($parsed_body);

        // fix empty parsedbody, by removing the email from the parsed body
        if(isset($parsed_body['email']) && empty($parsed_body['email'])){
            unset($parsed_body["email"]);
        }
        // fix empty parsedbody, by removing the referral_code from the parsed body
        if(isset($parsed_body['referral_code']) && empty($parsed_body['referral_code'])){
            unset($parsed_body["referral_code"]);
        }

        // Check if email already exists
        $user_mapper = new UserMapper($this->pdo);
        $condition_mapper = new PDOConditionMapper();
        $condition_mapper->where(" `email` = ? AND `removed` = ? AND `activate_token` = ? ", [ $parsed_body['email'], 0, 1 ]);
        $user_mapper_results = $user_mapper->fetch($condition_mapper);

        // If mapper returned results, it means that the email is already in use
        if($user_mapper_results)
        {
            throw new CustomException(2004,'Already exists','Email already exist',400);
        }

        /*
            Insert the converted model into the database
        *///
        $token = $this->generateString(50);
        //$token = 1; // Automatically enable the email
        $salt_hash = $this->generateString(20);
        //$default_user_type = "normal";

        $email = $parsed_body['email'];
        $name = $parsed_body["first_name"] ? $parsed_body["first_name"] : "";

        // create a password if not supplied
        if(isset($parsed_body['password'])) {
            $parsed_body['salt_hash'] = $salt_hash;
        }

        // Check if passwords match
        if(trim($parsed_body['confirm_password']) != trim($parsed_body['password']) || empty(trim($parsed_body['confirm_password'])))
            throw new CustomException(2003,'Password does not match','Password does not match',400);

        try {

            // // remove extra fields
            // unset($parsed_body['skip_recaptcha123']);
            // unset($parsed_body['recaptcha']);

            // Insert user in the database
            $id = $this->mapper->insert($parsed_body, $token);

            // Grant access for specific users to have immediate access for Elearning
            

            //send an email to the user for the registartion process
            if (!is_null($email)) {
                $this->sendVerificationEmail($email, $token, $name, $lang);
            }

            // Send SMS code to the user
            // Send SMS ($to, $message)
            //$this->sms_helper->sendSMS($parsed_body['phone_number'] , "Verification code: " . $fourRandomDigit);

            // everything went ok
            return $response->withStatus(201);

        }catch(\Exception $e){

            throw new CustomException(2002,'Registration Failed','Could not register',400);
        }

    }

    // Validate login parsed body fields
    protected function validateSignupFields (Array $user_input) {

        $errors = [];
        
        // Validators
        if(isset($user_input['email']) && !empty($user_input['email'])){
            $emailValidator = v::email()->validate($user_input['email']); // true
            if(!$emailValidator)
                $errors[] = "email";
        }

        // // Remove space characters and - from phone number and validate
        // $phone_number_trimmed = str_replace(" ", '', $user_input['phone_number'] );
        // $phone_number_trimmed = str_replace("-", '', $phone_number_trimmed );
        // $phoneNumberValidator = v::regex('/^[+20(1]*[0,1,2,5]{2}[)][0-9]{7}/')->validate($phone_number_trimmed); // true
        // if(!$phoneNumberValidator)
        //     $errors[] = "phone_number";

        // // Validate date of birth
        // $dobRules = new Rules\AllOf(
        //     new Rules\DateTime(),
        //     new Rules\MinAge(18, 'Y-m-d'),
        //     new Rules\MaxAge(100, 'Y-m-d')
        // );
        // $dobValidator = new Rules\Key('custom-dob', $dobRules);
        // $tmp = $dobValidator->validate(['custom-dob' => $user_input['dob']]); // true
        // if(!$tmp)
        //     $errors[] = "dob";

        // // Ensure that password is 8 to 64 characters long and contains a mix of upper and lower case characters, one numeric and one special character
        // $passwordValidator = v::regex('/((?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W]).{8,64})/')->validate($user_input['password']); // true
        // if(!$passwordValidator)
        //     $errors[] = "password";
            
        // Parse the errors and throw it back
        if (count($errors) != 0 ) {

            throw new CustomException(1007,'Invalid Fields',implode(",",$errors),400);
        }
        
    }

    /*
    // Activate account with SMS code
    public function activateSMSCode (Request $request, Response $response, $args) {

        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();
        
        $required_fields = [
            "phone_number"
        ];

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        $phone_number = $parsed_body['phone_number'];
        try {
            // Check if the code is correct, then activate account

            $conidition = new PDOConditionMapper();
            $conidition->where(" `users`.`phone_number` = ? AND `users`.`deactivated` = ? AND `users`.`removed` = ? ", [ $phone_number, 0, 0 ]);

            // Fetch the user
            $user_mapper = new UserMapper($this->pdo);
            $user = $user_mapper->fetch($conidition);

            // Check if codes are the same
            if($parsed_body['code'] != $user['verification_code'])
            {
                throw new CustomException(1008,'Invalid Verification','Invalid Verification code',400);
            }

            // Check parsed body content, and apply it to the user
            $condition_mapper = new PDOConditionMapper();
            $condition_mapper->where(' `phone_number` = ?', [$phone_number]);
            $condition_mapper->update("`verification_code` = ?, `activate_token` = ?, `reset_token` = ? ", [null, 1, null]);

            // Update user
            $user_mapper->update($condition_mapper);

            return $response->withStatus(200);

        } catch(\Exception $e){

            throw new CustomException(1008,'Invalid Verification','Invalid Verification code',400);
        }

    }

    // Send/Resend SMS code to the user
    public function sendSMSCode (Request $request, Response $response, $args) {
        // Prepare mapper condition
        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();

        $required_fields = [
            "phone_number"
        ];

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        $phone_number = $parsed_body['phone_number'];

        try {

            // Generate a new code
            $fourRandomDigit = rand(1000,9999);

            $conidition = new PDOConditionMapper();
            $conidition->where(" `users`.`phone_number` = ? AND `users`.`deactivated` = ? AND `users`.`removed` = ? AND `activate_token` != ? ", [ $phone_number, 0, 0, 1]);

            // Fetch the user
            $user_mapper = new UserMapper($this->pdo);
            $user = $user_mapper->fetch($conidition);

            if($user) {
                // Check parsed body content, and apply it to the user
                $condition_mapper = new PDOConditionMapper();
                $condition_mapper->where(' `phone_number` = ? AND `activate_token` != ? ', [$phone_number, 1]);
                $condition_mapper->update("`verification_code` = ?", [$fourRandomDigit]);

                // Update user
                $success = $user_mapper->update($condition_mapper);

                // Send the code to the user
                $this->sms_helper->sendSMS($user['phone_number'] , "Verification code: " . $fourRandomDigit);

                return $response->withStatus(200);
            }else{
                throw new CustomException(1009,'SMS Error','SMS Send failed',400);
            }
           
        } catch(\Exception $e){

            throw new CustomException(1009,'SMS Error','SMS Send failed',400);
        }
    }
    */
    
    // Regenerate JWT Token
    public function regenerateToken (Request $request, Response $response, $args) {

        $bearer_token = $request->getHeader('Authorization');
        $token = explode(" ", $bearer_token[0]);

        // Parse token - get its content
        $jwt_controller = new JwtHelper(['secret' => $this->settings["jwt"]["secret"], 'tokenKey' => $this->settings["jwt"]["tokenKey"]]);
        $parsed_content = $jwt_controller->getPayload($token[1]);
        $uid = $parsed_content['uid'];

        // Regenerate JWT tokens
        $data = $this->jwt->regenerate($uid, $request);

        $payload = json_encode($data, JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // Check if token is valid / user logged in
    public function checkAuthorization (Request $request, Response $response, $args){

        // Check if request has Authorization header
        if (!$request->hasHeader('Authorization')) {
            // $exception = new CustomException($request);
            // $exception->setExceptionFields(403,"Invalid Authorization","Invalid token supplied.","");
            // throw $exception;

            return $response->withStatus(400);
        }

        $users_id = (int) $args['users_id'];
        $bearer_token = $request->getHeader('Authorization');
        $token = explode(" ", $bearer_token[0]);

        if(!$this->mapper->checkToken($users_id, $token[1]))
        {
            // $exception = new CustomException($request);
            // $exception->setExceptionFields(403,"Invalid Authorization","Supplied Token is invalid or expired.","");
            // throw $exception;
            return $response->withStatus(400);
        }

        // Token is valid, return 200
        return $response->withStatus(200);
    }

    /*
    // Change password for a user
    public function changePassword (Request $request, Response $response, $args) {

        $query_params = $request->getQueryParams();
        $user_id = (int) $query_params['user_id'];

        $required_fields = [
            "password", "repassword", "old_password"
        ];

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($query_params), $required_fields);

        $new_pass = $query_params['password'];
        $retype_pass = $query_params['repassword'];
        $old_password = $query_params['old_password'];

        // Check if new password retype match
        if($new_pass != $retype_pass)
            throw new CustomException(2003, "Passwords do not match.", "Passwords do not match.", 400);


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
    */

    // Send email to user/users
    public function sendEmail (Request $request, Response $response, $args) {

        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();
        $user_id = (int) $args['users_id'];
        
        // check if the required field
        $required_fields = [
            "subject", "email", "message", "recaptcha"
        ];

        $skip_recaptcha = isset($parsed_body['skip_recaptcha123']) ?? false;
        // for dev only, will be removed later on
        if($skip_recaptcha)
            unset($required_fields[3]);

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);
        
        // check for automated attacks
        if(!$skip_recaptcha){
            $recaptcha = $parsed_body['recaptcha'];
            if(!$this->recaptcha->verify($recaptcha))
                throw new CustomException(2012,'Invalid Recaptcha','The recaptcha validation is incorrect', 400);
        }
        
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
            
            //Recipients
            $mail->setFrom($this->settings["smtp_default_email"], 'Diabafrica');

            // Check if we want to send to 1 user or multiple
            if(is_array($email))
            {
                foreach ($email as $key => $value) {
                    $mail->addAddress($value); 
                }
            }else
            {
                $mail->addAddress($email);
                $mail->addReplyTo($email);
            }

            $mail->Subject = $subject;
            $mail->Body    = $message;
            //$mail->AltBody = 'Thank you for signing up. Please click on the following link '.$link.' to activate your account. if you can\'t click on the link, please copy and paste it in your browser.';

            $mail->send();
            // echo 'Message has been sent';
        } catch (Exception $e) {
            // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        return $response->withStatus(200);
    }


    // ------------ CUSTOM METHODS ------------
    // ----------------------------------------

    protected function validateRequiredFields (Array $user_input, Array $required_fields) {

        // everything is ok just validate the logic
        $required = array_diff($required_fields, $user_input);

        if (count($required) != 0 ) {

            throw new CustomException(1001,'Missing fields',implode(",",$required),400);
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

    protected function generateString ($length) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ._';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }

        return $randomString;
    }

    // ------------------------------------------------
    // ------------------------------------------------



    // --------- VALIDATION METHODS -----------
    // ----------------------------------------

    // ******** BY EMAIL ********

    public function forgetPassword (Request $request, Response $response, $args) {

        try {

            $query_params = $request->getQueryParams();
            $email = $query_params['email']; // email
            $token = $this->generateString(20);

            if(!$this->mapper->forgetPassword($email, $token))
                return $response->withStatus(400);
    
            // we need to send an email_security
            $this->sendRequestToResetPass($email, $token);

            return $response->withStatus(200);
        } catch(\Exception $e){

            // Always return 200, so we don't let users know if this email exists or not
            return $response->withStatus(200);
        }
    }

    
    public function resetPassView (Request $request, Response $response, $args) {

        $token = $args['reset_token'];
        $user_obj = $this->mapper->getUserBasedOnResetToken($token);

        $link = $this->full_url . $this->router->urlFor('api_resetpass_user');
        $this->view->render($response, 'signup_verification/reset_password_view.twig', [
            "full_name" => trim($user_obj['full_name']),
            "url" => $link,
            "token" => $token
        ]);
        return $response;
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
            $mail->setFrom($this->settings["smtp_default_email"], 'Diabafrica');
            $mail->addAddress($email);     // Add a recipient

            $link = $this->full_url . $this->router->urlFor('api_resetpassview_user', ["reset_token" => $token]);
            $body = $this->view->fetch("signup_verification/reset_password_email.twig", ["link"=> $link]);

            // Create reset code of 4 numbers
            $reset_code = rand(1000,9999);

            // Insert it in database
            $user_mapper = new UserMapper($this->pdo);
            $condition_mapper = new PDOConditionMapper();
            $condition_mapper->where(' `email` = ?', [$email]);
            $condition_mapper->update("`verification_code` = ?", [
                $reset_code
            ]);
            $user_mapper->update($condition_mapper);

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

    protected function sendVerificationEmail ($email, $token, $name, $lang) {

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
            //$mail->setFrom($this->settings["signup_verification"]["email_username"], 'no-reply');
            $mail->setFrom($this->settings["smtp_default_email"], 'Diabafrica');
            $mail->addAddress($email);     // Add a recipient

            $link = $this->full_url . $this->router->urlFor('api_verify_user', ["activate_token" => $token, "lang" => $lang]);

            // Send twig based on language
            switch ($lang) {
                case 'en':
                    $body = $this->view->fetch("signup_verification/activation_verification_email_en.twig", ["link"=> $link, "name"=> $name]);
                    break;
                case 'fr':
                    $body = $this->view->fetch("signup_verification/activation_verification_email_fr.twig", ["link"=> $link, "name"=> $name]);
                    break;
                default:
                    $body = $this->view->fetch("signup_verification/activation_verification_email_en.twig", ["link"=> $link, "name"=> $name]);
                    break;
            }

            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = 'Email Verification';
            $mail->Body    = $body;
            $mail->AltBody = 'Thank you for signing up. Please click on the following link '.$link.' to activate your account. if you can\'t click on the link, please copy and paste it in your browser.';

            $mail->send();
            // echo 'Message has been sent';
        } catch (Exception $e) {
            // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        return true;
    }

    public function verifyCode (Request $request, Response $response, $args) {

        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();

        $required_fields = [
            "email", "code"
        ];

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        $email = $parsed_body['email'];
        $code = $parsed_body['code'];

        // Fetch the user from the database
        $mapper = new UserMapper($this->pdo);
        $condition_mapper = new PDOConditionMapper();
        $condition_mapper->where("email = ? AND `verification_code` = ? AND `deactivated` = ? AND `removed` = ?", [ $email, $code, 0, 0]);
        $result = $mapper->fetch($condition_mapper);
        // Check if code is correct
        if($result)
            return $response->withStatus(200);

        // User and code is not correct, return 400
        return $response->withStatus(400);
    }

    
    public function verify (Request $request, Response $response, $args) {

        $token = $args['activate_token'];
        $lang = $args['lang'];
        $user_row = $this->mapper->getUserBasedOnToken($token);

        // Fetch the main domain
        $main_domain = $this->settings['main_domain']['url'];

        // Check if user exists
        // Happens when users clicks verify button again. Checks token, returns false, so we redirect because token is already 1.
        if(!$user_row)
        {
            return $response->withHeader('Location',  $main_domain . '/login')->withStatus(302);
        }

        // Verify user and check if he's already verified
        if ($this->mapper->verify($token) && $token != "1") {

            // Redirect instantly to the website
            return $response->withHeader('Location', $main_domain . '/login')->withStatus(302);

            // Show twig based on language
            // switch ($lang) {
            //     case 'en':
            //         $this->view->render($response, 'signup_verification/activation_success_en.twig', [
            //             "name" => "User"
            //         ]);
            //         break;
            //     case 'fr':
            //         $this->view->render($response, 'signup_verification/activation_success_fr.twig', [
            //             "name" => "User"
            //         ]);
            //         break;
            //     default:
            //         $this->view->render($response, 'signup_verification/activation_success_en.twig', [
            //             "name" => "User"
            //         ]);
            //         break;
            // }

        }else{

            return $response->withHeader('Location', $main_domain . '/login')->withStatus(302);
        }

        return $response;
    }

    public function resetPassAPI (Request $request, Response $response, $args) {

        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();

        $required_fields = [
            "new_pass", "retype_new_pass"
        ];

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        $new_pass = $parsed_body['new_pass'];
        $retype_pass = $parsed_body['retype_new_pass'];

        // Check if new and retype are the same
        if($new_pass != $retype_pass)
            throw new CustomException(2003,'Password does not match','Password does not match',400);

        $token = $parsed_body['reset_token'];
        $salt_hash = $this->generateString(20);

        if (!$this->mapper->resetPassword($new_pass, $token, $salt_hash)) {
            $this->view->render($response, 'signup_verification/reset_password_failure.twig');
            return $response;
        }

        $this->view->render($response, 'signup_verification/reset_password_success.twig');
        return $response;
    }

    // UPDATE USER DETAILS
    public function updateUser (Request $request, Response $response, $args) {

        $user_id = (int) $args['users_id'];
        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();

        $conidition = new PDOConditionMapper();
        $conidition->where(" `users`.`id`= ? ",[$user_id]);

        // Fetch the user
        $user_mapper = new UserMapper($this->pdo);
        $user = $user_mapper->fetch($conidition);

        // Check parsed body content, and apply it to the user
        $user_model = new UserModel($user);
        $user_model->hydrate($parsed_body);

        try {
            $condition_mapper = new PDOConditionMapper();
            $condition_mapper->where(' `id` = ?', [$user_id]);
            $condition_mapper->update("`first_name` = ?,`last_name` = ?, `clinic_hospital` = ?, `country` = ?, `nationality` = ? ", [
                $user_model->getFirstName(), 
                $user_model->getLastName(), 
                $user_model->getClinicHospital(), 
                $user_model->getCountry(), 
                $user_model->getNationality()
            ]);

            $user_mapper->update($condition_mapper);

        }catch(\Exception $e){
            throw new CustomException( 2009, 'Update Failed', 'Could not update user', 400);
        }

        // code here
        return $response->withStatus(200);
    }
    
    // ******* BY SMS ********
    /*
    // Reset/Change phone number
    public function resetPhoneNumber (Request $request, Response $response, $args) {

        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();
        $users_id = (int) $args['users_id'];

        // check if the required field
        $required_fields = [
            "new_phone_number", "password"
        ];

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        $password = $parsed_body['password'];
        $new_phone = $parsed_body['new_phone_number'];

        // Check if password is correct
        if(!$this->mapper->checkPassword($users_id, $password))
            throw new CustomException(2005,'Invalid Credentials','The credentials are incorrect',400);

        // Remove space characters and - from phone number and validate
        $phone_number_trimmed = str_replace([" ", "-"], '', $new_phone );
        $phoneNumberValidator = v::regex('/^[+20(1]*[0,1,2,5]{2}[)][0-9]{7}/')->validate($phone_number_trimmed); // true
        
        if(!$phoneNumberValidator)
            throw new CustomException(1007,'Invalid Fields',"Invalid phone number",400);

        // Check if phone number already exists in the database
        $user_mapper = new UserMapper($this->pdo);
        $conidition = new PDOConditionMapper();
        $conidition->where(" `users`.`phone_number` = ? ", [ $new_phone ]);
        $user_found = $user_mapper->fetch($conidition);
        if($user_found)
            throw new CustomException(2004,'Already exists','Phone Number already exists',400);
            
        try {

            // Generate a new code
            $verification_code = rand(1000,9999);
            // Generate expiry for the code
            $expiry_date = new \DateTime('');
            $expiry_date->modify('+15 minute');
            $expiry_date = date_format($expiry_date,"Y/m/d H:i:s");

            // Check parsed body content, and apply it to the user
            $condition_mapper = new PDOConditionMapper();
            $condition_mapper->where(' `id` = ?', [ $users_id ]);
            $condition_mapper->update("`users`.`verification_code` = ?, `users`.`verification_code_expiry` = ?, `users`.`tmp_phone_number` = ? ", [ $verification_code, $expiry_date, $new_phone ]);

            // Update user
            $user_mapper->update($condition_mapper);

            // Send the code to the user
            $this->sms_helper->sendSMS($new_phone, "Verification code: " . $verification_code);

            return $response->withStatus(200);

        } catch(\Exception $e){

            throw new CustomException(2007,'Reset failed','Phone Number reset failed',400);
        }

    }

    // Validate new phone number
    public function validatePhoneNumber (Request $request, Response $response, $args) {

        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();
        $users_id = (int) $args['users_id'];

        $required_fields = [
            "code"
        ];

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        $code = $parsed_body['code'];

        // Check verification code if its correct
        $conidition = new PDOConditionMapper();
        $conidition->where(" `users`.`id` = ? AND `users`.`deactivated` = ? AND `users`.`removed` = ? ", [ $users_id, 0, 0 ]);

        // Fetch the user
        $user_mapper = new UserMapper($this->pdo);
        $user = $user_mapper->fetch($conidition);

        // Check if verification code has expired
        $date_now = new \DateTime();
        $date2    = new \DateTime($user['verification_code_expiry']);
        if ($date_now > $date2) {
            throw new CustomException(1008,'Invalid Verification','Verification code has expired',400);
        }

        // Check if codes are the same
        if($code != $user['verification_code'])
        {
            throw new CustomException(1008,'Invalid Verification','Invalid Verification code',400);
        }

        try {

            // Prepare user update conditions
            $condition_mapper = new PDOConditionMapper();
            $condition_mapper->where(' `id` = ?', [$users_id]);
            $condition_mapper->update("`verification_code` = ?, `phone_number` = ?, `tmp_phone_number` = ?, `verification_code_expiry` = ? ", [null, $user['tmp_phone_number'], null, null]);

            // Update user
            $user_mapper->update($condition_mapper);

            return $response->withStatus(200);

        } catch(\Exception $e){

            throw new CustomException(2008,'Validation failed','Could not validate phone number',400);
        }

    }

    // Reset/Change phone number
    public function resetEmail (Request $request, Response $response, $args) {

        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();
        $users_id = (int) $args['users_id'];

        $required_fields = [
            "email", "password"
        ];

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        $new_email = $parsed_body['email'];
        $password = $parsed_body['password'];

        // Check if email already exists
        $user_mapper = new UserMapper($this->pdo);
        $user_mapper_results = $user_mapper->fetchUserByEmail($new_email);
        if($user_mapper_results)
            throw new CustomException(2004,'Already exists','Email already exists',400);

        // Check if password is correct
        if(!$this->mapper->checkPassword($users_id,$password))
            throw new CustomException(2005,'Invalid Credentials','The credentials are incorrect',400);

        // Generate a new code
        $reset_token = $this->generateString(50);
        // Generate expiry for the code
        $expiry_date = new \DateTime('');
        $expiry_date->modify('+15 minute');
        $expiry_date = date_format($expiry_date,"Y/m/d H:i:s");

        // Check parsed body content, and apply it to the user
        $condition_mapper = new PDOConditionMapper();
        $condition_mapper->where(' `id` = ?', [ $users_id ]);
        $condition_mapper->update("`reset_token` = ?, `verification_code_expiry` = ?, tmp_email = ? ", [ $reset_token, $expiry_date, $new_email ]);

        // Update user
        $user_mapper->update($condition_mapper);

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
            $mail->setFrom('no-reply@mywinstonegypt.com');
            //$mail->setFrom($this->settings["smtp_default_email"], 'Winston');
            $mail->addAddress($new_email);     // Add a recipient

            //$link = $this->full_url . $this->router->urlFor('reset_email_view',['reset_token'=>$reset_token]);
            $link = $this->full_url . $this->router->urlFor('validate_email', ["reset_token" => $reset_token]);
            $body = $this->view->fetch("signup_verification/reset_email_verification.twig", ["link"=> $link]);

            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = 'Email Reset';
            $mail->Body    = $body;
            $mail->AltBody = 'Please click on the following link '.$link.' to change your email. if you can\'t click on the link, please copy and paste it in your browser.';

            $mail->send();
            // echo 'Message has been sent';
        } catch (Exception $e) {
            throw new CustomException(1010,'Send failed','Could not send email',400);
            // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
            
        return $response->withStatus(200);
    }
    
    // Validate email SMS code
    public function validateEmail (Request $request, Response $response, $args) {

        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();
        $token = $args['reset_token'];
        $user_obj = $this->mapper->getUserBasedOnResetToken($token);
        if(!$user_obj)
            throw new CustomException(2000,'Invalid Token','Token is invalid',400);

        $expiry_date = $user_obj['verification_code_expiry'];

        // Check if verification code has expired
        $date_now = new \DateTime();
        $date2    = new \DateTime($expiry_date);
        if ($date_now > $date2) {

            $this->view->render($response, 'signup_verification/token_expired.twig');
            return $response;
        }

        // Prepare user update conditions
        $condition_mapper = new PDOConditionMapper();
        $condition_mapper->where(' `id` = ?', [$user_obj['id']]);
        $condition_mapper->update("`verification_code` = ?, `email` = ?, `tmp_email` = ?, `verification_code_expiry` = ?, `reset_token` = ? ", [null, $user_obj['tmp_email'], null, null, null]);

        // Update user
        $user_mapper = new UserMapper($this->pdo);
        $user_mapper->update($condition_mapper);

        $this->view->render($response, 'signup_verification/reset_email_success.twig', [
            "email" => $user_obj['tmp_email']
        ]);

        return $response;
    }

    // Request a reset password through SMS
    public function resetPasswordBySMS (Request $request, Response $response, $args) {
        
        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();

        // check if the required field
        $required_fields = [
            "phone_number", "recaptcha"
        ];

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        $phone_number = $parsed_body['phone_number'];
        $recaptcha = $parsed_body['recaptcha'];

        // check for automated attacks
        if(!$this->recaptcha->verify($recaptcha))
            throw new CustomException(2012,'Invalid Recaptcha','The recaptcha validation is incorrect', 400);

        // Check phone number if its exists
        $conidition = new PDOConditionMapper();
        $conidition->where(" `users`.`phone_number` = ? AND `users`.`deactivated` = ? AND `users`.`removed` = ? ", [ $phone_number, 0, 0 ]);
        $user_mapper = new UserMapper($this->pdo);
        $user = $user_mapper->fetch($conidition);
        if(!$user)
            throw new CustomException(1011,'Does not exist','User does not exist.',400);

        // Generate a new code
        $verification_code = rand(1000,9999);
        // Generate expiry for the code
        $expiry_date = new \DateTime('');
        $expiry_date->modify('+15 minute');
        $expiry_date = date_format($expiry_date,"Y/m/d H:i:s");

        // Check parsed body content, and apply it to the user
        $condition_mapper = new PDOConditionMapper();
        $condition_mapper->where(' `id` = ?', [ $user['id'] ]);
        $condition_mapper->update(" `verification_code` = ?, `verification_code_expiry` = ? ", [ $verification_code, $expiry_date ]);

        // Update user
        $user_mapper->update($condition_mapper);

        // Send SMS to the user
        $this->sms_helper->sendSMS($user['phone_number'] , "Verification code: " . $verification_code);

        return $response->withStatus(200);
    }

    // Validate and reset password through SMS
    public function validateResetPasswordBySMS (Request $request, Response $response, $args) {

        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();

        // check if the required field
        $required_fields = [
            "phone_number", "code", "password"
        ];

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        $phone_number = $parsed_body['phone_number'];
        $code = $parsed_body['code'];
        
        // Check phone number if its exists
        $conidition = new PDOConditionMapper();
        $conidition->where(" `users`.`phone_number` = ? AND `users`.`deactivated` = ? AND `users`.`removed` = ? ", [ $phone_number, 0, 0 ]);
        $user_mapper = new UserMapper($this->pdo);
        $user = $user_mapper->fetch($conidition);
        if(!$user)
            throw new CustomException(1011,'Does not exist','User does not exist.',400);

        // Check if verification code has expired
        $date_now = new \DateTime();
        $date2    = new \DateTime($user['verification_code_expiry']);
        if ($date_now > $date2) {
            throw new CustomException(1008,'Invalid Verification','Verification code has expired',400);
        }

        // Check if codes are the same
        if($code != $user['verification_code'])
        {
            throw new CustomException(1008,'Invalid Verification','Invalid Verification code',400);
        }

        try {

            $password = $parsed_body['password'];
            // Fake reset token
            $token = $this->generateString(50);
            // Create salt hash for password
            $salt_hash = $this->generateString(20);

            // Insert the fake reset token in the reset_token column
            $this->mapper->resetTokenByUserId($user['id'],$token);

            // Call reset password
            if (!$this->mapper->resetPassword($password, $token, $salt_hash))
                return $response->withStatus(401);

            // Reset has been successful, now lets clear reset tokens
            // Prepare user update conditions
            $condition_mapper = new PDOConditionMapper();
            $condition_mapper->where(' `id` = ?', [$user['id']]);
            $condition_mapper->update("`verification_code` = ?, `verification_code_expiry` = ?, `reset_token` = ? ", [null, null, null]);

            // Update user
            $user_mapper = new UserMapper($this->pdo);
            $user_mapper->update($condition_mapper);

            return $response->withStatus(200);

        } catch(\Exception $e){

            throw new CustomException(2008,'Validation failed','Could not validate password',400);
        }

    }
    */
    // ------------------------------------------------
    // ------------------------------------------------


    
    /*
    // --------- A D M I N    A C T I O N S ----------
    // ------------------------------------------------
    
    // Create a user by admin
    public function createUser (Request $request, Response $response, $args) {

        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();

        $required_fields = [
            "email", "first_name", "password", "confirm_password"
        ];

        // check if all the required fields are supplied
        $this->validateRequiredFields(array_keys($parsed_body), $required_fields);

        // Check if email already exists
        $user_mapper = new UserMapper($this->pdo);
        $user_mapper_results = $user_mapper->fetchUserByEmail($parsed_body["email"]);

        // If mapper returned results, it means that the email is already in use
        if($user_mapper_results)
        {
            throw new CustomException(2004,'Already exists','Email already exists',400);
        }

        // Check if username alreadt exists
        // $condition_mapper = new PDOConditionMapper();
        // $condition_mapper->where(" `username` = ?", [$parsed_body['username']]);
        // $user_mapper_results = $user_mapper->fetch($condition_mapper);
        // if($user_mapper_results)
        // {
        //     $exception = new CustomException($request);
        //     $exception->setExceptionFields(400,"Username already exists","Already exists","Username already exists");
        //     throw $exception;
        // }

        try
        {
            $token = $this->generateString(50);
            $salt_hash = $this->generateString(20);

            $email = $parsed_body['email'];
            $name = $parsed_body["first_name"] ?? "";

            // create a password if not supplied
            if(isset($parsed_body['password'])) {
                $parsed_body['salt_hash'] = $salt_hash;
            }

            // Check if passwords match
            if(trim($parsed_body['confirm_password']) != trim($parsed_body['password']) || empty(trim($parsed_body['confirm_password'])))
                throw new CustomException(2003,'Password does not match','Password does not match',400);

            // Insert user in the database
            $id = $this->mapper->insert($parsed_body, $token);

            // Get admin info
            $users_hospitals_mapper = new UsersHospitalsMapper($this->pdo);
            $condition_mapper = new PDOConditionMapper();
            $condition_mapper->where(" `users_id` = ?", [$parsed_body['admin_id']]);
            $users_hospitals_results = $users_hospitals_mapper->fetch($condition_mapper);

            // Insert newly created user into hospital
            $users_hospitals_mapper->insert($id,$users_hospitals_results['hospitals_id']);

            // Get user group and insert the new user in the user_groups
            $groups_mapper = new GroupsMapper($this->pdo);
            $condition_mapper->where(" `title` = ? AND `removed` = ?", [ "User", 0 ]);
            $groups_mapper_results = $groups_mapper->fetch($condition_mapper);

            // Insert user in user groups
            $users_groups_mapper = new UsersGroupsMapper($this->pdo);
            $users_groups_mapper->insertSingle($id,$groups_mapper_results['id']);

            // everything went ok
            return $response->withStatus(201);

        }catch(\Exception $e){

            throw new CustomException(2002,'Registration Failed','Could not register',400);
        }
    }

    // Fetch list of users - paginated
    public function fetchUsers (Request $request, Response $response, $args) {

        // Get the pagination condition mapper
        $condition_mapper = $request->getAttribute('QUERY_PAGINATION');
        $condition_mapper->where(' `removed` = ? ', [0]);

        // Prepare user mapper
        $mapper = new UserMapper($this->pdo);
        $results = $mapper->fetch($condition_mapper);
        $count = $mapper->fetchTotalCount(); // Get the count for the pagination
      
        $url_route = $this->router->urlFor('fetch_users');
        $paginated_response = \App\Helpers\PaginationHelper::WrapPrevNextPages($url_route ,$request,$results,$count);

        $payload = json_encode($paginated_response, JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // UPDATE USER EMAIL AND PASSWORD
    public function updateCredentials (Request $request, Response $response, $args) {

        $user_id = (int) $args['users_id'];
        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();
       
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

    // DELETE USER
    public function deleteUser (Request $request, Response $response, $args) {

        $users_id = (int) $args['users_id'];

        $mapper = new UserMapper($this->pdo);
        $success = $mapper->delete($users_id);

        if(!$success)
            return $response->withStatus(400);

        return $response->withStatus(200);
    }

    // DE-ACTIVATE USER
    public function deactivateUser (Request $request, Response $response, $args) {

        $users_id = (int) $args['users_id'];

        $mapper = new UserMapper($this->pdo);
        $success = $mapper->deactivate($users_id);

        if(!$success)
            return $response->withStatus(400);

        return $response->withStatus(200);
    }

    // ACTIVATE USER
    public function activateUser (Request $request, Response $response, $args) {

        $users_id = (int) $args['users_id'];

        $mapper = new UserMapper($this->pdo);
        $success = $mapper->activate($users_id);

        if(!$success)
            return $response->withStatus(400);

        return $response->withStatus(200);
    }
    */
    // ------------------------------------------------
    // ------------------------------------------------
    
}
