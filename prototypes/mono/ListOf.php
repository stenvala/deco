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
 * List of services
 * 
 * Typically this is not directly used but actual monoidal products are 
 * inherited from ListOfType
 */
class ListOf {

  // Take class annotations to use
  use \deco\essentials\traits\deco\AnnotationsForClass;

// Take database connection to use
  use \deco\essentials\traits\database\FluentTableDB;

  /**
   * order of items is here (id's)   
   * 
   * @var array
   */
  private $sort = array();

  /**
   * This is a key value list where key is object id
   * 
   * @var array of instances
   */
  private $objects = array();

  /**
   * @type of instance
   * 
   * @var string
   */
  private $instance = null;

  /**
   * In constructor empty list is initiated by only passing the name of the service that this list contains
   * 
   * @param type $instance
   */
  public function __construct($instance) {
    $this->instance = $instance;
  }

  /**
   * Add existing object to the end of list. If this exists it is not added.
   * 
   * @param instance $obj
   * 
   * @return inserted object
   */
  public function add($obj) {
    $id = $obj->get('id');
    if (!array_key_exists($id, $this->objects)) {
      $this->objects[$id] = $obj;
      array_push($this->sort, $id);
    }
    return $this->objects[$id];
  }

  /**
   * Empty list
   * 
   * @return this
   */
  public function reset() {
    $this->sort = array();
    $this->objects = array();
    return $this;
  }

  /**
   * Create new instance from data and add to list
   * 
   * @param array $data Must fit to content class
   * 
   * @return instance Created object
   */
  public function create($data) {
    $cls = $this->instance;
    $obj = is_object($data) ? $data : $cls::create($data);
    // $property = $cls::getClassName();
    $id = $obj->get('id');
    array_push($this->sort, $id);
    $this->objects[$id] = $obj;
    return $obj;
  }

  /**
   * Load all data in objects of list recursively
   * 
   * @param integer $recursionDepth How deep to allow the recursion to go
   * @param array $disallowed Disallowed objects (by their table name) or false if recursion can go as far as it wants
   * 
   * @return array New list of disallowed objects (to prevent cyclic recursions)
   */
  public function loadAll($recursionDepth = 0, $disallow = array()) {    
    $temp = $disallow;
    foreach ($this->objects as $obj) {            
      $newDis = $obj->loadAll($recursionDepth, $temp);
      if (is_array($disallow)){
        $disallow = array_unique(array_merge($disallow,$newDis));
      }      
    }    
    return $disallow;
  }

  /**
   * Init list contents based on given guide of variable length property-value 
   * pairs for query constructor.
   * See documentation for details.
   * 
   * @return this
   */
  public function init() {
    $guide = \deco\essentials\util\Arguments::pvToArray(func_get_args());
    $cls = $this->instance;
    $table = $cls::getTable();
    $query = self::db()->fluent()
        ->from($table)
        ->select(null);
    if (array_key_exists('recursion', $guide)) {
      $query = self::createQueryRecursively($cls, $guide['recursion'], $query);
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
      // default sort is by container columns
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
    return $this;
  }

  /**
   * Constructs temporarily column names for table joins
   * 
   * @param string $table
   * @param array $columns
   * 
   * @return array
   */
  static private function getSelectInJoin($table, $columns) {
    $data = array();
    foreach ($columns as $col) {
      array_push($data, "$table.$col as {$table}_$col");
    }
    return $data;
  } 
  
  /**
   * Converts temporary column names for ones of a given table (i.e. removes 
   * prefixes from column names set by getSelectInJoin)
   * 
   * @param string $table
   * @param array $data
   * 
   * @return array Contains only data for given table
   */
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

  /**
   * Recursively init objects from query data
   * 
   * @param object $parent
   * @param array $children Contains recursion instructions
   * @param array $row Contains data of row that is now being entered
   */
  static private function initRecursively($parent, $children, $row) {
    if (array_key_exists('init', $children)) {
      foreach ($children['init'] as $child) {
        $childSer = $parent::getPropertyAnnotationValue($child, 'contains');
        $table = $childSer::getTable();
        $objectData = self::getDataFromSelectFor($table, $row);
        $obj = $parent->initFromRow($child, $objectData);
        if (array_key_exists($child, $children)) {
          self::initRecursively($obj, $children[$child], $row);
        }
      }
    }
  }

  /**
   * Construct recursively join query for tables that are either used to get 
   * data or only to guide the search via e.g. where
   * 
   * @param parent class $parent
   * @param array $children
   * @param \PDO::query $query
   * 
   * @return \PDO::query
   */
  private function createQueryRecursively($parent, $children, $query) {
    $table = $parent::getTable();
    $all = array();
    if (array_key_exists('init', $children)) {
      $all = array_merge($all, $children['init']);
    }
    if (array_key_exists('use', $children)) {
      $all = array_merge($all, $children['use']);
    }
    // Perform joins
    foreach ($all as $child) {
      $childSer = $parent::getPropertyAnnotationValue($child, 'contains');
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
    // Add select to query
    if (array_key_exists('init', $children)) {
      foreach ($children['init'] as $child) {
        $childSer = $parent::getPropertyAnnotationValue($child, 'contains');
        $childTable = $childSer::getTable();
        $columns = $childSer::getDatabaseHardColumnNames();
        $query = $query->select(self::getSelectInJoin($childTable, $columns));
      }
    }
    return $query;
  }

  /**
   * Test if given object (dased on its data) exists in list, if it does not 
   * exist by default, database is queried for it, and if found it is added
   * 
   * @param array or id  $where
   * 
   * @return boolean or object
   */
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
    // cannot go here
  }

  /**
   * Get all objects or those that match given criteria in the current order
   * Arguments can be single array for objects is function or property value pairs for the same purpose
   * 
   * @return array of objects
   */
  public function objects() {
    $objs = array();
    $args = func_get_args();
    if (count($args) > 0) {
      if (count($args) == 1) {
        $where = $args[0];
      } else {
        $where = array();
        for ($i = 0; $i < count($args); $i = $i + 2) {
          $where[$args[$i]] = $args[$i + 1];
        }
      }
      foreach ($this->sort as $id) {
        if ($this->objects[$id]->master()->is($where)) {
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

  /**
   * Display objects
   * 
   * @param int $indent
   */
  
  public function display($indent=0,$row=false){
    $ws = str_pad('',$indent);
    $ind = 0;
    foreach ($this->objects as $obj){
      if ($row){
        print "{$ws}[Element $ind]\n";
      }
      $obj->display($indent+2, $row, $ind === 0);
      $ind++;
    }
  }
  
  /**
   * Apply get to objects, can be filtered like objects
   * 
   * @return array of values
   */
  public function get() {
    $data = array();
    $args = func_get_args();
    if (count($args) > 0) {
      if (count($args) == 1) {
        $where = $args[0];
      } else {
        $where = array();
        for ($i = 0; $i < count($args); $i = $i + 2) {
          $where[$args[$i]] = $args[$i + 1];
        }
      }
      foreach ($this->sort as $id) {
        if ($this->objects[$id]->master()->is($where)) {
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

  /**
   * Apply getLazy to objects, can be filtered like objects
   * 
   * @return array of values
   */
  public function getLazy() {
    $data = array();
    $args = func_get_args();
    if (count($args) > 0) {
      if (count($args) == 1) {
        $where = $args[0];
      } else {
        $where = array();
        for ($i = 0; $i < count($args); $i = $i + 2) {
          $where[$args[$i]] = $args[$i + 1];
        }
      }
      foreach ($this->sort as $id) {
        if ($this->objects[$id]->master()->is($where)) {
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

  /**
   * Update objects
   * 
   * @param integer/array $where id of object or array that is matched agains objects is method
   * @param array $value Dictionary of those values to be set
   * 
   * @return this
   */
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
    return $this;
  }

  /**
   * Delete object
   * 
   * @return this
   */
  public function delete($id){
    $this->objects[$id]->delete();
    unset($this->object[$id]);
    foreach ($this->sort as $key => $value){
      if ($value == $id){
        unset($this->sort[$key]);
        break;
      }
    }
    $this->sort = array_values($this->sort);
    return $this;
  }
  
  /**
   * Perform custom sort to objects in the list
   * 
   * @param function $cb Handle to perform sort
   * 
   * @return this
   */
  public function sort($cb) {
    uasort($this->objects, $cb);
    $this->sort = array_keys($this->objects);
    return $this;
  }

  /**
   * Forward calls to all the objects in the list and return new list or array 
   * of data   
   *    
   * @return array or new list of objects
   */
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
