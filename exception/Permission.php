<?php

namespace deco\essentials\exception;

class Permission extends Base {

  public function __construct($dict = array()) {
    $dict['type'] = 'Permission';
    parent::__construct($dict);    
  }
}
  