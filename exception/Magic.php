<?php

namespace deco\essentials\exception;

class Magic extends Base {

  public function __construct($dict = array()) {
    $dict['type'] = 'Magic';
    parent::__construct($dict);    
  }
}
  