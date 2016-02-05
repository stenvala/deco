<?php

namespace deco\essentials\exception;

class General extends Base {

  public function __construct($dict = array()) {
    $dict['type'] = 'General';
    parent::__construct($dict);    
  }
}
  