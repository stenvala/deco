<?php

namespace deco\essentials\database\util;

class Database {

  static public function execute($command) {
    if (is_array($command)){
      foreach ($command as $cmd) {
        self::execute($cmd);
      }
      return;
    }
    $db = new \deco\essentials\database\FluentMariaDB();
    $db->execute($command);
  }
  
  static public function createTables($tables) {
    $db = new \deco\essentials\database\FluentMariaDB();
    $db->transactionStart();
    foreach ($tables as $table) {
      $db->execute($table->getTableCreationCommand());
    }
    $db->transactionCommit();
    // somehow if I do these in the transaction, all are not done
    foreach ($tables as $table) {
      $db->execute(implode("\n",$table->getIndexCreationCommands()));
      $db->execute(implode("\n",$table->getForeignKeyCreationCommands()));
    }
  }

  static public function deleteTablesInDatabase($tables = null) {
    $db = new \deco\essentials\database\FluentMariaDB();
    $db->transactionStart();
    if (is_null($tables)) {
      $tables = self::getTablesInDatabase();
    }
    $db->execute("SET FOREIGN_KEY_CHECKS = 0");
    foreach ($tables as $table) {
      $db->execute("DROP TABLE $table");
    }
    $db->execute("SET FOREIGN_KEY_CHECKS = 1");
    $db->transactionCommit();
  }

  static public function getTablesInDatabase() {
    $db = new \deco\essentials\database\FluentMariaDB();
    $data = $db->getAsArray('SHOW TABLES');
    $tables = array();
    foreach ($data as $value) {
      foreach ($value as $table) {
        array_push($tables, $table);
      }
    }
    return $tables;
  }

  static public function getTableStructure($table) {
    $db = new \deco\essentials\database\FluentMariaDB();
    $data = $db->getAsArray("DESCRIBE $table");
    $ret = array();
    foreach ($data as $column) {
      $ret[$column['Field']] = $column;
    }
    return $ret;
  }

  static public function getTableIndex($table) {
    $db = new \deco\essentials\database\FluentMariaDB();
    $data = $db->getAsArray("SHOW INDEX FROM $table");
    $ret = array();
    foreach ($data as $column) {
      $ret[$column['Key_name']] = $column;
    }
    return $ret;
  }
  
  static public function getTableForeignKeys($table){
    $db = new \deco\essentials\database\FluentMariaDB();
    $statement = 'select table_name,column_name,referenced_table_name,referenced_column_name,constraint_name' 
    . ' from information_schema.key_column_usage'
    . ' where referenced_table_name is not null'
    . " and table_name = '$table'";     
    return $db->getAsArray($statement,'column_name');        
  }

  static public function createColumn($table, $columnCommand) {
    if (is_array($columnCommand)){
      foreach ($columnCommand as $cmd){
        self::createColumn($table, $cmd);
      }
      return;
    }
    $db = new \deco\essentials\database\FluentMariaDB();
    return $db->execute("ALTER TABLE $table ADD $columnCommand");
  }

  static public function updateColumn($table, $columnCommand) {
    if (is_array($columnCommand)){
      foreach ($columnCommand as $cmd){
        self::updateColumn($table, $cmd);
      }
      return;
    }    
    $db = new \deco\essentials\database\FluentMariaDB();
    return $db->execute("ALTER TABLE $table MODIFY $columnCommand");
  }

  static public function dropColumn($table, $column) {
    if (is_array($column)){
      foreach ($column as $cmd){
        self::dropColumn($table, $cmd);
      }
      return;
    }
    $db = new \deco\essentials\database\FluentMariaDB();
    return $db->execute("ALTER TABLE $table DROP COLUMN $column");
  }

  static public function dropForeignKeys($table, $name){
    if (is_array($name)){
      foreach ($name as $nm){
        self::dropForeignKeys($table, $nm);
      }
      return;
    }
    $db = new \deco\essentials\database\FluentMariaDB();
    return $db->execute("ALTER TABLE $table DROP FOREIGN_KEY $name");
  }
  
  static public function hasTable($table) {
    return in_array($table, self::getTablesInDatabase());
  }

  static function writeTablesToFile($file, $tables) {
    $str = '';
    foreach ($tables as $table) {
      $str .= trim($table->getTableCreationCommand()) . "\n\n";
    }
    foreach ($tables as $table) {
      $str .= implode("\n",$table->getIndexCreationCommands());
      $str .= implode("\n",$table->getForeignKeyCreationCommands());
    }
    file_put_contents($file, $str);
  }

}
