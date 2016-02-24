<?php

namespace deco\essentials\traits\database;

trait FluentTableDB {
  
  private static $db = null;
  
  /**
   * @return: \deco\essentials\database\FluentTableDB
   */
  protected static function db(){
    if (is_null(self::$db)) {
      self::$db = new \deco\essentials\database\FluentTableDB();
    }
    return self::$db;
  }
  
}
