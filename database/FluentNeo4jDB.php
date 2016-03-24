<?php

/**
 * DECO Library
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\database;

use \deco\essentials\exception as exc;

/**
 * Perform queries for Neo4j database
 * 
 */
class FluentNeo4jDB {

  /**
   * Connection to database
   * 
   * @var \GraphAware\Neo4j\Client
   * @link https://github.com/graphaware/neo4j-php-client
   */
  private static $client = null;

  public static function conf($username = null, $password = null, $server = 'localhost', $port = 7474) {
    try {
      // for 3.4
      /*
        self::$client = \Neoxygen\NeoClient\ClientBuilder::create()
        ->addConnection('default', 'http', $server, $port, true, $username, $password)
        ->build();
       */
      // for 4.0
      $credentials = is_null($username) ? "" : "$username:$password";
      self::$client = \GraphAware\Neo4j\Client\ClientBuilder::create()
          ->addConnection('default', "http://$credentials@$server:$port")
          ->build();
    } catch (\Exception $e) {
      throw new exc\Database(
      array('msg' => 'Database initialization error.',
      'previous' => $e));
    }
  }

  public function getClient() {
    return self::$client;
  }

  public function fluent() {
    return new \deco\essentials\database\fluentNeo4j\FluentNeo4j(self::$client);
  }

}
