<?php

namespace App\Models;

class BaseModel {

	public function __construct(Array $array_values = [], Array $exclude =[]) {
		// abilitiy to fill the model with a default values
		$this->hydrate($array_values, $exclude);
	}

	public function getProperties(){
		return get_object_vars($this);
	}

	public function hydrate (Array $array_values = [], $exclude = []) {

		$fields = $this->getProperties();
    	// fill the model
    	foreach ($fields as $property => $value) {

      	// base case just skip the excluded item
      	if(in_array($property, $exclude))
        		continue;

      	// search for the get method
      	$foo = ucwords($property, "_");
      	$function = "set".str_replace("_", "", $foo);

      	if(isset($array_values[$property]) && method_exists($this, $function) ){
        		$this->$function($array_values[$property]);
      	}
    	}
		return $this;
	}

}
