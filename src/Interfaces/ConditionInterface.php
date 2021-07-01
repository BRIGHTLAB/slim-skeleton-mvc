<?php

namespace App\Interfaces;

/**
 *
 */
interface ConditionInterface
{

  // input
  public function where ($key, $value);
  public function order ($value);
  public function setPage (int $page);
  public function setOffset (int $offset);
  public function update ($key, $value);

  // output
  public function generateWhereSQL();
  public function getValues();
  public function getOffset();
  public function getPage();
}
