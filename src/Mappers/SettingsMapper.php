<?php
namespace App\Mappers;

use PDO;
use App\Interfaces\ConditionInterface;

class SettingsMapper extends BaseMapper {

   public function fetchSettings ($key) {

	    $sql = "SELECT * FROM `_settings` WHERE `key` = :KEY";

	    $st = $this->data_adapter->prepare($sql);
	    $st->execute([
	            ':KEY' => $key
	        ]);
	    $results = $st->fetch(PDO::FETCH_ASSOC);
	    return $results;
    }

}