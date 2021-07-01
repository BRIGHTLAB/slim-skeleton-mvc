<?php

namespace App\Mappers;

use PDO;

class PDOMapper extends BaseMapper{

     public function checkToken ($user_id, $token) {
        
        $sql = "
        SELECT `users_id` 
        FROM `users_tokens` 
        LEFT JOIN `users` ON `users`.`id` = `users_tokens`.`users_id`
        WHERE `users_tokens`.`token` = :TOKEN AND `users_tokens`.`valid_to` > NOW() AND `users_id` = :USER_ID
        ";
        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            ":TOKEN" => $token,
            ":USER_ID" => $user_id,
        ]);

        $result = $st->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function checkProfToken ($prof_id, $token) {

        $sql = "
        SELECT `custodian`.`users_id` 
        FROM `users_tokens` 
        LEFT JOIN `users` ON `users`.`id` = `users_tokens`.`users_id`
        LEFT JOIN `custodian` ON `users`.`id` = `custodian`.`users_id`
        WHERE `users_tokens`.`token` = :TOKEN AND `users_tokens`.`valid_to` > NOW() AND `custodian`.`users_id` = :USER_ID AND `custodian`.`removed` = 0
        ";
        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            ":TOKEN" => $token,
            ":USER_ID" => $prof_id,
        ]);

        $result = $st->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
}