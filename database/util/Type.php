<?php

/**
 * DECO Library
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\database\util;

use \deco\essentials\exception as exc;

/**
 * Is used to set type of value based on annotation
 */
class Type {

  /**
   * Converts variable to correct type according to its annotation collection
   * 
   * @param any &$value reference parameter   
   * @throws exc\Deco
   */
  public static function convertTo(&$value, \deco\essentials\util\annotation\AnnotationCollection $propertyAnnotations) {
    $type = $propertyAnnotations->getValue('type');
    if (is_null($value) || $value === 'NULL') {
      $value = null;
      return;
    }
    switch ($type) {
      case 'int':
      case 'integer':
      case 'string':
        settype($value, $type);
        break;
      case 'bool':
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        break;
      case 'timestamp':
        if (is_numeric($value)) {
          settype($value, 'integer');
        } else {
          $dt = new \DateTime($value);
          $value = $dt->getTimestamp();
        }
        break;
      case 'enum':
        if (func_num_args() == 3) {
          // Not using dynamic constants
          //$cls = $classAnnotations->reflector->getName();
          //$value = constant($cls . '::' . $value);
        }
        break;
      case 'date':
        break;
      case 'json':
        if (is_array($value)) {          
          $value = json_encode($value);          
        } else {
          $value = json_decode($value, true);
        }
        break;
      default:
        throw new exc\Deco(array('msg' => "Cannot convert variable '$value' to '$type'",
        'params' => array('type' => $type, 'value' => $value)));
    }
  }

}
