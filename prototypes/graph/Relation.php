<?php

/**
 * DECO Library
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\prototypes\graph;

use \deco\essentials\database\util as util;
use \deco\essentials\exception as exc;
use \deco\essentials\util as commonUtil;
use \deco\essentials\util\annotation as ann;

/**
 * Abstract for node
 *  
 */
abstract class Relation {

  // Use annotations with special features to database properties
  use \deco\essentials\traits\deco\AnnotationsIncludingDatabaseProperties;

  static public function initFromRelation($relation) {
    $obj = new static();
    $data = self::getDataFromRelation($relation);
    $obj->setValuesToObject($data);
    return $obj;
  }

  static public function getDataFromRelation($relation) {
    $data = $relation->values();
    return $data;
  }

  protected function setValuesToObject($data) {
    $anns = self::getForDatabaseProperties();
    $annCol = self::getAnnotationsForClass();
    foreach ($data as $key => $value) {
      if (!array_key_exists($key, $anns)) {
        continue;
      }
      util\Type::convertTo($value, $anns[$key], $annCol);
      $this->$key = $value;
    }
  }

  /**
    @call$obj->get{Property}(), $obj->get($property) // $force cannot be applied externally
   */
  protected function DECOget($property, $force = false) {
    $anns = self::getForDatabaseProperties();
    if (!$force) {
      if (!array_key_exists($property, $anns) ||
          !$anns[$property]->getValue('get', true)) {
        return null;
      }
    }
    if (!array_key_exists($property, $anns)) {
      $cls = get_called_class();
      throw new exc\SingleDataModel(
      array('msg' => "Property '$property' is not a database property and not gettable in '$cls'.",
      'params' => array('property' => $property, 'class' => $cls)));
    }
    if (!isset($this->$property)) {
      $value = self::getPropertyAnnotationValue($property, 'default', null);
    } else {
      $value = $this->$property;
    }
    return $value;
  }

  /**
    @call$obj->get() // returns all but non-lazy properties, $force cannot be applied externally
   */
  protected function DECOgetAll($force = false) {
    $anns = self::getForDatabaseProperties();
    $data = array();
    foreach ($anns as $property => $annCol) {
      if (!$force && !$annCol->getValue('get', true)) {
        continue;
      }
      $value = $this->DECOget($property, $force);
      $data[$property] = $value;
    }
    return $data;
  }

  public function __call($method, $arguments) {
    if (preg_match('#^get[A-Z]#', $method)) {
      $property = lcfirst(preg_replace('#^get#', '', $method));
      return $this->DECOget($property);
    } else if (preg_match('#^get$#', $method)) {
      if (count($arguments) == 1) {
        return $this->DECOget($arguments[0]);
      }
      return $this->DECOgetAll();
    } else {
      $cls = get_called_class();
      throw new exc\Magic(array('msg' => "Called unknown magic method '$method' in '$cls'."));
    }
  }

}
