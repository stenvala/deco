<?php

/**
 * DECO Framework
 * 
 * @link: https://github.com/stenvala/deco-essentials
 * @copyright: Copyright (c) 2016- Antti Stenvall
 * @license: https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\database;

use \deco\essentials\exception as exc;

/**
 * Database connection
 * 
 * This enables performing queries on the database and fetching data.
 * Uses FluentPDO for building queries (https://github.com/fpdo/fluentpdo).
 * Same connection is given to all the instances, thus mofications are needed if
 * more than single database is used. Uses utf8 characters and sets time_zone to 
 * UTC
 * .
 */
class FluentTableDB {

  /**
   * Connection to database
   * 
   * @var \PDO
   * @link http://php.net/manual/en/book.pdo.php
   */
  private static $conn = null;

  /**
   * Fluent PDO query builder
   * 
   * @var \FluentPDO
   * @link https://github.com/fpdo/fluentpdo
   */
  private static $fpdo = null;

  /**
   * Is performing queries inside a transaction 
   * 
   * @var bool
   */
  private static $transactionOngoing = false;

  /**
   * Automatically rollback on error
   * 
   * @var bool
   */
  private static $transactionAutoRollback = true;

  /**
   * Configure database connection
   * 
   * @param string $username Username to server
   * @param string $password Password to server
   * @param string $database Name of database for which to build the connection
   * @param string $server Server address
   * 
   * @throws exc\Database if connection initialization fails
   */
  public static function conf($username, $password, $database, $server = '127.0.0.1') {
    $dsn = "mysql:dbname=$database;host=$server";
    try {
      self::$conn = new \PDO($dsn, $username, $password);
      self::$conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      self::$conn->exec("set names utf8");
      self::$conn->exec("set time_zone='+00:00'");
      self::$fpdo = new \FluentPDO(self::$conn);
    } catch (\PDOException $e) {
      throw new exc\Database(
      array('msg' => 'Database initialization error.',
      'previous' => $e));
    }
  }

  /**
   * Get instance, configuration should be done beforehand, but can be done also here
   * 
   * @param string $username Username to server
   * @param string $password Password to server
   * @param string $database Name of database for which to build the connection
   * @param string $server Server address
   * 
   * @throws exc\Database If database is not configured and configuration data is not given
   */
  function __construct($username = null, $password = null, $database = null, $server = '127.0.0.1') {
    if (!is_null(self::$conn)) {
      return;
    }
    if (func_num_args() < 3) {
      throw new exc\Database(
      array('msg' => 'Configure database first.'));
    }
    self::conf($username, $password, $database, $server);
  }

  /**
   * Start transaction
   */
  public function transactionStart() {
    self::$conn->beginTransaction();
    self::$transactionOngoing = true;
  }

  /**
   * Commit transaction
   */
  public function transactionCommit() {
    if (self::$transactionOngoing) {
      self::$conn->commit();
      self::$transactionOngoing = false;
    }
  }

  /**
   * Rollback transaction
   */
  public function transactionRollBack() {
    if (self::$transactionOngoing) {
      self::$conn->rollBack();
      self::$transactionOngoing = false;
    }
  }

  /**
   * Rollback transaction on error
   */
  private function transactionRollBackOnError() {
    if (self::$transactionAutoRollback) {
      $this->transactionRollBack();
    }
  }

  /**
   * Set or get (without argument) transaction autorollback value   
   * 
   * @param bool $value set to given value   
   * 
   * @return value of current autorollback statuc 
   */
  public function transactionAutorollBack($value = null) {
    if (func_num_args() == 0) {
      return self::$transactionAutorollback;
    }
    self::$transactionAutoRollback = $value;
    return self::$transactionAutoRollback;
  }

  /**
   * Get FluentPDO querybuilder
   * 
   * @return \FluenPDO
   */
  public function fluent() {
    return self::$fpdo;
  }

  /**
   * Execute query on database
   * 
   * @param \PDO::query or string $query
   * 
   * @return \PDO::query (executed query)
   * 
   * @throws exc\Database if query cannot be executed
   */
  public function execute($query) {
    if (is_string($query)) {
      if (strlen($query) == 0) {
        return;
      }
      $query = self::$conn->prepare($query);
    }
    try {
      return $query->execute();
    } catch (\PDOException $e) {
      $this->transactionRollBackOnError();
      throw new exc\Database(array('msg' => 'Error in executeQuery.',
      'params' => array('query' => $query),
      'previous' => $e));
    }
  }

  /**
   * Get id of last insert
   * 
   * @return int
   */
  public function getLastInsertId() {
    return self::$conn->lastInsertId();
  }

  /**
   * Get results for executed query as a single associative array
   * 
   * @param \PDO::query or string $query If query is given it must have been executed
   * 
   * @return array
   */
  public function get($query) {
    if (is_string($query)) {
      $query = self::$conn->prepare($query);
      $this->execute($query);
    }
    return $query->fetch(\PDO::FETCH_ASSOC);
  }

  /**
   * Get results of query when there has been possibility to get multiple rows
   * 
   * @param \PDO::query or string $query If query is given it must have been executed
   * @param string $indexColumn use this column as an index to create new associative array, note if this is int, sort may be lost
   *
   * @return array
   */
  public function getAsArray($query, $indexColumn = null) {
    if (is_string($query)) {
      $query = self::$conn->prepare($query);
      $this->execute($query);
    }
    $ar = array();
    while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
      if (is_null($indexColumn)) {
        array_push($ar, $row);
      } else {
        $ar[$row[$indexColumn]] = $row;
      }
    }
    return $ar;
  }

  /**
   * Get single value from query
   * 
   * @param \PDO::query or string $query If query is given it must have been executed
   * 
   * @return value
   */
  public function getValue($query) {
    if (is_string($query)) {
      $query = self::$conn->prepare($query);
      $this->execute($query);
    }
    $row = $query->fetch(\PDO::FETCH_ASSOC);
    return array_pop($row);
  }

}
