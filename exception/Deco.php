<?php

namespace deco\essentials\exception;

class Deco extends Base {

  public function __construct($dict = array()) {
    $dict['type'] = 'Deco';
    parent::__construct($dict);    
  }
}
  