<?php

namespace deco\essentials\traits\database;

trait FluentMariaDB {
  
  private static $db = null;
  
  /**
   * @return: \deco\essentials\database\FluentMariaDB
   */
  protected static function db(){
    if (is_null(self::$db)) {
      self::$db = new \deco\essentials\database\FluentMariaDB();
    }
    return self::$db;
  }
  
}
