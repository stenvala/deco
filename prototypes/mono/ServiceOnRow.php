<?php

namespace deco\essentials\prototypes\mono;

use \deco\essentials\exception as exc;
use \deco\essentials\util\annotation as ann;
use \deco\essentials\prototypes\database as db;

/**
 * @contains: abstract   
 */
abstract class ServiceOnRow {

  use \deco\essentials\traits\deco\Annotations;

use \deco\essentials\traits\database\FluentMariaDB;

  /**
   * @passThrough: true
   * @type: \deco\essentials\prototypes\mono\Row   
   */
  protected $instance;

  public function __construct($value = null, $key = null) {
    if (is_null($value)) {
      return;
    }
    try {
      $annCol = self::getAnnotationsForClass();
      $cls = $annCol->getValue('contains');
      $key = is_null($key) ? $annCol->getValue('initBy', 'id') : $key;
      $this->instance = $cls::initBy($key, $value);
    } catch (\Exception $e) {
      error_log($e->getMessage());
      unset($this->instance);
    }
    if (isset($this->instance)) {
      $this->createOnConstructObjects();
    }
  }

  public static function initFromRow($row) {
    $cls = self::getClassAnnotationValue('contains');
    $obj = new static();
    $obj->instance = $cls::initFromRow($row);
    $obj->createOnConstructObjects();
    return $obj;
  }

  // Creates main object for the class
  static public function create($data) {
    $obj = new static();
    $obj->createInstance($data);
    $obj->createOnConstructObjects();
    return $obj;
  }

  protected function createInstance($data) {
    if (isset($this->instance)) {
      $cls = get_called_class();
      throw new exc\Service(array('msg' => "Cannot create instance in '$cls'. She already exists."));
    }
    $cls = self::getClassAnnotationValue('contains');
    $this->instance = $cls::create($data);
  }

  protected function createOnConstructObjects() {
    $anns = self::getAnnotationsForPropertiesHavingAnnotation('onConstruct', true);
    foreach ($anns as $annCol) {
      $this->populate($annCol);
    }
  }

  protected function populate(ann\AnnotationCollection $annCol) {
    if ($annCol->hasAnnotation('collection')) {
      $this->populateCollection($annCol);
    } else if ($annCol->hasAnnotation('repository')) {
      $this->populateRepository($annCol);
    } else if ($annCol->hasAnnotation('service')) {
      $this->populateService($annCol);
    } else if ($annCol->hasAnnotation('column')) {
      $this->populateColumn($annCol);
    }
  }

  protected function populateCollection(ann\AnnotationCollection $annCol) {
    $propertyName = $annCol->reflector->getName();
    $repositoryCls = $annCol->getValue('collection');
    $mainCls = self::getClassAnnotationValue('contains');
    $foreign = $repositoryCls::getReferenceToClass($mainCls);
    $foreignValue = $this->instance->get($foreign['parentColumn']);
    $search = array($foreign['column'] => $foreignValue);
    if ($repositoryCls::isSubclassOf('\deco\essentials\prototypes\mono\ServiceOnCollection')) {
      $this->$propertyName = $repositoryCls::initBy($search);
    } else {
      $this->$propertyName = new Rows($repositoryCls);
      $this->$propertyName->initBy($search);
    }
  }

  protected function populateRepository(ann\AnnotationCollection $annCol) {
    $propertyName = $annCol->reflector->getName();
    $repositoryCls = $annCol->getValue('repository');
    if ($annCol->getValue('foreign', true)) {
      $mainCls = self::getClassAnnotationValue('contains');
      $foreign = $repositoryCls::getReferenceToClass($mainCls);
      $foreignValue = $this->instance->get($foreign['parentColumn']);
      try {
        $this->$propertyName = $repositoryCls::initBy($foreign['column'], $foreignValue);
      } catch (\Exception $e) {
        $this->$propertyName = null;
      }
    } else {
      $table = $repositoryCls::getTable();
      $columns = $repositoryCls::getDatabaseHardColumnNames();      
      $query = self::db()->fluent()->from($table)->select(null)->
          select($columns);
      $where = $annCol->getValue('where');
      $whereColumn = is_array($where['column']) ? $where['column'] : array($where['column']);
      $whereValues = is_array($where['value']) ? $where['value'] : array($where['value']);
      foreach ($whereColumn as $key => $col) {
        $val = $whereValues[$key];
        if (is_numeric($val)) {
          $query = $query->where($col, $val);
        } else {
          $val = $this->instance->get($val);          
          $query = $query->where($col, $val);
        }
      }
      $sort = $annCol->getValue('orderBy', array());
      if (count($sort) > 0) {
        $query = $query->orderBy($sort);
      }
      $query = $query->limit(1)->execute();
      $row = self::db()->get($query);
      if (!is_array($row)) {
        $this->$propertyName = null;
      } else {
        $this->$propertyName = $repositoryCls::initFromRow($row);
      }
    }
  }

  protected function populateService(ann\AnnotationCollection $annCol) {
    $propertyName = $annCol->reflector->getName();
    $serviceCls = $annCol->getValue('service');
    if ($serviceCls::isSubclassOf(__CLASS__)) {
      $serviceContains = $serviceCls::getClassAnnotationValue('contains');
      $parentContains = self::getClassAnnotationValue('contains');
      $foreign = $serviceContains::getReferencetoClass($parentContains);
      $foreignValue = $this->instance->get($foreign['parentColumn']);
      $this->$propertyName = new $serviceCls($foreignValue, $foreign['column']);
    } else {
      $cls = get_called_class();
      throw new exc\Service(array('msg' => "Cannot populate '$propertyName' in '$cls' because not allowed service for automatic population."));
    }
  }

  protected function populateColumn(ann\AnnotationCollection $annCol) {
    $propertyName = $annCol->reflector->getName();
    $columnData = $annCol->getValue('column');
    $repository = $columnData['repository'];
    $table = $repository::getTable();
    $query = self::db()->fluent()->from($table)->select(null)->select($columnData['column']);
    if (array_key_exists('isChild', $columnData) && $columnData['isChild']) {
      $ref = $repository::getReferenceToClass(self::getClassAnnotationValue('contains'));
      $foreignValue = $this->instance->get($ref['parentColumn']);
      $query = $query->where($ref['column'], $foreignValue);
    }
    $this->$propertyName = self::db()->getValue($query->execute());
  }

  /** // Creates object for the instance, object must be of type service or repository
   * @call: $obj->create{Property}(data)
   */
  protected function DECOcreate($property, $data = array()) {
    $annCol = self::getPropertyAnnotations($property);
    if (isset($this->$property) && !is_null($this->$property)) {
      $cls = get_called_class();
      throw new exc\Service(array('msg' => "Cannot create property '$property' in '$cls'. It exists."));
    } else if ($annCol->hasAnnotation('service') && $annCol->getValue('createInstance', false)) {
      $serviceCls = $annCol->getValue('service');
      if ($serviceCls::isSubClassOf('\deco\essentials\prototypes\mono\ServiceOnRow')) {
        $parentContains = self::getClassAnnotationValue('contains');
        $serviceContains = $serviceCls::getClassAnnotationValue('contains');
        $foreign = $serviceContains::getReferenceToClass($parentContains);
        $data[$foreign['column']] = $this->instance->get($foreign['parentColumn']);
        $this->$property = $serviceCls::create($data);
        return;
      }
    } else if ($annCol->hasAnnotation('repository') && $annCol->getValue('createInstance', false)) {
      $cls = $annCol->getValue('repository');
      $parentContains = self::getClassAnnotationValue('contains');
      $foreign = $cls::getReferenceToClass($parentContains);
      $data[$foreign['column']] = $this->instance->get($foreign['parentColumn']);
      $cls::create($data);
      return;
    }
    $cls = get_called_class();
    throw new exc\Service(array('msg' => "Cannot create property '$property' in '$cls'. Property not allowed to be created. See the documentation."));
  }

  public function get() {
    $revealInstance = self::getClassAnnotationValue('revealAs', null);
    $data = array();
    if (!is_null($revealInstance)) {
      if (!isset($this->instance)) {
        return false;
      }
      $data[$revealInstance] = $this->instance->get();
    }
    $anns = self::getAnnotationsForPropertiesHavingAnnotation('revealAs');
    foreach ($anns as $annCol) {
      if (!$annCol->getValue('onConstruct', false)) {
        $this->populate($annCol);
      }
      $property = $annCol->reflector->getName();
      if ($annCol->getValue('instanceOf', null) === 'value') {
        $data[$annCol->getValue('revealAs')] = $this->$property();
      } else {
        $data[$annCol->getValue('revealAs')] = is_null($this->$property) ? null : $this->$property()->get();
      }
    }
    return $data;
  }

  public function getHard() {
    $revealInstance = self::getClassAnnotationValue('revealAs', null);
    $data = array();
    if (!is_null($revealInstance)) {
      if (!isset($this->instance)) {
        return false;
      }
      $data[$revealInstance] = $this->instance->getHard();
    }
    $anns = self::getAnnotationsForPropertiesHavingAnnotation('revealAs');
    foreach ($anns as $annCol) {
      if (!$annCol->getValue('onConstruct', false)) {
        continue;
      }
      $property = $annCol->reflector->getName();
      if (is_null($this->$property)) {
        $data[$annCol->getValue('revealAs')] = $this->$property;
      } else if ($annCol->getValue('instanceOf', null) === 'value') {
        $data[$annCol->getValue('revealAs')] = $this->$property();
      } else {
        $data[$annCol->getValue('revealAs')] = $this->$property()->getHard();
      }
    }
    return $data;
  }

  private function getPropertyFor($singular) {
    $properties = self::getAnnotationsForPropertiesHavingAnnotation('singular', lcfirst($singular));
    if (count($properties) != 1) {
      $cls = get_called_class();
      throw new exc\Magic(array('msg' => "Unknown singular '$singular' in '$cls'."));
    }
    return array_pop($properties)->reflector->getName();
  }

  /**
   * @call: $obj->has{$property}($value)
   * @description: check if collection has value
   */
  protected function DECOhas($property, $value) {
    if (is_array($value)) {
      foreach ($value as $val) {
        if ($this->DECOhas($property, $val)) {
          return true;
        }
      }
      return false;
    }
    if ($property instanceof ann\AnnotationCollection) {
      $annCol = $property;
      $property = $property->reflector->getName();
    } else {
      $annCol = self::getPropertyAnnotations($property);
    }
    if (!is_null($val = $annCol->getValue('has'))) {
      return $this->$property->hasObjectWith($val['match'], $value);
    } else {
      $cls = get_called_class();
      throw new exc\Service(array('msg' => "'$property' in '$cls' does not have has method."));
    }
  }

  /**
   * @call: $obj->add{$property}($value)
   * @description: Add value or values to collection
   */
  protected function DECOadd($property, $value) {
    if ($property instanceof ann\AnnotationCollection) {
      $annCol = $property;
      $property = $property->reflector->getName();
    } else {
      $annCol = self::getPropertyAnnotations($property);
    }
    if (!is_null($val = $annCol->getValue('add')) && $val !== false) {
      if (!is_array($value)) {
        $value = array($val['property'] => $value);
      }
      return $this->$property->addReferencesTo($this->instance, $value);
    } else {
      $cls = get_called_class();
      throw new exc\Service(array('msg' => "Collection '$property' in '$cls' not allowed to be extended."));
    }
  }

  protected function DECOdelete() {
    if (!isset($this->instance)) {
      $cls = get_called_class();
      throw new exc\Service(array('msg' => "Instance in '$cls' already deleted."));
    }
    $this->instance->delete();
    unset($this->instance);
    return null;
  }

  /**
   * @call: $obj->remove{$property}($value), $obj->remove{$property}ById($id)
   * @description: remove value from collection
   */
  protected function DECOremove($property, $value, $key = null) {
    if ($property instanceof ann\AnnotationCollection) {
      $annCol = $property;
      $property = $property->reflector->getName();
    } else {
      $annCol = self::getPropertyAnnotations($property);
    }

    if (!is_null($val = $annCol->getValue('remove')) && $val !== false) {
      if (is_null($key)) {
        $key = $val['property'];
      }
      $object = $this->$property->getObjectBy($key, $value);
      $this->$property->deleteById($object->getId());
    } else {
      $cls = get_called_class();
      throw new exc\Service(array('msg' => "'$property' in '$cls' does not have has method. Property not allowed to be created. See the documentation."));
    }
  }

  public function __call($method, $parameters) {
    $passThroughs = self::getAnnotationsForPropertiesHavingAnnotation('passThrough', true);
    if (array_key_exists($method, $passThroughs)) {
      if ($method != 'instance' && !isset($this->$method)) {
        $annCol = self::getPropertyAnnotations($method);
        $this->populate($annCol);
      }
      return $this->$method;
    } else if (preg_match('#^create([a-zA-Z]*)$#', $method, $match)) {
      $property = lcfirst($match[1]);
      return call_user_func_array(array($this, 'DECOcreate'), array_merge(array($property), $parameters));
    } else if (preg_match('#^has([a-zA-Z]*)$#', $method, $match)) {
      return $this->DECOhas($this->getPropertyFor($match[1]), $parameters[0]);
    } else if (preg_match('#^add([a-zA-Z]*)$#', $method, $match)) {
      return $this->DECOadd($this->getPropertyFor($match[1]), $parameters[0]);
    } else if (preg_match('#^remove([a-zA-Z]*)ById$#', $method, $match)) {
      return $this->DECOremove($this->getPropertyFor($match[1]), $parameters[0], 'id');
    } else if (preg_match('#^remove([a-zA-Z]*)$#', $method, $match)) {
      return $this->DECOremove($this->getPropertyFor($match[1]), $parameters[0]);
    } else if (preg_match('#^delete$#', $method, $match)) {
      return $this->DECOdelete();
    } else {
      $cls = get_called_class();
      throw new exc\Magic(array('msg' => "Called unknown magic method '$method' in '$cls'."));
    }
  }

}
