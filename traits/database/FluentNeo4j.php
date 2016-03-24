<?php

/**
 * DECO Library
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\traits\database;

/**
 * Trait to include class method db which has access to database via FluentNeo4j
 */
trait FluentNeo4j {

  /**
   * Instance of database connection
   * 
   * @var \deco\essentials\database\FluentNeo4j
   */
  private static $db = null;

  /**
   * Get access to database via this
   * 
   * @return \deco\essentials\database\FluentNeo4j
   */
  protected static function db() {
    if (is_null(self::$db)) {
      self::$db = new \deco\essentials\database\FluentNeo4jDB();
    }
    return self::$db;
  }

}
