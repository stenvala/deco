<?php

namespace deco\essentials\exception;

class Database extends Base {

  public function __construct($dict = array()) {
    $dict['type'] = 'Database';
    parent::__construct($dict);
    if (array_key_exists('query', $this->params)) {
      //$this->params['debugDumpParams'] = $this->params['query']->debugDumpParams();
      //$this->params['errorInfo'] = $this->params['query']->errorInfo();
    }
  }
}
  