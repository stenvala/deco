<?php

/**
 * DECO Library
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\robot;

/**
 * Builds automatically rest services from repositories
 * 
 */
class Rest {

  private $base; // inherit all rest services from these unless they already exist and are inherited from something else
  private $repositories = array();

  public function __construct($base) {
    $this->base = $base;
  }

  public function setRepositories($repositories) {
    $this->repositories = $repositories;
  }

  public function writeRestServices($namespace, $to) {
    foreach ($this->repositories as $repo) {
      $repo->writeRestService($namespace, $to, $this->base);
    }
  }

}
