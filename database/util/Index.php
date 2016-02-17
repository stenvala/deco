<?php

namespace deco\essentials\database\util;

class Index {

  protected $name;
  protected $columns;
  protected $kind = '';

  public function __construct($name, $columns) {
    if (!is_array($columns)) {
      $columns = array($columns);
    }
    $this->name = $name;
    $this->columns = $columns;
  }

  public function setKind($kind) {
    $this->kind = $kind;
  }

  public function __call($name, $arguments) {
    if (preg_match('#^get[A-Z]#', $name)) {
      $col = lcfirst(preg_replace('#^get#', '', $name));
      return $this->$col;
    }
  }

}
