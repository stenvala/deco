<?php

/**
 * DECO Library
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\prototypes\mono;

use \deco\essentials\exception as exc;
use \deco\essentials\util\annotation as ann;

abstract class GraphService {

  use \deco\essentials\traits\database\FluentNeo4j;

use \deco\essentials\traits\deco\Annotations;

  public function __construct() {
    $args = func_get_args();
    if (count($args) == 0) {
      return;
    }
    $annCol = self::getMasterAnnotationCollection();
    $property = $annCol->reflector->name;
    $cls = $annCol->getValue('contains');
    if (count($args) == 1) {
      if ($args[0] instanceof $cls) {
        $this->$property = $args[0];
      } else {
        $this->$property = new $cls($args[0]);
      }
    } else if (count($args) == 2) {
      $this->$property = new $cls($args[0], $args[1]);
    }
  }

  /**
   * Init object based on two other objects via traverse
   */
  public static function between($property1, $obj1, $property2, $obj2, $where = array()) {
    $annCol1 = self::getPropertyAnnotations($property1);
    $direction1 = $annCol1->getValue('direction') == 'from' ? 'to' : 'from';
    $label1 = $annCol1->getValue('relation');
    $annCol2 = self::getPropertyAnnotations($property2);
    $direction2 = $annCol2->getValue('direction');
    $label2 = $annCol2->getValue('relation');
    $master = self::getMasterClass();
    $masterLabels = $master::getLabels();
    $traverse = self::db()->fluent()->traverse()
        ->start('n', $obj1->get('id'))
        ->start('m', $obj2->get('id'))
        ->match()
        ->node('n')
        ->$direction1('r1', $label1)
        ->node('s', $masterLabels)
        ->$direction2('r2', $label2)
        ->node('m')
        ->ret('s');
    if (count($where)) {
      $traverse->where($where);
    }
    $nodes = $traverse->execute();
    $annCol = self::getMasterAnnotationCollection();
    $property = $annCol->reflector->name;
    $cls = $annCol->getValue('contains');
    if (count($nodes) != 1) {      
      throw new exc\Deco(
      array('msg' => "Instance of '$cls' was not found based on query.",
      'params' => array('where' => $where)));
    }
    $obj = new static();    
    $obj->$property = $cls::initFromNode($nodes[0]);
    return $obj;
  }

  /**
   * @call $cls::initFromRow($value)
   * @return instance of self   
   */
  public static function DECOinitMasterFromRow($data) {
    $annCol = self::getMasterAnnotationCollection();
    $cls = $annCol->getValue('contains');
    $property = $annCol->reflector->name;
    $obj = new static();
    $obj->$property = $cls::initFromRow($data);
    return $obj;
  }

  /**
   * @call $obj->initFromRow{Property}($value), $obj->initFromRow($property,$value)
   * @return instance of inited object
   * @throws Deco, SingleDataModel
   */
  protected function DECOinitFromRow($property, $data) {
    $cls = self::getPropertyAnnotationValue($property, 'contains');
    if ($cls::isListOfService()) {
      $obj = $cls::initFromRow($data);
      if (!isset($this->$property)) {
        $this->$property = new $cls();
      }
      return $this->$property->add($obj);
    } else {
      if (is_object($data)) {
        return $this->$property = $data;
      }
      $this->$property = $cls::initFromRow($data);
      $obj = $this->$property;
    }
    return $obj;
  }

  // load all data recursively 
  public function loadAll($recursionDepth = 0, $disallow = array(), $allow = array()) {
    if ($recursionDepth < 0) {
      return $disallow;
    }
    $anns = self::getAnnotationsForProperties();
    foreach ($anns as $property => $annCol) {
      $cls = $annCol->getValue('contains', false);
      if ($cls !== false) {
        $table = $cls::getTable();
        if (is_array($disallow) && in_array($table, $disallow)) {
          continue;
        }
        if (is_array($disallow) && !in_array($table, $allow)) {
          array_push($disallow, $table); // prevents cycles
        }
      }
      if (!isset($this->$property)) {
        $this->DECOloadProperty($property);
        if (is_object($this->$property)) {
          $this->$property->loadAll($recursionDepth - 1, $disallow, $allow);
        }
      }
    }
    return $disallow;
  }

  /**
   * @call $obj->load{Property}(), $obj->load($property, property value pairs of guide for initialization)
   * @return instance of inited object (could be also value if query is value)   
   */
  protected function DECOloadProperty() {
    $args = func_get_args();
    $property = $args[0];
    if (count($query = self::getPropertyAnnotationValue($property, 'query', array())) > 0) {
      $q = self::db()->fluent()->
              from($query['table'])->select(null)->select($query['columns']);
      if (array_key_exists('where', $query)) {
        foreach ($query['where'] as $key => $value) {
          if (preg_match('#^{([A-Za-z]*)}$#', $value, $matches)) {
            $query['where'][$key] = $this->master()->get($matches[1]);
          }
        }
        $q = $q->where($query['where']);
      }
      if (array_key_exists('orderBy', $query)) {
        $q = $q->orderBy($query['orderBy']);
      }
      if (array_key_exists('limit', $query)) {
        $q = $q->limit($query['limit']);
      }
      $data = self::db()->get($q->execute());
      $type = self::getPropertyAnnotationValue($property, 'type', false);
      if ($type == 'array') {
        $this->$property = $data;
      } else {
        $this->$property = array_pop($data);
        if ($type !== false) {
          settype($this->$property, $type);
        }
      }
      return $this->$property;
    }
    array_shift($args);
    if (count($args) == 0) {
      $args[0] = array();
    }
    $guide = is_array($args[0]) ? $args[0] : \deco\essentials\util\Arguments::pvToArray($args);
    $cls = self::getPropertyAnnotationValue($property, 'contains');
    $annCol = self::getMasterAnnotationCollection();
    $masterCls = $annCol->getValue('contains');
    $masterProperty = $annCol->reflector->name;
    $isCollection = $cls::isListOfService();
    if (!$isCollection &&
        !self::getPropertyAnnotationValue($property, 'parent', false)
    ) {
      $foreign = $masterCls::getReferenceToClass($cls);
      $masterValue = $this->$masterProperty->get($foreign['column']);
      $this->$property = new $cls($masterValue);
    } else {
      $foreign = $cls::getReferenceToClass($masterCls);
      $masterValue = $this->$masterProperty->get($foreign['parentColumn']);
      if (self::getPropertyAnnotationValue($property, 'parent', false)) {
        $this->$property = new $cls($foreign['column'], $masterValue);
      } else {
        $this->$property = new $cls();
        $ar = array('where', array($foreign['column'] => $masterValue));
        if (!is_null($guide)) {
          $ar = array_merge($ar, $guide);
        }
        call_user_func_array(array($this->$property, 'init'), $ar);
      }
    }
    return $this->$property;
  }

  public function master() {
    $annCol = self::getMasterAnnotationCollection();
    $property = $annCol->reflector->name;
    return $this->$property;
  }

  public function display($indent = 0, $row = false, $first = true) {
    $annCol = self::getMasterAnnotationCollection();
    $master = $annCol->reflector->name;
    if (!isset($this->$master)) {
      return;
    }
    $this->$master->display($indent, $row, $first);
    $properties = self::getAnnotationsForProperties();
    $ws = str_pad('', $indent);
    foreach ($properties as $property => $annCol) {
      if ($property == $master || !isset($this->$property)) {
        continue;
      }
      if (!is_object($this->$property)) {
        print "$ws- $property: {$this->$property}\n";
      } else {
        print "$ws* $property:\n";
        $this->$property->display($indent + 2, $row);
      }
    }
  }

  public function get() {
    $args = func_get_args();
    // recursion
    if (count($args) > 0) {
      // forward to master object
      if (count($args) == 1 && !array_key_exists($args[0], self::getAnnotationsForProperties())) {
        return $this->master()->get(lcfirst($args[0]));
      }
      // Get from given object
      $obj = $args[0];
      if (!isset($this->$obj)) {
        // throw
      }
      if (is_null($this->$obj)) {
        return null;
      }
      if (!is_object($this->$obj)) {
        return $this->$obj;
      }
      if (count($args) > 1) {
        unset($args[0]);
        return call_user_func_array(array($this->$obj, 'get'), array_values($args));
      }
      return $this->$obj->get();
    }    
    // first get all from this ndoe    
    $data = $this->master()->get();
    $properties = self::getAnnotationsForProperties();    
    $masterProperty = self::getClassName();
    foreach ($properties as $property => $annCol) {      
      if ($property == $masterProperty){
        continue;
      }
      if (isset($this->$property)) {
        $key = self::getPropertyAnnotationValue($property, 'revealAs', $property);
        if (is_object($this->$property)) {
          $data[$key] = $this->$property->get();
        } else {
          $data[$property] = $this->$property;
        }
      }
    }
    return $data;
  }

  public function getLazy() {
    $data = array();
    $properties = self::getAnnotationsForProperties();
    foreach ($properties as $property => $annCol) {
      $key = $annCol->getValue('revealAs', $property);
      if (isset($this->$property)) {
        if (is_object($this->$property)) {
          $data[$key] = $this->$property->getLazy();
        } else {
          $data[$key] = $this->$property;
        }
      }
    }
    return $data;
  }

  /**
   * @call $obj->$property()
   * @return instance of object (could be also value if query is value)   
   */
  public function DECOgetInstance($property) {
    if (isset($this->$property)) {
      return $this->$property;
    }
    $cls = self::getPropertyAnnotationValue($property, 'contains');
    if ($cls::isListOfService()) {
      return $this->$property = new $cls();
    }
    return $this->DECOloadProperty($property);
  }

  /**
   * @call $cls::create($data)
   * @return instance of self   
   */
  static public function create($data) {
    $obj = new static();
    $obj->DECOcreate(self::getClassName(), $data);
    return $obj;
  }

  /**
   * @call $obj->create{Property}($data), $obj->create($property,$data) 
   * @return instance of self   
   */
  protected function DECOcreate($property, $data) {
    $cls = self::getPropertyAnnotationValue($property, 'contains');
    if ($cls::isListOfService()) {
      if (!isset($this->$property)) {
        $this->$property = new $cls();
      }
      $node = $this->$property->create($data);
      // if (!is_object($data)) {
      // need to create also relation        
      $relation = self::getPropertyAnnotationValue($property, 'relation');
      $rel = self::db()->fluent();
      if (self::getPropertyAnnotationValue($property, 'direction') == 'to') {
        $rel->insertRelation($node->getId(), $this->master()->getId());
      } else {
        $rel->insertRelation($this->master()->getId(), $node->getId());
      }
      $rel->labels($relation)->execute();
      //}
      return $node;
    }
    return $this->$property = is_object($data) ? $data : $cls::create($data);
  }

  public function set($data) {
    return $this->DECOset(self::getClassName(), $data);
  }

  /**
   * @call $obj->set{Property}($data), $obj->set($property,$data) 
   * @return instance of set data
   */
  protected function DECOset($property, $data) {
    return $this->$property->set($data);
  }

  /**
   * @call $obj->deleteProperty(), $obj->delete($property) and for master $obj->delete()
   * note that data inconsistencies may arise   
   */
  protected function DECOdelete($property) {
    if (is_object($this->$property)) {
      $this->$property->delete();
    }
    unset($this->$property);
    return null;
  }

  static protected function getMasterAnnotationCollection() {
    $property = self::getClassName();
    return self::getPropertyAnnotations($property);
  }

  static protected function getMasterClass() {
    $annCol = self::getMasterAnnotationCollection();
    return $annCol->getValue('contains');
  }

  /* does exactly the same thing as loadProperty(guide)
    protected function DECOinitCollection($property, $guide = null) {
    $cls = self::getPropertyAnnotationValue($property, 'contains');
    if (func_num_args() == 1) {
    $this->$property = new ListOf($cls);
    } else {
    $this->$property = new ListOf($cls);
    call_user_func_array(array($this->$property, 'init'), $guide);
    }
    return $this->$property;
    }
   */

  public static function getTable() {
    $cls = self::getMasterClass();
    return $cls::getTable();
  }

  public static function getReferenceToClass($cls) {
    $masterCls = self::getMasterClass();
    return $masterCls::getReferenceToClass($cls);
  }

  public function __call($name, $args) {
    if (preg_match('#^get[A-Z][A-Za-z]*$#', $name)) {
      return call_user_func_array(array($this, 'get'), array_merge(array(preg_replace('#^get#', '', $name)), $args));
    } else if (preg_match('#^load[A-Z][A-Za-z]*$#', $name)) {
      return call_user_func_array(array($this, 'DECOloadProperty'), array_merge(array(preg_replace('#^load#', '', $name)), $args));
    } else if ($name == 'load') {
      return call_user_func_array(array($this, 'DECOloadProperty'), $args);
    } else if (preg_match('#^set[A-Z][A-Za-z]*$#', $name)) {
      $property = preg_replace('#^set#', '', $name);
      $cls = self::getPropertyAnnotationValue($property, 'contains');
      if ($cls::isCollectionisListOfService()) {
        return $this->$property->set($args[0], $args[1]);
      }
      return $this->DECOset($property, $args[0]);
    } else if (preg_match('#^initFromRow$#', $name)) {
      return $this->DECOinitFromRow($args[0], $args[1]);
    } else if (preg_match('#^initFromRow[A-Z][A-Za-z]*$#', $name)) {
      return $this->DECOinitFromRow(preg_replace('#^initFromRow#', '', $name), $args[0]);
    } else if (preg_match('#^add|create[A-Z][A-Za-z]*$#', $name)) {
      $name = preg_replace('#^add|create#', '', $name);
      if (!in_array($name, self::getPropertyNames())) {
        // should actually throw exception if length is no 1
        $annCol = array_values(self::getAnnotationsForPropertiesHavingAnnotation('singular', $name))[0];
        $name = $annCol->reflector->name;
      }
      return $this->DECOcreate($name, $args[0]);
    } else if (preg_match('#^has[A-Z][A-Za-z]*$#', $name)) {
      $property = preg_replace('#^has#', '', $name);
      if (!in_array($name, self::getPropertyNames())) {
        // should actually throw exception if length is no 1
        $annCol = array_pop(self::getAnnotationsForPropertiesHavingAnnotation('singular', $property));
        $property = $annCol->reflector->name;
      }
      // need to add foreign key property to search arguments unless it is id (i.e. not array)
      if (is_array($args[0])) {
        $childCls = self::getPropertyAnnotationValue($property, 'contains');
        $annCol = self::getMasterAnnotationCollection();
        $masterCls = $annCol->getValue('contains');
        $masterProperty = $annCol->reflector->name;
        $foreign = $childCls::getReferenceToClass($masterCls);
        $args[0][$foreign['column']] = $this->$masterProperty->get($foreign['parentColumn']);
      }
      if (!isset($this->$property)) {
        $this->DECOinitCollection($property);
      }
      return $this->$property->has($args[0]);
    } if (preg_match('#^delete[A-Z][A-Za-z]*$#', $name)) {
      $property = preg_replace('"^delete', '', $name);
      return $this->DECOdelete($property);
    } else if ($name == 'delete') {
      if (count($args) == 0) {
        return $this->DECOdelete(self::getClassName());
      }
      return $this->DECOdelete($args[0]);
    } else if (array_key_exists($name, self::getAnnotationsForProperties())) {
      return $this->DECOgetInstance($name);
    } else {
      return call_user_func_array(array($this->master(), $name), $args);
    }
  }

  static public function __callStatic($name, $args) {
    if ($name == 'initFromRow') {
      return self::DECOinitMasterFromRow($args[0]);
    } else { // delegate to master
      $cls = self::getMasterClass();
      return forward_static_call_array(array($cls, $name), $args);
    }
  }

}
