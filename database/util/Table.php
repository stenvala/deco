<?php

namespace deco\essentials\database\util;

class Table {

  protected $charSet;
  protected $tableName;
  protected $columns = array();
  protected $indexes = array();
  protected $constraints = array();

  public function __construct($tableName, $charSet = 'DEFAULT CHARACTER SET utf8 COLLATE utf8_swedish_ci') {
    $this->tableName = $tableName;
    $this->charSet = $charSet;
  }

  public function getTableName() {
    return $this->tableName;
  }

  public function addColumn(Column $column) {
    $this->columns[$column->getName()] = $column;
  }

  public function addForeignKey(ForeignKey $constraint) {
    $this->constraints[$constraint->getColumn()] = $constraint;
  }

  public function addIndex(Index $index) {
    $this->indexes[$index->getName()] = $index;
  }

  public function getTableCreationCommand() {
    // CREATE TABLE
    $cmd = "CREATE TABLE IF NOT EXISTS {$this->tableName} (\n\t";
    $cmd .= implode(",\n\t", $this->getColumnCommands());
    $cmd .= ");\n";
    return $cmd;
  }

  public function getColumnCommands() {
    $commands = array();
    foreach ($this->columns as $name => $col) {
      $commands[$name] = $this->getColumnCommand($name);
    }
    return $commands;
  }

  public function getColumnCommand($column) {
    $col = $this->columns[$column];
    $cmd = $col->getName() . ' ' . $col->getType();
    $cmd .= $col->getAllowNull() ? ' NULL' : ' NOT NULL';
    if ($col->hasDefault())
      $cmd .= " DEFAULT {$col->getDefault()}";
    if ($col->getAutoIncrement())
      $cmd .= ' AUTO_INCREMENT';
    if ($col->getUnique() || $col->getPrimaryKey())
      $cmd .= ' UNIQUE';
    if ($col->getPrimaryKey())
      $cmd .= ' PRIMARY KEY';
    return $cmd;
  }

  public function getForeignKeyCreationCommands() {
    $commands = array();
    foreach ($this->constraints as $con) {
      $cmd = "ALTER TABLE {$this->tableName} ADD" .
          " FOREIGN KEY {$con->getName()} ({$con->getColumn()})" .
          " REFERENCES {$con->getTable()} ({$con->getForeignColumn()})" .
          " ON DELETE {$con->getOnDelete()}" .
          " ON UPDATE {$con->getOnUpdate()};\n";
      $commands[$con->getColumn()] = $cmd;
    }
    return $commands;
  }

  public function getIndexCreationCommands() {
    $commands = array();
    foreach ($this->indexes as $index) {
      $cols = '(' . implode(',', $index->getColumns()) . ')';
      $cmd = "CREATE {$index->getKind()} INDEX {$index->getName()}" .
          " ON {$this->tableName} $cols;\n";
      $commands[$index->getName()] = $cmd;
    }
    return $commands;
  }

  public function hasColumn($column) {
    return array_key_exists($column, $this->columns);
  }

  public function hasForeignKey($index) {
    return array_key_exists($index, $this->indexes);
  }

  public function hasIndex($index) {
    return array_key_exists($index, $this->indexes);
  }

}
