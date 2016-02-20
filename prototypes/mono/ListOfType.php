<?php

namespace deco\essentials\prototypes\mono;

/**
 * @contains: abstract
 */
abstract class ListOfType extends ListOf {

  public function __construct() {
    $cls = self::getClassAnnotationValue('contains');
    parent::__construct($cls);
    if (func_num_args() > 0) {
      call_user_func_array(array($this, 'init'), func_get_args());
    }
  }    

  static public function __callStatic($name, $args) {
    $cls = self::getClassAnnotationValue('contains', false);
    if ($cls === false) {
      print "Cannot call static on '" . get_called_class() . "'. Contain not defined.\n";
      die();
    }    
    return forward_static_call_array(array($cls, $name), $args);
  }

}
