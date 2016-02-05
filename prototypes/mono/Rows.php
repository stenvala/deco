<?php

namespace deco\essentials\prototypes\mono;

use \deco\essentials\exception as exc;

class Rows {

  use \deco\essentials\traits\deco\Annotations;

  use \deco\essentials\traits\database\FluentMariaDB;

  protected $instancesOf;
  protected $collection = array();

  public function __construct($cls) {
    $this->instancesOf = $cls;
  }

  public function initBy($where, $fromKey = null, $fromValue = null, $limit = null) {
    $table = $this->getTable();
    $cls = $this->instancesOf;
    $columns = $cls::getDatabaseHardColumnNames();
    $sort = $cls::getDatabaseSortColumns();    
    $query = self::db()->fluent()
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
      $query = $query->orderBy($sort);
    }
    if (!is_null($limit)) {
      $query = $query->limit($limit);
    }        
    $query = $query->execute();        
    $data = self::db()->getAsArray($query);
    foreach ($data as $row) {
      $this->collection = array_merge($this->collection,array($row['id'] => $cls::initFromRow($row)));      
    }        
  }

  public function addReferencesTo($obj, $data) {
    $mainTable = $obj::getTable();
    $collectionCls = $this->instancesOf;
    $propertyAnnotation = $collectionCls::getPropertyAnnotationsForForeignKeyReferringTo($mainTable);
    $ref = $propertyAnnotation->get('references');
    $foreignKey = $collectionCls::propertyNameToDatabaseColumn($propertyAnnotation->name);
    $foreignValue = $obj->get($ref->value['column']);
    $data[$foreignKey] = $foreignValue;
    $this->add($data);
  }

  public function add($data) {
    $cls = $this->instancesOf;
    $new = $cls::create($data);
    $this->collection[$new->getId()] = $new;
  }

  public function get($property = null, $value = null) {
    $cb = function($obj) {
      return $obj->get();
    };
    return array_map($cb, $this->getObjects($property, $value));
  }

  public function getHard() {
    $cb = function($obj) {
      return $obj->getHard();
    };
    return array_map($cb, $this->getObjects());
  }

  public function getObjectById($id) {
    return $this->getObjectBy('id', $id);
  }

  public function getObjectBy($property, $value) {
    if ($property == 'id') {
      return $this->collection[$value];
    }
    foreach ($this->collection as $obj) {
      if ($obj->get($property) == $value) {
        return $obj;
      }
    }
    throw new exc\Database(array('msg' => "Collection of '{$this->instancesOf}' does not have object with (property,value) pair ($property,$value)"));
  }

  public function getObjects($property = null, $value = null) {
    if (is_null($property)) {
      return $this->collection;
    }
    $cb = function($obj) use ($property, $value) {
      return $obj->get($property) == $value;
    };
    return array_filter($this->collection, $cb);
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

  public function hasObjectWithId($value) {
    return $this->hasObjectWith('id', $value);
  }

  public function hasObjectWith($property, $value) {
    if ($property == 'id') {
      return array_key_exists($value, $this->collection);
    }
    foreach ($this->collection as $obj) {
      if ($obj->get($property) == $value) {
        return true;
      }
    }
    return false;
  }

  protected function getTable() {
    $cls = $this->instancesOf;
    return $cls::getTable();
  }

}
