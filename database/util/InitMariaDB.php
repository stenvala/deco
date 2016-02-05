<?php

namespace deco\essentials\database\util;

class InitMariaDB {

  static private $db;
  private $tables = array();
  private $transactionOngoing = true;

  public function __construct() {
    self::$db = new \deco\essentials\database\FluentMariaDB();
  }

  public function transactionStart() {
    if ($this->transactionOngoing) {
      return;
    }
    $this->transactionOngoing = true;
    self::$db->transactionStart();
  }

  public function transactionCommit() {
    if ($this->transactionOngoing) {
      self::$db->transactionCommit();
      $this->transactionOngoing = false;
    }
  }

  public function get(){
    return self::$db;
  }
  
  public function createTables() {
    $this->transactionStart();
    foreach ($this->tables as $table) {
      self::$db->execute($table->getTableCreationCommand());
    }
    $this->transactionCommit();
  }

  public function deleteTablesInDatabase() {
    $this->transactionStart();
    $tables = $this->getTablesInDatabase();
    self::$db->execute("SET FOREIGN_KEY_CHECKS = 0");
    foreach ($tables as $table) {
      self::$db->execute("DROP TABLE $table");
    }
    self::$db->execute("SET FOREIGN_KEY_CHECKS = 0");
    $this->transactionStart();
  }

  public function getTablesInDatabase() {
    $data = self::$db->getAsArray('SHOW TABLES');
    $tables = array();
    foreach ($data as $value) {
      foreach ($value as $table) {
        array_push($tables, $table);
      }
    }
    return $tables;
  }

  public function getTableStructure($table){
    $data = self::$db->getAsArray("DESCRIBE $table");
    $ret = array();
    foreach ($data as $column){
      $ret[$column['Field']] = $column;
    }
    return $ret;
  }
  
  public function getTableIndex($table){
    $data = self::$db->getAsArray("SHOW INDEX FROM $table");    
    $ret = array();
    foreach ($data as $column){
      $ret[$column['Column_name']] = $column;
    }
    return $ret;
  }
  
  public function isIndexColumn($table, $column){
    
  }
  
  public function createColumn($table, $columnCommand){
    return self::$db->execute("ALTER TABLE $table ADD $columnCommand");
  }
  
  public function updateColumn($table, $columnCommand){    
   return self::$db->execute("ALTER TABLE $table MODIFY $columnCommand");
  }
  
  public function dropColumn($table, $column){
    return self::$db->execute("ALTER TABLE $table DROP COLUMN $column");
  }
  
  public function hasTable($table){
    return in_array($table, $this->getTablesInDatabase());
  }
  
  public function setTables($tables) {
    // actually order does ot matter because table creation is performed in a transaction
    usort($tables, array('self', 'order'));
    $this->tables = $tables;
  }

  public function writeTablesToFile($file) {
    $str = '';
    foreach ($this->tables as $table) {
      $str .= trim($table->getTableCreationCommand()) . "\n\n";
    }
    file_put_contents($file, $str);
  }

  static public function order(Table $tableA, Table $tableB) {
    if ($tableA->doesDependOn($tableB->getTableName())) {
      return 1;
    }
    if ($tableB->doesDependOn($tableA->getTableName())) {
      return -1;
    }
    return 0;
  }

}
