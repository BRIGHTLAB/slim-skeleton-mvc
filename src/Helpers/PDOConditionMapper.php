<?php

namespace App\Helpers;

use App\Interfaces\ConditionInterface;

class PDOConditionMapper implements ConditionInterface
{

  protected $where_close;
  protected $array_values;
  protected $update_close;
  protected $update_key_values;
  protected $page = 0;
  protected $pageOffset = 20;
  protected $offset = 0;
  protected $limit = 20;

  public function where ($key, $value){
    $this->where_close = $key;
    $this->array_values = $value;
    return $this;
  }

  public function update ($key, $value){
    $this->update_close = $key;
    $this->update_key_values = $value;
    return $this;
  }

  public function setOffset (int $value) {
    $this->pageOffset = $value;
    return $this;
  }

  public function setPage ( int $value) {
    $this->page = $value;
    return $this;
  }

  public function setLimit ( int $value) {
    $this->limit = $value;
    return $this;
  }

  public function order($value){
    return $this;
  }

  // output
  public function generateWhereSQL(){
    return " WHERE " . $this->where_close;
  }

  public function generatePagination (){

    $tmp = $this->pageOffset * $this->page;
    $tmp -= $this->limit;

    if($this->page != 1)
      return " LIMIT " . $tmp .",".$this->limit;
    else
      return " LIMIT " . 0 .",".$this->limit;
  }

  public function getValues(){
    return $this->array_values;
  }

  public function getUpdateValues () {
    return $this->update_key_values;
  }

  public function getOffset() {
    return $this->pageOffset;
  }

  public function getPage() {
    return $this->page;
  }

  public function getLimit(){
    return $this->limit;
  }

  public function generateUpdateSQL () {
    return " SET " . $this->update_close;
  }

}
