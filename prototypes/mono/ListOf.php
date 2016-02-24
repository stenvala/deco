<?php

namespace deco\essentials\prototypes\mono;

class ListOf {

  use \deco\essentials\traits\deco\AnnotationsForClass;

use \deco\essentials\traits\database\FluentTableDB;

  private $sort = array();
  private $objects = array();
  private $instance = null;

  public function __construct($instance) {
    $this->instance = $instance;
  }

  public function add($obj) {    
    $id = $obj->get('id');
    if (!array_key_exists($id, $this->objects)) {
      $this->objects[$id] = $obj;
      array_push($this->sort, $id);
    }
    return $this->objects[$id];
  }

  public function reset() {
    $this->sort = array();
    $this->objects = array();
  }

  public function create($data) {
    $cls = $this->instance;
    $obj = is_object($data) ? $data : $cls::create($data);    
    // $property = $cls::getClassName();
    $id = $obj->get('id');    
    array_push($this->sort, $id);           
    $this->objects[$id] = $obj;
    return $obj;
  }

  public function loadAll($recursionDepth = 0, $disallow = array()) {
    foreach ($this->objects as $obj) {
      $obj->loadAll($recursionDepth, $disallow);
    }
    return $disallow;
  }

  public function init() {
    $guide = \deco\essentials\util\Arguments::pvToArray(func_get_args());
    $cls = $this->instance;
    $table = $cls::getTable();
    // self::db()->fluent()->debug = true;
    $query = self::db()->fluent()
        ->from($table)
        ->select(null);
    if (array_key_exists('recursion', $guide)) {
      $query = static::createQueryRecursively($cls, $guide['recursion'], $query);
    } else {
      $columns = $cls::getDatabaseHardColumnNames();
      $query = $query->select(self::getSelectInJoin($table, $columns));
    }
    if (array_key_exists('where', $guide)) {
      $query = $query->where($guide['where']);
    }
    if (array_key_exists('orderBy', $guide)) {
      $query = $query->orderBy($guide['orderBy']);
    } else {
      $sort = $cls::getDatabaseSortColumns();
      if (count($sort)) {
        $query = $query->orderBy($sort);
      }
    }
    if (array_key_exists('limit', $guide)) {
      $query = $query->limit($guide['limit']);
    }
    if (array_key_exists('groupBy', $guide)) {
      $query = $query->groupBy($guide['groupBy']);
    }
    $data = self::db()->getAsArray($query->execute());    
    foreach ($data as $row) {      
      $id = $row[$table . '_id'];
      if (!array_key_exists($id, $this->objects)) {
        array_push($this->sort, $id);
        $objectData = self::getDataFromSelectFor($table, $row);
        $this->objects[$id] = $cls::initFromRow($objectData);
      }
      if (array_key_exists('recursion', $guide)) {
        self::initRecursively($this->objects[$id], $guide['recursion'], $row);
      }
    }
  }

  static private function getSelectInJoin($table, $columns) {
    $data = array();
    foreach ($columns as $col) {
      array_push($data, "$table.$col as {$table}_$col");
    }
    return $data;
  }

  static private function getDataFromSelectFor($table, &$data) {
    $ar = array();
    foreach ($data as $key => $value) {
      if (preg_match("#^{$table}_#", $key)) {
        $ar[preg_replace("#^{$table}_#", "", $key)] = $value;
        //unset($data[$key]);
      }
    }
    return $ar;
  }

  static private function initRecursively($parent, $children, $row) {        
    if (array_key_exists('init', $children)) {
      foreach ($children['init'] as $child) {
        $childSer = $parent::getPropertyAnnotationValue($child, 'contains');
        $table = $childSer::getTable();
        $objectData = self::getDataFromSelectFor($table, $row);        
        $obj = $parent->initFromRow($child, $objectData);
        //$obj = $parent->$child();
        if (array_key_exists($child, $children)) {
          self::initRecursively($obj, $children[$child], $row);
        }
      }
    }
  }

  private function createQueryRecursively($parent, $children, $query) {
    $table = $parent::getTable();
    if (array_key_exists('init', $children)) {
      foreach ($children['init'] as $child) {
        $childSer = $parent::getPropertyAnnotationValue($child, 'contains'); // preg_replace('#\\\\[A-Za-z]*$#', "\\$child", $parent);        
        $childTable = $childSer::getTable();        
        if ($childSer::getTable() != $parent::getTable()) {
          if (!$childSer::isListOfService() &&
              !$parent::getPropertyAnnotationValue($child, 'parent', false)) {
            $foreign = $parent::getReferenceToClass($childSer);
            $query = $query->innerJoin("$childTable ON $table.{$foreign['column']} = $childTable.{$foreign['parentColumn']}");
          } else {
            $foreign = $childSer::getReferenceToClass($parent);
            $query = $query->innerJoin("$childTable ON $childTable.{$foreign['column']} = $table.{$foreign['parentColumn']}");
          }
          if (array_key_exists($child, $children)) {
            $query = $this->createQueryRecursively($childSer, $children[$child], $query);
          }
        }
        // get columns
        $columns = $childSer::getDatabaseHardColumnNames();
        $query = $query->select(self::getSelectInJoin($childTable, $columns));
      }
    }
    // 
    if (array_key_exists('use', $children)) {
      foreach ($children['use'] as $child) {
        $childSer = $parent::getPropertyAnnotationValue($child, 'contains'); //preg_replace('#\\\\[A-Za-z]*$#', "\\$child", $parent);
        $childTable = $childSer::getTable();
        if ($childSer::getTable() != $parent::getTable()) {
          if (!$childSer::isListOfService() &&
              !$parent::getPropertyAnnotationValue($child, 'parent', false)) {
            $foreign = $parent::getReferenceToClass($childSer);
            $query = $query->innerJoin("$childTable ON $table.{$foreign['column']} = $childTable.{$foreign['parentColumn']}");
          } else {
            $foreign = $childSer::getReferenceToClass($parent);
            $query = $query->innerJoin("$childTable ON $childTable.{$foreign['column']} = $table.{$foreign['parentColumn']}");
          }
          if (array_key_exists($child, $children)) {
            $query = $this->createQueryRecursively($childSer, $children[$child], $query);
          }
        }
      }
    }
    return $query;
  }

  public function has($where) {
    if (!is_array($where)) {
      if (array_key_exists($where, $this->objects)) {
        return $this->objects[$where];
      }
      $this->init('where', array('id' => $where));
      if (array_key_exists($where, $this->objects)) {
        return $this->objects[$where];
      }
      return false;
    }
    foreach ($this->objects as $obj) {
      if ($obj->is($where)) {
        return $obj;
      }
    }
    $num = count($this->objects);
    $this->init('where', $where);
    if (count($this->objects) == $num) {
      return false;
    }
    foreach ($this->objects as $obj) {
      if ($obj->is($where)) {
        return $obj;
      }
    }
  }

  public function objects() {
    $objs = array();
    $args = func_get_args();
    if (count($args) > 0) {
      foreach ($this->sort as $id) {
        if ($this->objects[$id]->master()->is($args[0])) {
          array_push($objs, $this->objects[$id]);
        }
      }
      return $objs;
    }
    foreach ($this->sort as $id) {
      array_push($objs, $this->objects[$id]);
    }
    return $objs;
  }
   

  public function get() {
    $data = array();
    $args = func_get_args();
    if (count($args) > 0) {
      foreach ($this->sort as $id) {
        if ($this->objects[$id]->master()->is($args[0])) {
          array_push($data, $this->objects[$id]->get());
        }
      }
      return $data;
    }
    foreach ($this->sort as $id) {
      array_push($data, $this->objects[$id]->get());
    }
    return $data;
  }

  public function getLazy() {
    $data = array();
    $args = func_get_args();
    if (count($args) > 0) {
      foreach ($this->sort as $id) {
        if ($this->objects[$id]->master()->is($args[0])) {
          array_push($data, $this->objects[$id]->getLazy());
        }
      }
      return $data;
    }
    foreach ($this->sort as $id) {
      array_push($data, $this->objects[$id]->getLazy());
    }
    return $data;
  }

  public function set($where, $value) {
    if (!is_array($where)) {
      $this->objects[$where]->set($value);
    } else {
      foreach ($this->objects as $obj) {
        if ($obj->master()->is($where)) {
          $obj->set($value);
        }
      }
    }
  }

  public function sort($cb){
    uasort($this->objects, $cb);
    $this->sort = array_keys($this->objects);
  }
  
  public function __call($name, $args) {
    $data = null;
    foreach ($this->sort as $ind => $id) {
      $obj = call_user_func_array(array($this->objects[$id], $name), $args);
      if ($ind == 0 && is_object($obj)) {
        $instance = $this->instance;
        $cls = $instance::getPropertyAnnotationValue($name, 'contains', false);
        if ($data != false) {
          $data = new ListOf($cls);
        }
      } else if ($ind == 0) {
        $data = array();
      }
      if (is_object($obj)) {
        $data->add($obj);
      } else {
        array_push($data, $obj);
      }
    }
    return $data;
  }

}
