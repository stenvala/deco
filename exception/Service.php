<?php

namespace deco\essentials\exception;

class Service extends Base {

  public function __construct($dict = array()) {
    $dict['type'] = 'Service';
    parent::__construct($dict);    
  }
}
  