<?php

namespace App\Registration;

use PDO;

class PDOMapper {

    protected $data_adapter;
    private $table_name = "users";
    private $token_table = "users_tokens";
    //private $user_session_table = "users_token";

    private $activate_token_column = "activate_token";
    private $reset_token_column = "reset_tforgetPasswordoken";
    private $columns = [];

    public function __construct (PDO $pdo_adapter) {
        $this->data_adapter = $pdo_adapter;
    }

    public function setTableName ($value) {
        $this->table_name = $value;
        return $this;
    }

    public function checkToken ($user_id, $token) {
        $sql = "SELECT `users_id` FROM `users_tokens` WHERE `users_id` = :USER_ID AND `token` = :TOKEN AND `valid_to` > NOW() ";
        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            ":TOKEN" => $token,
            ":USER_ID" => $user_id,
        ]);

        $result = $st->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function checkPassword ($users_id, $password) {

        $sql = "SELECT * FROM `".$this->table_name."`
            WHERE
                `id` = :USERS_ID AND
                `enc_password` = SHA2(CONCAT(`salt_hash`, :PASSWORD), 256) AND
                `activate_token` = '1' ";

        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            ":USERS_ID" => $users_id,
            ":PASSWORD" => $password
        ]);

        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function setColumns (Array $columns) {
        $this->columns = $columns;
        return $this;
    }

    public function setActivateTokenColumn ($value) {
        $this->activate_token_column = $value;
        return $this;
    }

    public function setResetTokenColumn ($value) {
        $this->reset_token_column = $value;
        return $this;
    }

    public function insert (Array $model_assoc, $token = null) {

        $id = 0;
        $prepare_statement = [];
        // preparing the column name in the first SQL and set all the values to NULL
        $first_prepare_sql = [];
        //$this->setActivateTokenColumn($token);
        foreach ($this->columns as $key => $column) {

            $first_prepare_sql[] = "`".$column."`";
            $prepare_statement[":".$column] = NULL;
        }

        // finilizing the prepare statement array;
        foreach ($model_assoc as $key => $value)
        {
            $prepare_statement[":".$key] = $value;
        }

        // adding the token file
        if (!is_null($token)) {
            $first_prepare_sql[] = "`".$this->activate_token_column."`";
            $prepare_statement[":".$this->activate_token_column] = $token;
        }

        // checking the password and creating the required keys
        if (isset($model_assoc['password'])) {
            // the salt_hash is taken care of we need to create the pass only
            $password = $model_assoc['password'];
            $salt_hash = $model_assoc['salt_hash'];

            $enc_pass = hash('sha256', $salt_hash.$password);
            $prepare_statement[":enc_password"] = $enc_pass;

            // clean the old value
            //unset($prepare_statement[":enc_password"]);
            unset($prepare_statement[":confirm_password"]);
            unset($prepare_statement[":password"]);
        }
        
        $update_arr = [];
        foreach ($first_prepare_sql as $key => $row) {
            $update_arr[] = " $row = VALUES($row) ";
        }

        $sql = "INSERT INTO `".$this->table_name."`
                (
                    ".implode(',', $first_prepare_sql)."
                )
                VALUES (".implode(',', array_keys($prepare_statement)).")
                ON DUPLICATE KEY UPDATE
                " .implode(',', $update_arr);
                    
        

        $this->data_adapter->beginTransaction();
        $st = $this->data_adapter->prepare($sql);
        $st->execute($prepare_statement);

        // echo $sql;
        //print_r($st->errorInfo());
        // exit;

        // getting the last inserted id
        $id = $this->data_adapter->lastInsertId();
        $this->data_adapter->commit();

        return $id;
    }

    public function insertAddress (Array $model_assoc, int $id) {
        $sql = "INSERT IGNORE INTO `users_address` (`users_id`, `filters_details_id`, `name`, `address`, `phone`, `email`, `is_default`) VALUES (:ID, :LOCATION_ID, :NAME, :ADDRESS, :PHONE, :EMAIL, :IS_DEFAULT)";
        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            ':ID' => $id,
            ':LOCATION_ID' => (int) $model_assoc['location_id'],
            ':NAME' => "default",
            ':ADDRESS' => $model_assoc['address'],
            ':PHONE' => $model_assoc['phone_code'].$model_assoc['phone_number'],
            ':EMAIL' => $model_assoc['email'],
            ':IS_DEFAULT' => 1,
        ]);
        return true;
    }

    public function getUserBasedOnToken ($token) {
        $sql = "SELECT CONCAT(`first_name`,' ',`last_name`) as `full_name` FROM `".$this->table_name."` WHERE `".$this->activate_token_column."` = :TOKEN";
        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            ":TOKEN" => $token
        ]);
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserBasedOnResetToken ($token) {
        $sql = "SELECT CONCAT(`first_name`,' ',`last_name`) as `full_name`,`verification_code_expiry`,`verification_code`,`id`,`tmp_email` FROM `".$this->table_name."` WHERE `".$this->reset_token_column."` = :TOKEN";
        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            ":TOKEN" => $token
        ]);
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function verify ($token) {

        $sql = "UPDATE `".$this->table_name."` SET `".$this->activate_token_column."` = 1 WHERE `".$this->activate_token_column."` = :TOKEN";
        $this->data_adapter->beginTransaction();
        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            ":TOKEN" => $token
        ]);
        $count = $st->rowCount();
        $this->data_adapter->commit();

        return $count > 0;
    }

    public function login ($username, $password) {

        $sql = "SELECT * FROM `".$this->table_name."`
            WHERE
                `email` = :USERNAME AND
                `enc_password` = SHA2(CONCAT(`salt_hash`, :PASSWORD), 256) AND
                `activate_token` = '1' ";

        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            ":USERNAME" => $username,
            ":PASSWORD" => $password
        ]);

        return $st->fetch(PDO::FETCH_ASSOC);
    }

    public function profLogin ($username, $password) {

        $sql =
        "
        SELECT * 
        FROM `users`
        LEFT JOIN `custodian` ON `users`.`id` = `custodian`.`users_id`
        WHERE `users`.`email` = :USERNAME AND `users`.`enc_password` = SHA2(CONCAT(`salt_hash`, :PASSWORD), 256) AND `users`.`activate_token` = 1 AND `custodian`.`removed` = 0
        ";

        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            ":USERNAME" => $username,
            ":PASSWORD" => $password
        ]);

        return $st->fetch(PDO::FETCH_ASSOC);
    }

    // }
    public function insertToken (int $user_id, $token) {
        $sql = "INSERT INTO `".$this->token_table."` (`users_id`, `token`, `valid_to`)
                VALUES (:USER_ID, :TOKEN, CURDATE() + INTERVAL 30 DAY)";

        $this->data_adapter->beginTransaction();
        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            "USER_ID" => $user_id,
            "TOKEN" => $token
        ]);

        // getting the last inserted id
        $this->data_adapter->commit();
        return true;
    }

    public function resetPassword ($new_pass, $token, $salt_hash) {

        $sql = "UPDATE `".$this->table_name."` SET
                    `".$this->reset_token_column."` = 1,
                    `activate_token` = 1,
                    `salt_hash` = :SALT_HASH,
                    `enc_password` = SHA2(CONCAT(`salt_hash`,:NEW_PASS), 256)
                WHERE
                    `".$this->reset_token_column."` = :TOKEN ";

        //echo $sql;

        $this->data_adapter->beginTransaction();
        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            ":TOKEN" => $token,
            ":NEW_PASS" => $new_pass,
            ":SALT_HASH" => $salt_hash,
        ]);
        $count = $st->rowCount();
        $this->data_adapter->commit();
        return $count > 0;
    }

    public function resetRequestToken($email, $new_token) {

        $sql = "UPDATE `".$this->table_name."` SET
                    `".$this->reset_token_column."` = :TOKEN
                WHERE
                    `email` = :EMAIL";

        $this->data_adapter->beginTransaction();
        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            ":TOKEN" => $new_token,
            ":EMAIL" => $email
        ]);
        $count = $st->rowCount();
        $this->data_adapter->commit();

        return $count > 0;
    }

    public function resetTokenByUserId($user_id, $new_token) {

        $sql = "UPDATE `".$this->table_name."` SET
                    `".$this->reset_token_column."` = :TOKEN
                WHERE
                    `id` = :USER_ID";

        $this->data_adapter->beginTransaction();
        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            ":TOKEN" => $new_token,
            ":USER_ID" => $user_id
        ]);
        $count = $st->rowCount();
        $this->data_adapter->commit();

        return $count > 0;
    }


    public function forgetPassword($email, $new_token) {

        $sql = "UPDATE `".$this->table_name."` SET
                    `".$this->reset_token_column."` = :TOKEN
                WHERE
                     `email` = :EMAIL";

        $this->data_adapter->beginTransaction();
        $st = $this->data_adapter->prepare($sql);
        $st->execute([
            ":TOKEN" => $new_token,
            ":EMAIL" => $email
        ]);
        $count = $st->rowCount();
        $this->data_adapter->commit();

        return $count > 0;
    }

}
