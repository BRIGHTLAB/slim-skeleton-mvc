<?php

namespace App\Mappers;

use PDO;
use App\Interfaces\ConditionInterface;

class UsersMapper extends BaseMapper {


  	public function fetch ()  {

	    $sql = "SELECT `id`,`name` FROM `users`";

      $st = $this->data_adapter->prepare($sql);
      $st->execute();
      $results = $st->fetchAll(PDO::FETCH_ASSOC);
      return $results;
  	}

}
