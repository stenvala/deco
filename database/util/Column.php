<?php

namespace deco\essentials\database\util;

class Column {

  private $name;
  private $type;
  private $default;
  private $autoIncrement = false;
  private $primaryKey = false;
  private $unique = false;
  private $allowNull = false;

  public function __construct($name, $type) {
    $this->name = $name;
    $this->type = $type;
  }
    
  public function setDefault($default) {
    $this->default = $default;
  }

  public function setAsAutoIncrement() {
    $this->autoIncrement = true;
  }

  public function setAsPrimaryKey() {
    $this->primaryKey = true;
  }

  public function setAsUnique() {
    $this->unique = true;
  }
  
  public function allowNull() {
    $this->allowNull = true;
  }

  public function hasDefault() {
    return isset($this->default);
  }
        
  public function __call($name, $arguments) {
    if (preg_match('#^get[A-Z]#', $name)) {
      $col = lcfirst(preg_replace('#^get#', '', $name));
      return $this->$col;
    }
  }

  static public function getTypeForProperty($annCol) {
    $type = $annCol->getValue('type');
    if ($type == 'string') {
      if (count($val = $annCol->getValue('validation', array())) > 0 &&
          array_key_exists('maxLength', $val)) {
        $type = "varchar({$val['maxLength']})";
      } else {
        $type = 'text';
      }
    } else if ($type == 'enum') {
      $values = $annCol->getValue('values');
      $type = " enum('" . implode("','", $values) . "')";
    }
    return $type;
  }

}
