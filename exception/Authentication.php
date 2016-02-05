<?php

namespace deco\essentials\exception;

class Authentication extends Base {

  public function __construct($dict = array()) {
    $dict['type'] = 'Authentication';
    parent::__construct($dict);    
  }
}
  