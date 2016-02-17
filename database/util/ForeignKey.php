<?php

namespace deco\essentials\database\util;

class ForeignKey {
  private $name;
  private $column;
  private $table;
  private $foreignColumn;
  private $onDelete;
  private $onUpdate;  
  
  public function __construct($name,$column,$table,$foreignColumn,$onDelete,$onUpdate){
    $this->name = $name;
    $this->column = $column;
    $this->table = $table;    
    $this->foreignColumn = $foreignColumn;
    $this->onDelete = $onDelete;
    $this->onUpdate = $onUpdate;
  }
  
  public function __call($name,$arguments){
    if (preg_match('#^get[A-Z]#',$name)){
      $col = lcfirst(preg_replace('#^get#','',$name));
      return $this->$col;
    }    
  }
  
}
