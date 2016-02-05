<?php

namespace deco\essentials\exception;

class Annotation extends Base {

  public function __construct($dict = array()) {
    $dict['type'] = 'Annotation';
    parent::__construct($dict);    
  }
}
  