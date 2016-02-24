<?php

/**
 * DECO Framework
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\traits\database;

/**
 * Trait to include class method db which has access to database via FluentTableDB
 */
trait FluentTableDB {

  /**
   * Instance of database connection
   * 
   * @var \deco\essentials\database\FluentTableDB
   */
  private static $db = null;

  /**
   * Get access to database via this
   * 
   * @return \deco\essentials\database\FluentTableDB
   */
  protected static function db() {
    if (is_null(self::$db)) {
      self::$db = new \deco\essentials\database\FluentTableDB();
    }
    return self::$db;
  }

}
