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
 * Common database utilities for operating with tables
 */
class Database {

  /**
   * Execute a command for the database
   * 
   * @param array/string $command   
   */
  static public function execute($command) {
    if (is_array($command)) {
      foreach ($command as $cmd) {
        self::execute($cmd);
      }
      return;
    }
    $db = new \deco\essentials\database\FluentTableDB();
    $db->execute($command);
  }

  /**
   * Create tables to database
   * 
   * @param array of Table $tables
   */
  static public function createTables($tables) {
    $db = new \deco\essentials\database\FluentTableDB();
    $db->transactionStart();
    foreach ($tables as $table) {
      $db->execute($table->getTableCreationCommand());
    }
    $db->transactionCommit();
    // Somehow if I do these in a transaction, all are not done
    foreach ($tables as $table) {
      $db->execute(implode("\n", $table->getIndexCreationCommands()));
      $db->execute(implode("\n", $table->getForeignKeyCreationCommands()));
    }
  }

  /**
   * Delete tables from database
   * 
   * @param array of string $tables Default is all the tables in the database
   */
  static public function deleteTablesInDatabase($tables = null) {
    $db = new \deco\essentials\database\FluentTableDB();
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

  /**
   * Get names of tables from the database
   * 
   * @query SHOW TABLES
   * 
   * @return array of strings, list of tables
   */
  static public function getTablesInDatabase() {
    $db = new \deco\essentials\database\FluentTableDB();
    $data = $db->getAsArray('SHOW TABLES');
    $tables = array();
    foreach ($data as $value) {
      foreach ($value as $table) {
        array_push($tables, $table);
      }
    }
    return $tables;
  }

  /**
   * Get structure of table in database
   * 
   * @query DESCRIBE $table
   * 
   * @param string $table
   * 
   * @return array
   */
  static public function getTableStructure($table) {
    $db = new \deco\essentials\database\FluentTableDB();
    $data = $db->getAsArray("DESCRIBE $table");
    $ret = array();
    foreach ($data as $column) {
      $ret[$column['Field']] = $column;
    }
    return $ret;
  }

  /**
   * Get index of table
   * 
   * @query SHOW INDEX FROM $table
   * 
   * @param string $table
   * 
   * @return array
   */
  static public function getTableIndex($table) {
    $db = new \deco\essentials\database\FluentTableDB();
    $data = $db->getAsArray("SHOW INDEX FROM $table");
    $ret = array();
    foreach ($data as $column) {
      $ret[$column['Key_name']] = $column;
    }
    return $ret;
  }

  /**
   * Get table's foreign key data
   * 
   * @param string $table
   * 
   * @return array
   */
  static public function getTableForeignKeys($table) {
    $db = new \deco\essentials\database\FluentTableDB();
    $statement = 'select table_name,column_name,referenced_table_name,referenced_column_name,constraint_name'
        . ' from information_schema.key_column_usage'
        . ' where referenced_table_name is not null'
        . " and table_name = '$table'";
    return $db->getAsArray($statement, 'column_name');
  }

  /**
   * Create column(s) to existing table
   * 
   * @param string $table
   * @param string/array of strings $columnCommand   
   */
  static public function createColumn($table, $columnCommand) {
    if (is_array($columnCommand)) {
      foreach ($columnCommand as $cmd) {
        self::createColumn($table, $cmd);
      }
      return;
    }
    $db = new \deco\essentials\database\FluentTableDB();
    $db->execute("ALTER TABLE $table ADD $columnCommand");
  }

  /**
   * Update column(s)
   * 
   * @param string $table
   * @param string/array of strings $columnCommand   
   */
  static public function updateColumn($table, $columnCommand) {
    if (is_array($columnCommand)) {
      foreach ($columnCommand as $cmd) {
        self::updateColumn($table, $cmd);
      }
      return;
    }
    $db = new \deco\essentials\database\FluentTableDB();
    $db->execute("ALTER TABLE $table MODIFY $columnCommand");
  }

  /**
   * Drop column(s)
   * 
   * @param string $table
   * @param string/array of strings $column
   */
  static public function dropColumn($table, $column) {
    if (is_array($column)) {
      foreach ($column as $cmd) {
        self::dropColumn($table, $cmd);
      }
      return;
    }
    $db = new \deco\essentials\database\FluentTableDB();
    return $db->execute("ALTER TABLE $table DROP COLUMN $column");
  }

  /**
   * Drop foreign key(s)
   * 
   * @param string $table
   * @param string/array of strings $name Names of the foreign keys
   */
  static public function dropForeignKeys($table, $name) {
    if (is_array($name)) {
      foreach ($name as $nm) {
        self::dropForeignKeys($table, $nm);
      }
      return;
    }
    $db = new \deco\essentials\database\FluentTableDB();
    return $db->execute("ALTER TABLE $table DROP FOREIGN_KEY $name");
  }

  /**
   * Checks if given table exists in database
   * 
   * @param string $table
   * @return bool
   */
  static public function hasTable($table) {
    return in_array($table, self::getTablesInDatabase());
  }

  /**
   * Writes table creation command to a file
   * 
   * @param string $file File name where to write
   * @param array of Table $tables
   */
  static function writeTablesToFile($file, $tables) {
    $str = '';
    foreach ($tables as $table) {
      $str .= trim($table->getTableCreationCommand()) . "\n\n";
    }
    foreach ($tables as $table) {
      $str .= implode("\n", $table->getIndexCreationCommands());
      $str .= implode("\n", $table->getForeignKeyCreationCommands());
    }
    file_put_contents($file, $str);
  }

}
