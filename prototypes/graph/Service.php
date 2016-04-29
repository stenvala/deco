<?php

/**
 * DECO Library
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\prototypes\graph;

use \deco\essentials\exception as exc;
use \deco\essentials\util\annotation as ann;

abstract class Service {

  use \deco\essentials\traits\database\FluentNeo4j,
      \deco\essentials\traits\deco\Annotations;

  /**
   * @contains abstract
   */
  protected $master;

  public function __construct() {
    $args = func_get_args();
    if (count($args) == 0) {
      return;
    }
    $cls = self::getMasterClass();
    if (count($args) == 1) {
      if ($args[0] instanceof $cls) {
        $this->master = $args[0];
      } else {
        $this->master = new $cls($args[0]);
      }
    } else if (count($args) == 2) {
      $this->master = new $cls($args[0], $args[1]);
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
        ->start('n', $obj1->get('nodeId'))
        ->start('m', $obj2->get('nodeId'))
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
    $cls = self::getMasterClass();
    if (count($nodes) != 1) {
      throw new exc\Deco(
      array('msg' => "Instance of '$cls' was not found based on query.",
      'params' => array('where' => $where)));
    }
    $obj = new static();
    $obj->master = $cls::initFromNode($nodes->values()[0]);
    return $obj;
  }

  /**
   * @call $cls::initFromNode($node)
   * @return instance of self   
   */
  public static function DECOinitMasterFromNode($node) {
    $cls = self::getMasterClass();
    $obj = new static();
    $obj->master = $cls::initFromNode($node);
    return $obj;
  }

  /**
   * @call $obj->initFromNode{Property}($node), $obj->initFromNode($property,$node)
   * @return instance of inited object
   * @throws Deco, SingleDataModel
   */
  protected function DECOinitFromNode($property, $node) {
    $cls = self::getPropertyAnnotationValue($property, 'contains');
    if ($cls::isListOfService()) {
      $obj = $cls::initFromNode($node);
      if (!isset($this->$property)) {
        $this->$property = new $cls();
      }
      return $this->$property->add($obj);
    } else {
      $this->$property = $cls::initFromNode($node);
      $obj = $this->$property;
    }
    return $obj;
  }

  /**
   * @call $obj->load{Property}(), $obj->load($property, property value pairs of guide for initialization TBD)
   * @return instance of inited object (could be also value if query is value)   
   */
  protected function DECOloadProperty() {
    $args = func_get_args();
    $property = $args[0];
    $cls = self::getPropertyAnnotationValue($property, 'contains');
    $annCol = self::getPropertyAnnotations($property);
    // check if there is directly query    
    if ($cypher = $annCol->getValue('query', false)) {      
      while (preg_match('#{([a-zA-Z]*)}#',$cypher,$matches)){
        $prop = $matches[1];
        $cypher = preg_replace("#\{$prop\}#",$this->master->get($matches[1]),$cypher);
      }      
      $results = self::db()->fluent()->run($cypher)->getRecords();
    } else { 
      // build query
      $relation = $annCol->getValue('relation');
      $label = $relation['label'];
      switch ($relation['direction']) {
        case 'OUTGOING':
          $direction = 'from';
          break;
        case 'INCOMING':
          $direction = 'to';
          break;
        default:
          $direction = 'undirected';
      }
      $query = self::db()->fluent()->traverse()
          ->start('n', $this->master()->get('nodeId'))
          ->with('n')
          ->match()
          ->node('n')
          ->$direction('r', $label)
          ->node('m')
          ->ret('m,r');
      $results = $query->execute();
    }
    if ($cls::isListOfService()) {
      if (!isset($this->$property)) {
        $this->$property = new $cls(); // when loaded, new is set
      }
      foreach ($results as $result) {
        $node = $result->values()[0];
        $obj = $this->$property->addNode($node);
        if (array_key_exists('saveRelations', $relation)) {
          $relVar = $relation['saveRelations'];
          $relClass = self::getPropertyAnnotationValue($relVar, 'type');
          $relation = $result->values()[1];
          $this->$relVar[$obj->getId()] = $relClass::initFromRelation($relation);
        }
      }
    } else {
      if (count($results)){
        $this->$property = $cls::initFromNode($results[0]->values()[0]);
      }
    }
    return $this->$property;
  }

  public function master() {
    return $this->master;
  }

  public function display($indent = 0, $row = false, $first = true) {
    if (!isset($this->master)) {
      return;
    }
    $this->master->display($indent, $row, $first);
    $properties = self::getAnnotationsForProperties();
    $ws = str_pad('', $indent);
    foreach ($properties as $property => $annCol) {
      if ($property == 'master' || !isset($this->$property)) {
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
    foreach ($properties as $property => $annCol) {
      if ($property == 'master' || !$annCol->getValue('get', true)) {
        continue;
      }
      if (isset($this->$property)) {
        $key = self::getPropertyAnnotationValue($property, 'revealAs', $property);
        if (is_object($this->$property)) {
          if (!is_null($fun = $annCol->getValue('getCall'))) {
            $data[$key] = $this->$fun();
          } else {
            $data[$key] = $this->$property->get();
          }
        } else {
          $data[$property] = $this->$property;
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
    $obj->DECOcreate('master', $data);
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
    return $this->DECOset('master', $data);
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

  static protected function getMasterClass() {
    return self::getPropertyAnnotationValue('master', 'contains');
  }

  public function __call($name, $args) {
    if (preg_match('#^get[A-Z][A-Za-z]*$#', $name)) {
      return call_user_func_array(array($this, 'get'), array_merge(array(lcfirst(preg_replace('#^get#', '', $name))), $args));
    } else if (preg_match('#^load[A-Z][A-Za-z]*$#', $name)) {
      return call_user_func_array(array($this, 'DECOloadProperty'), array_merge(array(lcfirst(preg_replace('#^load#', '', $name))), $args));
    } else if ($name == 'load') {
      return call_user_func_array(array($this, 'DECOloadProperty'), $args);
    } else if (preg_match('#^set[A-Z][A-Za-z]*$#', $name)) {
      $property = preg_replace('#^set#', '', $name);
      $cls = self::getPropertyAnnotationValue($property, 'contains');
      if ($cls::isCollectionisListOfService()) {
        return $this->$property->set($args[0], $args[1]);
      }
      return $this->DECOset($property, $args[0]);
    } else if (preg_match('#^initFromNode$#', $name)) {
      return $this->DECOinitFromNode($args[0], $args[1]);
    } else if (preg_match('#^initFromNode[A-Z][A-Za-z]*$#', $name)) {
      return $this->DECOinitFromNode(lcfirst(preg_replace('#^initFromNode#', '', $name)), $args[0]);
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
    if ($name == 'initFromNode') {
      return self::DECOinitMasterFromNode($args[0]);
    } else { // delegate to master
      $cls = self::getMasterClass();
      return forward_static_call_array(array($cls, $name), $args);
    }
  }

}
