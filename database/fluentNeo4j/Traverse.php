<?php

// Make this own repository one day

/**
 * Fluent query builder for Neo4j
 * 
 * @link to-appear
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license to-appeas
 */

namespace deco\essentials\database\fluentNeo4j;

/**
 * Perform queries for Neo4j database
 * 
 */
class Traverse {

  const START = 1;
  const WHERE = 2;
  const MATCH = 3;
  const RET = 4;
  const NODE = 5;
  const REL = 6;
  const WITH = 7;

  private $client;
  private $previous;
  private $stack = array();

  public function __construct(\GraphAware\Neo4j\Client\Client $client) {
    $this->previous = null;
    $this->client = $client;
  }

  public function start($nodeName, $id) {
    $str = $this->previous == self::START ? ', ' : 'START ';
    $str .= "$nodeName=node($id)";
    $this->previous = self::START;
    array_push($this->stack, $str);
    return $this;
  }

  public function match() {
    array_push($this->stack, ' MATCH');
    $this->previous = SELF::MATCH;
    return $this;
  }

  public function node($nodeName, $labels = array()) {
    $str = "($nodeName" . self::labels($labels) . ')';
    array_push($this->stack, $str);
    $this->previous = self::NODE;
    return $this;
  }

  public function to($relationName, $labels = array()) {
    self::arrayfy($labels);
    $str = "<-[$relationName" . self::labels($labels) . ']-';
    array_push($this->stack, $str);
    $this->previous = SELF::REL;
    return $this;
  }

  public function from($relationName, $labels = array()) {
    self::arrayfy($labels);
    $str = "-[$relationName" . self::labels($labels) . ']->';
    array_push($this->stack, $str);
    $this->previous = SELF::REL;
    return $this;
  }

  public function undirected($relationName, $labels = array()) {
    self::arrayfy($labels);
    $str = "-[$relationName" . self::labels($labels) . ']-';
    array_push($this->stack, $str);
    $this->previous = SELF::REL;
    return $this;
  }

  public function with($what) {
    $str = $this->previous == SELF::WITH ? ', ' : ' WITH ';
    $str .= $what;
    $this->previous = self::WITH;
    array_push($this->stack, $str);
    return $this;
  }

  public function ret($what) {
    $str = $this->previous == self::RET ? ', ' : ' RETURN ';
    $str .= $what;
    array_push($this->stack, $str);
    $this->previous = self::RET;
    return $this;
  }

  public function execute() {
    $cypher = implode('', $this->stack);
    // print "$cypher\n";
    return $this->client->sendCypherQuery($cypher)->getRecords();
  }

  private static function arrayfy(&$labels) {
    $labels = is_array($labels) ? $labels : array($labels);
  }

  private static function labels($labels = array()) {
    self::arrayfy($labels);
    return count($labels) > 0 ? ':' . implode(':', $labels) : '';
  }

}
