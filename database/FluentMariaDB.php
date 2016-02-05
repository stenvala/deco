<?php

namespace deco\essentials\database;

use \deco\essentials\exception as exc;

class FluentMariaDB {

  private static $conn = null;
  private static $fpdo = null;
  private static $transactionOngoing = false;
  private static $transactionAutoRollback = true;

  public static function conf($username, $password, $database, $server = '127.0.0.1') {
    $dsn = "mysql:dbname=$database;host=$server";
    try {
      self::$conn = new \PDO($dsn, $username, $password);
      self::$conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      self::$conn->exec("set names utf8");
      self::$fpdo = new \FluentPDO(self::$conn);
      // self::$conn->exec("set time_zone='+00:00'"); // always return in UTC, unfortunately this works differently in osx and linux
    } catch (\PDOException $e) {
      throw new exc\Database(
      array('msg' => 'Database initialization error.',
      'previous' => $e));
    }
  }

  function __construct($username = null, $password = null, $database = null, $server = '127.0.0.1') {
    if (!is_null(self::$conn)) {
      return;
    }
    if (is_null($username) || is_null($password) || is_null($database)) {
      throw new exc\Database(
      array('msg' => 'Configure database first.'));
    }
    self::conf($username, $password, $database, $server);
  }

  /*
   * Transaction support
   */

  public function transactionStart() {
    self::$conn->beginTransaction();
    self::$transactionOngoing = true;
  }

  public function transactionCommit() {
    if (self::$transactionOngoing) {
      self::$conn->commit();
      self::$transactionOngoing = false;
    }
  }

  public function transactionRollBack() {
    if (self::$transactionOngoing) {
      self::$conn->rollBack();
      self::$transactionOngoing = false;
    }
  }

  private function transactionRollBackOnError() {
    if (self::$transactionAutoRollback) {
      $this->transactionRollBack();
    }
  }

  public function transactionAutorollBack($value = null) {
    if (is_null($value)) {
      return self::$ransactionAutorollback;
    }
    self::$transactionAutoRollback = $value;
  }

  /**
   * Fluent builder
   */
  public function fluent() {
    return self::$fpdo;
  }

  public function execute($query) {
    if (is_string($query)) {
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

  public function getLastInsertId() {
    return self::$conn->lastInsertId();
  }

  public function get($query) {
    if (is_string($query)) {
      $query = self::$conn->prepare($query);
      $this->execute($query);
    }
    return $query->fetch(\PDO::FETCH_ASSOC);
  }

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

  public function getValue($query) {
    if (is_string($query)) {
      $query = self::$conn->prepare($query);
      $this->execute($query);
    }
    $row = $query->fetch(\PDO::FETCH_ASSOC);
    return array_pop($row);
  }

}
