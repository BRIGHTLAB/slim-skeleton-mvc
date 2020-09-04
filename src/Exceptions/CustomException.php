<?php

namespace App\Exceptions;

use \Exception;

/**
 * Define a custom exception class
 */
class CustomException extends Exception
{
    protected $json;
    protected $status;
    protected $details;

    // Redefine the exception so message isn't optional
    public function __construct($code, $message, $status = 400,  Exception $previous = null) {
        // some code
        $this->status = $status;

        if(is_array($message))
          $this->details = $message;
        
        $message = json_encode($message);
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }

    public function setDetail ($value) {
      $this->details = $value;
      return $this;
    }

    public function getDetail () {
      return $this->details;
    }

    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

    public function customFunction() {
        echo "A custom function for this type of exception\n";
    }

    public function setJson ($value){
      $this->json = $value;
      return $this;
    }

    public function setStatusCode ($value){
      $this->status = $value;
      return $this;
    }


    public function getJson (){
      return $this->json;
    }

    public function getStatusCode (){
      return $this->status;
    }

}
