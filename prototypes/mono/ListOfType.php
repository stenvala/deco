<?php

/**
 * DECO Framework
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\prototypes\mono;

/**
 * List of services of the same type (i.e. monoidal product of same objects)
 * 
 * @contains abstract
 */
abstract class ListOfType extends ListOf {

  /**
   * With constructor data can be initiated with variable length property-value 
   * pairs for query constructor.
   * See documentation for details.
   */
  public function __construct() {
    $cls = self::getClassAnnotationValue('contains');
    parent::__construct($cls);
    if (func_num_args() > 0) {
      call_user_func_array(array($this, 'init'), func_get_args());
    }
  }

  /**
   * Forwards static call to container calss   
   */
  static public function __callStatic($name, $args) {
    $cls = self::getClassAnnotationValue('contains', false);
    return forward_static_call_array(array($cls, $name), $args);
  }

}
