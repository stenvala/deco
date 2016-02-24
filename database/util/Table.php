<?php

/**
 * DECO Framework
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\database\util;

/**
 * Model of database table
 */
class Table {

  /**
   * Character set of the table
   * 
   * @var string
   */
  protected $charSet;

  /**
   * Name of the table
   * 
   * @var string
   */
  protected $tableName;

  /**
   * Columns in the table
   * 
   * @var array, elemnts are of type Column
   */
  protected $columns = array();

  /**
   * Indexes in the table
   * 
   * @var array, elemnts are of type Index
   */
  protected $indexes = array();

  /**
   * Foreign keys in the table
   * 
   * @var array, elemnts are of type ForeignKey
   */
  protected $foreignKeys = array();

  /**
   * Construct table object
   * 
   * @param string $tableName
   * @param string $charSet
   */
  public function __construct($tableName, $charSet = 'DEFAULT CHARACTER SET utf8 COLLATE utf8_swedish_ci') {
    $this->tableName = $tableName;
    $this->charSet = $charSet;
  }

  /**
   * Get name of the table
   * 
   * @return string
   */
  public function getTableName() {
    return $this->tableName;
  }

  /**
   * Add column
   * 
   * @param \deco\essentials\database\util\Column $column
   */
  public function addColumn(Column $column) {
    $this->columns[$column->getName()] = $column;
  }

  /**
   * Add foreign key
   * 
   * @param \deco\essentials\database\util\ForeignKey $foreignKey
   */
  public function addForeignKey(ForeignKey $foreignKey) {
    $this->constraints[$foreignKey->getColumn()] = $foreignKey;
  }

  /**
   * Add index
   * 
   * @param \deco\essentials\database\util\Index $index
   */
  public function addIndex(Index $index) {
    $this->indexes[$index->getName()] = $index;
  }

  /**
   * Get table creation command excluding indexes and foreign keys
   * 
   * @return string
   */
  public function getTableCreationCommand() {
    $cmd = "CREATE TABLE IF NOT EXISTS {$this->tableName} (\n\t";
    $cmd .= implode(",\n\t", $this->getColumnCommands());
    $cmd .= ");\n";
    return $cmd;
  }

  /**
   * Get command for all the columns in the table
   * 
   * @return array
   */
  public function getColumnCommands() {
    $commands = array();
    foreach ($this->columns as $name => $col) {
      $commands[$name] = $this->getColumnCommand($name);
    }
    return $commands;
  }

  /**
   * Get command for given column
   * 
   * @param string $column
   * @return string
   */
  public function getColumnCommand($column) {
    $col = $this->columns[$column];
    $cmd = $col->getName() . ' ' . $col->getType();
    $cmd .= $col->getAllowNull() ? ' NULL' : ' NOT NULL';
    if ($col->hasDefault()) {
      $cmd .= " DEFAULT {$col->getDefault()}";
    }
    if ($col->getAutoIncrement()) {
      $cmd .= ' AUTO_INCREMENT';
    }
    if ($col->getUnique() || $col->getPrimaryKey()) {
      $cmd .= ' UNIQUE';
    }
    if ($col->getPrimaryKey()) {
      $cmd .= ' PRIMARY KEY';
    }
    return $cmd;
  }

  /**
   * Get all the foreign key creation commands via alter table statement
   * 
   * @return array, elements are strings
   */
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

  /**
   * Get all the index creation commands
   * 
   * @return array, elements are strings
   */
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

  /**
   * If given column exists in table
   * 
   * @param string $column
   * @return bool
   */
  public function hasColumn($column) {
    return array_key_exists($column, $this->columns);
  }

  /**
   * If table has foreign key
   * 
   * @param string $foreignKey
   * @return bool
   */
  public function hasForeignKey($foreignKey) {
    return array_key_exists($foreignKey, $this->foreignKeys);
  }

  /**
   * If table has index
   * 
   * @param string $index
   * @return bool
   */
  public function hasIndex($index) {
    return array_key_exists($index, $this->indexes);
  }

}
