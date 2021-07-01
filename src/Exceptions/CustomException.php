<?php

/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/4.x/LICENSE.md (MIT License)
 */

declare(strict_types=1);

namespace App\Exceptions;

use \Exception;

class CustomException extends Exception
{
    protected $json;
    protected $status;
    protected $details;
    protected $title;
   // protected $description;

    // Redefine the exception so message isn't optional
    public function __construct($code, $title, $message, $status = 400,  Exception $previous = null) {
        // some code
        $this->status = $status;

        $this->title = $title;

        //$this->description = $description;

       // if(is_array($message)){
           // $message = json_encode($message);
        $this->details = $message;
       // }
        
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }

    public function setDetail ($value) {
      $this->details = $value;
      return $this;
    }

    public function setTitle ($value) {
        $this->title = $value;
        return $this;
    }

    public function setDescription ($value) {
        $this->description = $value;
        return $this;
    }

    public function getDetails () {
      return $this->details;
    }

    public function getTitle () {
        return $this->title;
    }

    public function getDescription () {
        return $this->description;
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