<?php

namespace App\Mappers;

use PDO;
use App\Interfaces\ConditionInterface;
use App\Models\UserModel;
use App\Helpers\PDOConditionMapper;

class UserMapper extends BaseMapper {

  public function __construct (PDO $pdo_adapter) {
      $this->data_adapter = $pdo_adapter;
      $this->model_class_name = UserModel::class;
      parent::__construct($pdo_adapter);
  }

  public function fetchAll (ConditionInterface $condition) : array {

    $condition_statement = $condition->generateWhereSQL(); // string
    $prepare_statement = $condition->getValues(); // array
    $pagination = $condition->generatePagination();

    $sql = "
    SELECT 
    `users`.*
    FROM `users`" . $condition_statement;
    $st = $this->data_adapter->prepare($sql);
    $st->execute($prepare_statement);
    $results = $st->fetchAll(PDO::FETCH_ASSOC);

    return $results;
  }

  public function fetchAllPaginated(PDOConditionMapper $conditionMapper) {

    $sql = "
    SELECT 
    `users`.*
    FROM `users`
    ". $conditionMapper->generateWhereSQL() . $conditionMapper->generatePagination();

    $prepare_statement = $conditionMapper->getValues(); // array
    $st = $this->data_adapter->prepare($sql);
    $st->execute($prepare_statement);
    $results = $st->fetchAll(PDO::FETCH_ASSOC);
    return $results;
  }

  public function fetchCount(PDOConditionMapper $conditionMapper) {

    $sql = "
    SELECT
    count(*) as `count`
    FROM `users`
    ". $conditionMapper->generateWhereSQL();

    $prepare_statement = $conditionMapper->getValues(); // array
    $st = $this->data_adapter->prepare($sql);
    $st->execute($prepare_statement);
    $results = $st->fetch(PDO::FETCH_ASSOC);
    return (int) $results['count'];
  }

  public function fetch (PDOConditionMapper $condition){

    $condition_statement = $condition->generateWhereSQL(); // string
    $prepare_statement = $condition->getValues(); // array

    $sql = "
    SELECT 
    `users`.*
    FROM `users` " . $condition_statement;
    $st = $this->data_adapter->prepare($sql);
    $st->execute($prepare_statement);
    $results = $st->fetch(PDO::FETCH_ASSOC);

    return $results;
  }

  public function fetchTotalCount () {

    $sql = "SELECT COUNT(*) as `count` FROM `users` WHERE `removed` = 0";

    $st = $this->data_adapter->prepare($sql);
    $st->execute();
    $results = $st->fetch(PDO::FETCH_ASSOC);
    return (int) $results['count'];
  }


  // Insert user (as parent)
  public function insert (Array $model_assoc) {

    // Create encrypted password
    $enc_pass = hash('sha256', $model_assoc['salt_hash'].$model_assoc['password']);

    $sql = "INSERT INTO `users` (
        `first_name`,
        `last_name`,
        `username`,
        `salt_hash`,
        `enc_password`,
        `email`,
        `removed`,
        `activate_token`) VALUES (?,?,?,?,?,?)";
    $st = $this->data_adapter->prepare($sql);
    $st->execute([
      $model_assoc['username'],
      $model_assoc['salt_hash'],
      $enc_pass,
      $model_assoc['email'],
      $model_assoc['removed'],
      $model_assoc['activate_token'],  
    ]);

    return $this->data_adapter->lastInsertId();
  }

  public function update (ConditionInterface $condition) {

    $where_sql = $condition->generateWhereSQL(); // string
    $update_sql = $condition->generateUpdateSQL();
    $prepare_statement = array_merge($condition->getUpdateValues(), $condition->getValues()); // array

    $sql = "UPDATE `users` " . $update_sql . $where_sql;
    $this->data_adapter->beginTransaction();
    $st = $this->data_adapter->prepare($sql);
    $st->execute($prepare_statement);
    $count = $st->rowCount();
    $this->data_adapter->commit();

    return $count;
  }

  public function delete ($id) {

    $sql = "UPDATE `users` SET `removed` = 1 WHERE `id` = :ID";

    $this->data_adapter->beginTransaction();
    $st = $this->data_adapter->prepare($sql);
    $st->execute([
        ':ID' => $id
    ]);

    $count = $st->rowCount();
    $this->data_adapter->commit();
    return $count > 0;
  }

  public function deactivate ($id) {

    $sql = "UPDATE `users` SET `deactivated` = 1 WHERE `id` = :ID";

    $this->data_adapter->beginTransaction();
    $st = $this->data_adapter->prepare($sql);
    $st->execute([
        ':ID' => $id
    ]);

    $count = $st->rowCount();
    $this->data_adapter->commit();
    return $count > 0;
  }

  public function activate ($id) {

    $sql = "UPDATE `users` SET `deactivated` = 0 WHERE `id` = :ID";

    $this->data_adapter->beginTransaction();
    $st = $this->data_adapter->prepare($sql);
    $st->execute([
        ':ID' => $id
    ]);

    $count = $st->rowCount();
    $this->data_adapter->commit();
    return $count > 0;
  }


  public function resetEmail (UserModel $model) {

    $sql = "UPDATE `users` SET `email` = :EMAIL WHERE `id` = :ID AND `removed` = 0";

    $this->data_adapter->beginTransaction();
    $st = $this->data_adapter->prepare($sql);
    $st->execute([
        ":EMAIL" => $model->getEmail(),
        ":ID" => $model->getId(),
    ]);
    $count = $st->rowCount();
    $this->data_adapter->commit();
    return $count > 0;
  }

  public function fetchUserByEmail ($email){

    $sql = "SELECT `users`.`id` FROM `users` WHERE `email`= :EMAIL AND `removed` = 0 AND `activate_token` = 1";

    $st = $this->data_adapter->prepare($sql);
    $st->execute([
        ':EMAIL' => $email
    ]);
    $results = $st->fetch(PDO::FETCH_ASSOC);
    return $results;
  }
}
