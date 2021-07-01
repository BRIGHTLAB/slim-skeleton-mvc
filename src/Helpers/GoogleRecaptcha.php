<?php
namespace App\Helpers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Exceptions\CustomException;

use ReCaptcha\ReCaptcha;

final class GoogleRecaptcha
{
    protected $secret;
    protected $recaptcha; // client obj
    protected $host_name;
    protected $errors;

    public function __construct (Array $values) {

        // setting the initial option values
        $this->setOptions($values);

        // creating the instance
        $this->recaptcha = new ReCaptcha($this->secret);
        $this->recaptcha->setExpectedHostname($this->host_name);
    }

    public function setOptions (Array $options) {
        // assign the secret if exists
        $this->secret = $options['secret'] ?? "";
        $this->host_name = $options['host'] ?? "";
        return $this; // chain
    }

    public function verify ($value) {
        $resp = $this->recaptcha->verify($value, $_SERVER['REMOTE_ADDR']);
        if ($resp->isSuccess()) {
            return true;
        } else {
            $this->errors = $resp->getErrorCodes();
            return false;
        }
    }

    public function getError() {
        return $this->errors;
    }

}
