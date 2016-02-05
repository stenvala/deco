<?php

namespace deco\essentials\prototypes\mono;

use \deco\essentials\exception as exc;

abstract class ServiceOnCollection {

  use \deco\essentials\traits\deco\Annotations;

use \deco\essentials\traits\database\FluentMariaDB;

  protected $collection = array();

  private function __construct() {
    
  }

  public static function initBy($where, $fromKey = null, $fromValue = null, $limit = null) {
    $obj = new static();
    $table = self::getTable();
    $instanceOf = self::getClassAnnotationValue('contains');
    $cls = self::getInstancesDatabaseClass();
    $columns = $cls::getDatabaseHardColumnNames();
    $sort = $cls::getDatabaseSortColumns();    
    $query = $obj::db()->fluent()
            ->from($table)->select(null)->select($columns)
            ->where($where);
    if (!is_null($fromKey)) {
      $condition = '>';
      foreach ($sort as $column) {
        if ($column == $fromKey) {
          break;
        } elseif (($fromKey . ' desc') == $column) {
          $condition = '<';
          break;
        }
      }
      $query = $query->where("$fromKey $condition ?", $fromValue);
    }
    if (count($sort) > 0) {
      foreach ($sort as $value){        
        $query = $query->orderBy($value);
      }
    }
    if (!is_null($limit)) {
      $query = $query->limit($limit);
    }
    $data = self::db()->getAsArray($query->execute());
    foreach ($data as $row) {
      $obj->collection = array_merge($obj->collection,array($row['id'] => $instanceOf::initFromRow($row)));      
    }
    return $obj;
  }

  public function add($data) {
    $cls = self::getInstancesDatabaseClass();
    $new = $cls::create($data);
    $id = $new->getId();
    $instanceOf = self::getClassAnnotationValue('contains');
    $this->collection[$id] = new $instanceOf($id);
    return $this->collection[$id];
  }

  public function addReferencesTo($obj, $data) {
    $mainTable = $obj::getTable();
    $collectionCls = self::getInstancesDatabaseClass();
    $propertyAnnotation = $collectionCls::getPropertyAnnotationsForForeignKeyReferringTo($mainTable);
    $ref = $propertyAnnotation->get('references');
    $foreignKey = $collectionCls::propertyNameToDatabaseColumn($propertyAnnotation->name);
    $foreignValue = $obj->get($ref->value['column']);
    $data[$foreignKey] = $foreignValue;
    return $this->add($data);
  }

  public function get() {
    $data = array();
    foreach ($this->collection as $key => $value){
      $data = array_merge($data, array($key => $value->get()));
    }    
    return $data;
  }

  public function getHard() {
    $data = array();
    foreach ($this->collection as $key => $value){
      $data = array_merge($data, array($key => $value->getHard()));
    }    
    return $data;
  }

  public function hasObjectWithId($value) {
    return $this->hasObjectWith('id', $value);
  }

  public function hasObjectWith($property, $value) {
    if ($property == 'id') {
      return array_key_exists($value, $this->collection);
    }
    $instanceOf = self::getClassAnnotationValue('contains');
    $singleService = '\deco\essentials\prototypes\mono\ServiceOnRow';
    foreach ($this->collection as $obj) {
      if ($instanceOf::isSubClassOf($singleService)) {
        if ($obj->instance()->get($property) == $value) {
          return true;
        }
      } else if ($obj->get($property) == $value) {
        return true;
      }
    }
    return false;
  }

  public function deleteAll() {
    foreach ($this->collection as $obj) {
      $obj->delete();
    }
    $this->collection = array();
  }

  public function deleteById($id) {
    if (!array_key_exists($id, $this->collection)) {
      throw new exc\Database(array('msg' => "Collection of '{$this->instancesOf}' does not have object with id '$id'"));
    }
    $this->collection[$id]->delete();
    unset($this->collection[$id]);
  }

  public static function getInstancesDatabaseClass() {
    $instanceOf = self::getClassAnnotationValue('contains');
    $singleService = '\deco\essentials\prototypes\mono\ServiceOnRow';
    if ($instanceOf::isSubClassOf($singleService)) {
      return $instanceOf::getClassAnnotationValue('contains');      
    }
    return $instanceOf;
  }

  public static function getTable() {
    $cls = self::getInstancesDatabaseClass();
    return $cls::getTable();
  }

  public static function getReferenceToClass($mainCls){
    $instance = self::getInstancesDatabaseClass();
    return $instance::getReferenceToClass($mainCls);
  }
  
  static function __callStatic($method, $arguments) {
    if ($method == 'initBy') {
      return forward_static_call_array(array(__CLASS__, "DECOinitBy"), $arguments);
    } else {
      $cls = get_called_class();
      throw new exc\Magic(array('msg' => "Called unknown magic method '$method' in '$cls'."));
    }
  }

}
