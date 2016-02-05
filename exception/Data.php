<?php

namespace deco\essentials\exception;

class Data extends Base {

  public function __construct($dict = array()) {
    $dict['type'] = 'Data';
    parent::__construct($dict);    
  }
}
  