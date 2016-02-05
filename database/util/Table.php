<?php

namespace deco\essentials\database\util;

class Table {

  protected $charSet;
  protected $tableCreationCommand = null;
  protected $tableName;
  protected $depends = array();
  protected $columns = array();

  public function __construct($tableName, $charSet = 'DEFAULT CHARACTER SET utf8 COLLATE utf8_swedish_ci') {
    $this->tableName = $tableName;
    $this->charSet = $charSet;
  }

  public function doesDependOn($table) {
    return in_array($table, $this->depends);
  }

  public function getTableName() {
    return $this->tableName;
  }

  public function getTableCreationCommand() {
    return $this->tableCreationCommand;
  }

  public function hasColumn($column) {
    return array_key_exists($column, $this->columns);
  }

  public function getColumnCommand($column) {
    return $this->columns[$column]['command'];
  }

  public function getColumns() {
    return array_keys($this->columns);
  }

  public function isIndex($column) {
    if (array_key_exists('index', $this->columns[$column])) {
      return true;
    }
    return false;
  }

  public function getIndexCreationCommand($column) {
    return 'CREATE INDEX ' . $this->columns[$column]['indexName'] .
        ' ON ' . $this->getTableName() . ' (' . $column . ')';
  }
  
  public function isForeignKey($column){
    if (array_key_exists('foreignKey', $this->columns[$column])) {
      return true;
    }
    return false;
  }
  
  public function getForeignKeyCreationCommand($column) {
    return 'ALTER TABLE ' . $this->getTableName() . 
        ' ADD CONSTRAINT FOREIGN_KEY_' . $column . ' '
        . $this->columns[$column]['foreignKeyCommand'];
  }

}
