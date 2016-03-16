<?php

namespace deco\essentials\exception;

class Validation extends Base {

  private $fields = array();
  
  public function __construct($dict = array()) {
    $dict['type'] = 'validation';
    $this->fields = $dict['fields'];
    parent::__construct($dict);    
  }
  
  public function getValidationErrorFields(){
    return $this->fields;
  }
}
  