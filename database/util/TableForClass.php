<?php

namespace deco\essentials\database\util;

class TableForClass extends Table {

  protected $instance;
  
  public function __construct($className) {
    $this->instance = $className;    
    parent::__construct($className::getTable());    
    $this->createTableCreationCommand();
  }  
  
  private function createTableCreationCommand() {
    $cls = $this->instance;
    $anns = $cls::getForDatabaseProperties();            
    $str = "CREATE TABLE IF NOT EXISTS {$this->tableName} (";
    foreach ($anns as $column => $annCol) {             
      $str .= "\n\t";
      $columnCommand = $column;
      $columnDescription = array();
      // Types
      if ($annCol->getValue('type') == 'string') {        
        if (array_key_exists('maxLength', $annCol->getValue('validation',array()))) {          
          $columnCommand .= " varchar({$annCol->getValue('validation')['maxLength']})";
        } else {
          $columnCommand .= ' text';
        }
      } else if ($annCol->getValue('type') == 'enum') { 
        $enum = $annCol->getValue('values');
        sort($enum);
        $columnCommand .= " enum('" . implode("','", $enum) . "')";
      } else {       
        $columnCommand .= ' ' . $annCol->getValue('type');
      }
      // Other attributes
      if (count($ref = $annCol->getValue('references',array())) > 0) {  
        if (array_key_exists('allowNull',$ref)) {
          $columnCommand .= ' NULL';
        }
      }
      if ($annCol->getValue('isNull',false)) {
        $columnCommand .= ' NOT NULL';
      }
      if (!is_null($default = $annCol->getValue('default',null))) {
        $columnCommand .= " DEFAULT {$default}";
      }
      if (!is_null($onUpdate = $annCol->getValue('onUpdate',null))) {
        $columnCommand .= " ON UPDATE {$onUpdate}";
      }
      if ($annCol->getValue('autoIncrement',false)) {
        $columnCommand .= ' AUTO_INCREMENT';
      }
      if ($annCol->getValue('unique',false) || $annCol->getValue('primaryKey',false)) {
        $columnCommand .= ' UNIQUE';
      }
      if ($annCol->getValue('primaryKey',false)) {
        $columnCommand .= ' PRIMARY KEY';
      }
      $columnDescription['command'] = $columnCommand;
      $this->columns[$column] = $columnDescription;      
      $str .= $columnCommand;
      if ($annCol != end($anns)) {
        $str .= ',';
      }
    }
    // Indices
    foreach ($anns as $column => $annCol) {      
      if ($annCol->getValue('index',false)) {
        $indexName = strtoupper($column) . '_INDEX';
        $str .= ",\n\tINDEX {$indexName} ($column)";
        $this->columns[$column]['index'] = true;
        $this->columns[$column]['indexName'] = $indexName;
      }
    }    
    
    // Foreign keys
    foreach ($anns as $column => $annCol) {      
      if (count($ref = $annCol->getValue('references',array())) > 0) {          
        $depends = $ref['table'] == 'self' ? $this->tableName : $ref['table'];
        array_push($this->depends,$depends);        
        $str .= ",\n\t";
        $foreignKey = "FOREIGN KEY ($column) REFERENCES $depends({$ref['column']})";
        if (array_key_exists('onUpdate', $ref)) {
          $foreignKey .= ' ON UPDATE ' . $ref['onUpdate'];
        }
        if (array_key_exists('onDelete', $ref)) {
          $foreignKey .= ' ON DELETE ' . $ref['onDelete'];
        }
        $str .= $foreignKey;
        $this->columns[$column]['foreignKey'] = true;
        $this->columns[$column]['foreignKeyCommand'] = $foreignKey;
      }
    }

    $str .= "\n) {$this->charSet};\n";
    $this->tableCreationCommand = $str;
  }

}
