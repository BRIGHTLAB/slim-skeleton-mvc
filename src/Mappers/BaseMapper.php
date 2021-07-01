<?php

namespace App\Mappers;

use PDO;

class BaseMapper {

  protected $data_adapter;
  protected $model_class_name;
  protected $affected_properties = []; // properties to be affected

  public function __construct (PDO $pdo_adapter) {
      $this->data_adapter = $pdo_adapter;
  }

  public function affectOnly (Array $user_properties ) {

    $properties = [];
    /*
      SQL injection prevention should be adapted here
      We have to check the requested properties if they are genuine
      otherwise the attacker could inject sql here ex:
      {
        "first_name" : "Joe",
        "1=1 OR DROP * ;--" : "not gonna reach"
      }
    */
    $model = new $this->model_class_name;
    $genuine_properties = $model->getProperties();
    foreach ($user_properties as $u_property) {
      if(array_key_exists($u_property, $genuine_properties))
        $properties[] = $u_property;
    }

    $this->affected_properties = $properties;
    return $this;
  }

 // can be overriden
  protected function getAffectedColumnsAsSTR () {
    $set_str = [];
    foreach($this->affected_properties as $index => $property){
        $set_str[] = " `$property` = ? ";
    }
    return implode(",", $set_str);
  }

  // can be overriden
  protected function getAffectedResultsArray ($model) {
    $array = [];
    foreach($this->affected_properties as $index => $property){

      // search for the get method
      $foo = ucwords($property, "_");
      $function = "get".str_replace("_", "", $foo);
      if(method_exists($model, $function) ){
        $array[] = $model->$function();
      }
    }
    return $array;
  }


}
