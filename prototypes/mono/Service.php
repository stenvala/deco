<?php

namespace deco\essentials\prototypes\mono;

use \deco\essentials\exception as exc;
use \deco\essentials\util\annotation as ann;

abstract class Service {

  use \deco\essentials\traits\deco\Annotations;

use \deco\essentials\traits\database\FluentMariaDB;

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
      $this->$property = new $cls(array($args[1] => $args[0]));
    }
  }

  /**
   * @call: $cls::initFromRow($value)
   * @return: instance of self   
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
   * @call: $obj->initFromRow{Property}($value), $obj->initFromRow($property,$value)
   * @return: instance of inited object
   * @throws: Deco, SingleDataModel
   */
  protected function DECOinitFromRow($property, $data) {
    $cls = self::getPropertyAnnotationValue($property, 'contains');
    if (self::getPropertyAnnotationValue($property, 'collection', false)) {
      $obj = $cls::initFromRow($data);
      if (!isset($this->$property)) {
        $this->$property = new ListOf($cls);
      }
      $obj = $this->$property->add($obj);
    } else {
      $this->$property = $cls::initFromRow($data);
      $obj = $this->$property;
    }
    return $obj;
  }

  public function load($recursionDepth = 0, $disallow = array()) {
    if ($recursionDepth < 0) {
      return;
    }
    $anns = self::getAnnotationsForProperties();
    foreach ($anns as $property => $annCol) {
      $cls = $annCol->getValue('contains', false);
      if ($cls !== false) {
        $table = $cls::getTable();
        if (is_array($disallow) && in_array($table, $disallow)) {
          continue;
        }
        if (is_array($disallow)) {
          array_push($disallow, $table); // prevents cycles
        }
      }
      if (!isset($this->$property)) {
        $this->DECOloadProperty($property);
        if (is_object($this->$property)) {
          $this->$property->load($recursionDepth - 1, $disallow);
        }
      }
    }
    return $disallow;
  }

  /**
   * @call: $obj->load{Property}(), $obj->load($property, [property, value pairs for database query])
   * @return: instance of inited object (could be also value if query is value)   
   */
  protected function DECOloadProperty($property, $guide = null) {
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
      if (array_key_exists('orderBy',$query)){
        $q = $q->orderBy($query['orderBy']);
      }
      if (array_key_exists('limit',$query)){
        $q = $q->limit($query['limit']);
      }
      $data = self::db()->get($q->execute());
      $type = self::getPropertyAnnotationValue($property, 'type', false);      
      if ($type == 'array') {
        $this->$property = $data;
      } else {
        $this->$property = $data[$property];
        if ($type !== false) {
          settype($this->$property, $type);
        }
      }
      return $this->$property;
    }
    $cls = self::getPropertyAnnotationValue($property, 'contains');
    $annCol = self::getMasterAnnotationCollection();
    $masterCls = $annCol->getValue('contains');
    $masterProperty = $annCol->reflector->name;
    if (!self::getPropertyAnnotationValue($property, 'collection', false) &&
        !self::getPropertyAnnotationValue($property, 'parent', false)
    ) {
      $foreign = $masterCls::getReferenceToClass($cls);
      $masterValue = $this->$masterProperty->get($foreign['column']);
      $this->$property = new $cls($masterValue);
    } else {
      $foreign = $cls::getReferenceToClass($masterCls);
      $masterValue = $this->$masterProperty->get($foreign['parentColumn']);
      if (self::getPropertyAnnotationValue($property, 'parent', false)) {
        $this->$property = new $cls($masterValue, $foreign['column']);
      } else {
        $this->$property = new ListOf($cls);
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
        array_shift($args);
        return call_user_func_array(array($this->$obj, 'get'), $args);
      }
      return $this->$obj->get();
    }
    // go all through
    $data = array();
    $properties = self::getAnnotationsForProperties();
    foreach ($properties as $property => $annCol) {
      if (isset($this->$property)) {
        $key = self::getPropertyAnnotationValue($property, 'revealAs', $property);
        if (is_object($this->$property)) {
          $data[$key] = $this->$property->get();
        } else {
          $data[$key] = $this->$property;
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
          error_log($property);
          $data[$key] = $this->$property->getLazy();
        } else {
          error_log('VALUE: ' . $property);
          $data[$key] = $this->$property;
        }
      }
    }
    return $data;
  }

  /**
   * @call: $obj->$property()
   * @return: instance of object (could be also value if query is value)   
   */
  public function DECOgetInstance($property) {
    if (isset($this->$property)) {
      return $this->$property;
    }
    $this->DECOloadProperty($property);
    return $this->$property;
  }

  /**
   * @call: $cls::create($data)
   * @return: instance of self   
   */
  static public function create($data) {
    $obj = new static();
    return $obj->DECOcreate(self::getClassName(), $data);
  }

  /**
   * @call: $obj->create{Property}($data), $obj->create($property,$data) 
   * @return: instance of self   
   */
  protected function DECOcreate($property, $data) {
    $cls = self::getPropertyAnnotationValue($property, 'contains');
    $this->$property = $cls::create($data);
    return $this;
  }

  public function set($data) {
    return $this->DECOset(self::getClassName(), $data);
  }

  /**
   * @call: $obj->set{Property}($data), $obj->set($property,$data) 
   * @return: instance of set data
   */
  protected function DECOset($property, $data) {
    return $this->$property->set($data);
  }

  /**
   * @call: $obj->add{Property}($data), $obj->add($property,$data) // this is for adding to list of data
   * @return: instance of added data
   */
  protected function DECOadd($property, $data) {
    $cls = self::getPropertyAnnotationValue($property, 'contains');
    if (!isset($this->$property)) {
      $this->$property = new ListOf($cls);
    }
    $annCol = self::getMasterAnnotationCollection();
    $masterCls = $annCol->getValue('contains');
    $masterProperty = $annCol->reflector->name;
    $foreign = $cls::getReferenceToClass($masterCls);
    $data[$foreign['column']] = $this->$masterProperty->get($foreign['parentColumn']);
    return $this->$property->create($data);
  }

  static protected function getMasterAnnotationCollection() {
    $property = self::getClassName();
    return self::getPropertyAnnotations($property);
  }

  static protected function getMasterClass() {
    $annCol = self::getMasterAnnotationCollection();
    return $annCol->getValue('contains');
  }

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
    } if (preg_match('#^load[A-Z][A-Za-z]*$#', $name)) {
      return call_user_func_array(array($this, 'DECOloadProperty'), array_merge(array(preg_replace('#^load#', '', $name)), $args));
    } if (preg_match('#^set[A-Z][A-Za-z]*$#', $name)) {
      $property = preg_replace('#^set#', '', $name);
      if (self::getPropertyAnnotationValue($property, 'collection', false)) {
        return $this->$property->set($args[0], $args[1]);
      }
      return $this->DECOset($property, $args[0]);
    } if (preg_match('#^initFromRow$#', $name)) {
      return $this->DECOinitFromRow($args[0], $args[1]);
    } else if (preg_match('#^initFromRow[A-Z][A-Za-z]*$#', $name)) {
      return $this->DECOinitFromRow(preg_replace('#^initFromRow#', '', $name), $args[0]);
    } if (preg_match('#^add[A-Z][A-Za-z]*$#', $name)) {
      return $this->DECOadd(preg_replace('#^add#', '', $name), $args[0]);
    } if (preg_match('#^has[A-Z][A-Za-z]*$#', $name)) {
      $property = preg_replace('#^has#', '', $name);
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
    } if (array_key_exists($name, self::getAnnotationsForProperties())) {
      if (count($args) == 0) {
        return $this->DECOgetInstance($name);
      } else {
        return $this->$name->getList($args[0]);
      }
    } else {
      return call_user_func_array(array($this->master(), $name), $args);
    }
  }

  static public function __callStatic($name, $args) {
    if ($name == 'initFromRow') {
      return self::DECOinitMasterFromRow($args[0]);
    } else { // delegate to master
      $annCol = self::getMasterAnnotationCollection();
      $cls = $annCol->getValue('contains');
      return forward_static_call_array(array($cls, $name), $args);
    }
  }

}
