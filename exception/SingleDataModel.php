<?php

namespace deco\essentials\exception;

class SingleDataModel extends Base {

  public function __construct($dict = array()) {
    $dict['type'] = 'SingleDataModel';
    parent::__construct($dict);    
  }
}
  