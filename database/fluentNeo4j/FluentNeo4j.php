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
class FluentNeo4j {

  const CREATE_NODE = 1;
  const CREATE_RELATION = 2;
  const GET_NODE = 3;

  private $action;
  private $client;
  private $labels;
  private $data;
  private $from;
  private $to;
  private $where;

  //public function __construct(\Neoxygen\NeoClient\Client $client) {
  // 4.0
  public function __construct(\GraphAware\Neo4j\Client\Client $client) {
    $this->client = $client;
  }

  public function traverse() {
    return new Traverse($this->client);
  }

  public function from($label) {
    $this->action = self::GET_NODE;
    $this->labels($label);
    return $this;
  } 
  
  public function where($where) {
    $this->where = $where;
    return $this;
  }

  public function insertNode($data) {
    $this->action = self::CREATE_NODE;
    $this->data = $data;
    return $this;
  }

  public function insertRelation($fromId, $toId) {
    $this->action = self::CREATE_RELATION;
    $this->from = $fromId;
    $this->to = $toId;
    return $this;
  }

  public function labels($labels) {
    if (!is_array($labels)) {
      $labels = array($labels);
    }
    $this->labels = $labels;
    return $this;
  }

  public function execute() {
    switch ($this->action) {
      case self::CREATE_NODE:
        $cypher = 'CREATE (n:' . implode(':', $this->labels) . ') SET n += {infos} RETURN n';
        $result = $this->client->
            sendCypherQuery($cypher, array('infos' => $this->data));
        return $result->getRecords();
      case self::CREATE_RELATION:
        $cypher = "START n=node({$this->from}), m=node({$this->to})\n"
            . 'CREATE (n)-[r:' . implode(':', $this->labels) . "]->(m)\nRETURN r";
        $result = $this->client->sendCypherQuery($cypher);
        return $result->getRecords();
      case self::GET_NODE:
        if (count($this->where) == 1 && array_keys($this->where)[0] == 'nodeId') {
          $cypher = "START n=node({$this->where['nodeId']}) RETURN n";
          $result = $this->client->sendCypherQuery($cypher);
        } else {
          $cypher = 'MATCH (n:' . implode(':', $this->labels) . ') WHERE ' .
              $this->constructWhereStatement('n', $this->where) . ' RETURN n';
          $result = $this->client->sendCypherQuery($cypher, $this->where);
        }
        return $result->getRecords();
      default:
        error_log('QUERY TYPE NOT DETERMINED.');
    }
  }

  public function run($cypher) {
    return $this->client->sendCypherQuery($cypher);
  }

  private function constructWhereStatement($node = 'n') {
    $val = array();
    foreach ($this->where as $key => $value) {
      array_push($val, "$node.$key = {" . $key . "}");
    }
    return implode(' AND ', $val);
  }

}
