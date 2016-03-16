<?php

namespace deco\essentials\exception;

abstract class Base extends \Exception {

  protected $type = 'undefined';
  protected $translate;
  protected $params;

  public function __construct($dict = array()) {    
    if (!array_key_exists('msg', $dict)) {
      $dict['msg'] = ''; //get_called_class() . '-exception';
    }
    if (!array_key_exists('code', $dict)) {
      $dict['code'] = 400;
    }
    if (!array_key_exists('previous', $dict)) {
      $dict['previous'] = null;
    }    
    parent::__construct($dict['msg'], $dict['code'], $dict['previous']);
    if (array_key_exists('params', $dict)) {
      $this->params = $dict['params'];
    }
    if (array_key_exists('dict', $dict)) {
      $this->translate = $dict['dict'];
    }
    if (array_key_exists('type', $dict)) {
      $this->type = $dict['type'];
    }    
  }

  public function getParams() {
    return $this->params;
  }

  public function getDictionaryCode() {
    return $this->translate;
  }

  public function getType() {
    return $this->type;
  }

}
