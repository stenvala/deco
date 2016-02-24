<?php

namespace deco\essentials\prototypes\mono;

use \deco\essentials\database\util as util;
use \deco\essentials\exception as exc;
use \deco\essentials\util as commonUtil;
use \deco\essentials\util\annotation as ann;

/**
 * @noTable(private): true
 */
abstract class Row {

  use \deco\essentials\traits\database\FluentTableDB;

  use \deco\essentials\traits\deco\AnnotationsIncludingDatabaseProperties;

  const CREATE = 'CREATE';
  const UPDATE = 'UPDATE';
  const DELETE = 'DELETE';

  private static $table = array();

  /**
   * @type: integer
   * @primaryKey: true
   * @autoIncrement: true
   * @set: false
   */
  protected $id;

  public function __construct() {
    $args = func_get_args();
    if (count($args) == 1 && is_array($args[0])) {
      self::DECOinitByArray($args[0], $this);
    } else if (count($args) == 1) {
      self::DECOinitBy('id', $args[0], $this);
    } else if (count($args) == 2) {
      self::DECOinitBy($args[0], $args[1], $this);
    }
  }

  /**
   * @call: $cls::initBy{Property}($value), $cls::initBy($property,$value) // $obj cannot be given, is for constructor help only
   * @return: instance of self
   * @throws: Deco, SingleDataModel
   */
  static protected function DECOinitBy($property, $value, $obj = null) {
    if (!self::isObjectInitableBy($property)) {
      $cls = get_called_class();
      throw new exc\Deco(
      array('msg' => "Instance of '$cls' cannot be initialized with property '$property'.",
      'params' => array('property' => $property, 'class' => $cls)));
    }
    return self::DECOinitByArray(array(
            $property => $value
            ), $obj);
  }

  /**
   * @call: $cls::initBy($dictionary) // $value is array, $obj cannot be given, is for constructor help only
   * @return: instance of self
   * @throws: Deco, SingleDataModel
   */
  static protected function DECOinitByArray($where, $obj = null) {
    if ($obj == null) {
      $obj = new static();
    }
    $properties = self::getDatabaseHardColumnNames();
    $query = self::db()->fluent()->
            from(self::getTable())->select(null)->select($properties)->where($where)->execute();
    $data = self::db()->get($query);
    if (!is_array($data)) {
      $cls = get_called_class();
      throw new exc\SingleDataModel(
      array('msg' => "Instance of '$cls' not found with given search query.",
      'params' => array('where' => $where, 'class' => $cls)));
    }
    $obj->setValuesToObject($data);
    return $obj;
  }

  /**
   * @call: $cls::initFromRow($dictionary)
   */
  static protected function DECOinitFromRow($data) {
    $obj = new static();
    $obj->setValuesToObject($data);
    return $obj;
  }

  protected function setValuesToObject($data) {
    $anns = self::getForDatabaseProperties();
    $annCol = self::getAnnotationsForClass();
    foreach ($data as $key => $value) {
      util\Type::convertTo($value, $anns[$key], $annCol);
      $this->$key = $value;
    }
  }

  /**
   * @call: $obj->get{Property}(), $obj->get($property) // $force cannot be applied externally
   */
  protected function DECOget($property, $force = false) {
    $anns = self::getForDatabaseProperties();
    if (!$force) {
      if (!array_key_exists($property, $anns) ||
          !$anns[$property]->getValue('get', true)) {
        $cls = get_called_class();
        throw new exc\SingleDataModel(
        array('msg' => "Property '$property' is not gettable in '$cls'.",
        'params' => array('property' => $property, 'class' => $cls)));
      }
    }
    if (!array_key_exists($property, $anns)) {
      throw new exc\SingleDataModel(
      array('msg' => "Property '$property' is not a database property and not gettable in '$cls'.",
      'params' => array('property' => $property, 'class' => $cls)));
    }
    if (!isset($this->$property) && $anns[$property]->getValue('lazy', false)) {
      $this->initLazy($property);
    }
    return $this->$property;
  }

  /**
   * @call: $obj->getLazy()  // returns also lazy properties, $force cannot be applied externally
   */
  protected function DECOgetLazy($force = false) {
    $anns = self::getForDatabaseProperties();
    $data = array();
    $lazyProperties = self::getAnnotationsForPropertiesHavingAnnotation('lazy', true);
    foreach ($lazyProperties as $property => $annCol) {
      if (isset($this->$property)) {
        unset($lazyProperties[$property]);
      }
    }
    $this->initLazy(array_keys($lazyProperties));
    foreach ($anns as $property => $annCol) {
      if ($force || $annCol->getValue('get', true)) {
        $data[$property] = $this->$property;
      }
    }
    return $data;
  }

  protected function initLazy($properties) {
    if (!is_array($properties)) {
      $properties = array($properties);
    }
    if (count($properties) == 0) {
      return;
    }
    $query = self::db()->fluent()->
            from(self::getTable())->select(null)->select($properties)->where('id', $this->getId())->execute();
    $data = self::db()->get($query);
    $this->setValuesToObject($data);
  }

  /**
   * @call: $obj->get() // returns all but non-lazy properties, $force cannot be applied externally
   */
  protected function DECOgetAll($force = false) {
    $anns = self::getForDatabaseProperties();
    $data = array();
    foreach ($anns as $property => $annCol) {
      if (!$annCol->getValue('lazy', false) &&
          ($force || $annCol->getValue('get', true))) {
        $data[$property] = $this->$property;
      }
    }
    return $data;
  }

  static public function getTable() {
    $calledAs = get_called_class();
    if (!array_key_exists($calledAs, self::$table)) {
      self::$table[$calledAs] = ann\AnnotationReader::getObjectsTable($calledAs);
    }
    return self::$table[$calledAs];
  }

  /**
   * @call: $obj = $cls::create($dictionary)
   */
  static protected function DECOcreate($data) {
    $data = self::checkCreateData($data);
    self::db()->transactionStart();
    $data = self::checkOrderColumnConsistencyOnCreate($data);
    self::maintainOrderColumnConsistency(self::CREATE, $data);
    self::db()->fluent()->
        insertInto(self::getTable())->values($data)->execute();
    $id = self::db()->getLastInsertId();
    self::db()->transactionCommit();
    return new static($id);
  }

  static protected function checkCreateData($data) {
    $data = self::DECOstrip($data);
    $anns = self::getForDatabaseProperties();
    commonUtil\Validation::validateObjectData($anns, $data);
    foreach ($anns as $key => $annCol) {
      if (array_key_exists($key, $data)) {
        continue;
      } else if ($annCol->hasAnnotation(array('default', 'autoIncrement', 'autoArrange', 'order'))) {
        continue;
      }
      $cls = get_called_class();
      throw new exc\SingleDataModel(
      array('msg' => "Missing oblicatory property '$key' in creating instance of '$cls'.",
      'params' => array('property' => $key, 'class' => $cls, 'data' => $data)));
    }
    return $data;
  }

  public function is($data) {
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        if (!in_array($this->$key, $value)) {
          return false;
        }
      } else if ($this->$key != $value) {
        return false;
      }
    }
    return true;
  }

  /**
   * @call: $obj->set{Property}($value), $obj->set($property, $value) // $force cannot be applied externally
   */
  protected function DECOset($property, $value, $force = false) {
    $this->DECOsetAll(array($property => $value), $force);
  }

  /**
   * @call: $obj->set($dictionary) // $force cannot be applied externally
   */
  protected function DECOsetAll($data, $force = false) {
    $anns = self::getForDatabaseProperties();
    if (!$force) {
      foreach ($data as $property => $value) {
        if (!array_key_exists($property, $anns) ||
            !$anns[$property]->getValue('set', true)) {
          unset($data[$property]);
        }
      }
      commonUtil\Validation::validateObjectData($anns, $data, false);
    }
    if (count($data) == 0) {
      return;
    }
    // don't really understand why this is needed to be done
    foreach ($data as $key => $value) {
      if (gettype($value) == 'boolean' && !$value) {
        $data[$key] = 0;
      }
    }
    // check order column
    $data = self::checkOrderColumnConsistencyOnCreate($data, $this);
    // 
    self::db()->transactionStart();
    self::maintainOrderColumnConsistency(self::UPDATE, $data, $this);
    self::db()->fluent()->
        update(self::getTable())->set($data)->where('id', $this->getId())->execute();
    self::db()->transactionCommit();
    $this->setValuesToObject($data);
  }

  public function delete() {
    self::db()->transactionStart();
    self::maintainOrderColumnConsistency(self::DELETE, $this->DECOgetAll(true), $this);
    self::db()->fluent()->
        deleteFrom(self::getTable())->where('id', $this->getId())->execute();
    self::db()->transactionCommit();
    return null;
  }

  static protected function checkOrderColumnConsistencyOnCreate($data, $instance = null) {
    $anns = self::getAnnotationsForPropertiesHavingAnnotation('order');
    if (count($anns) == 0) {
      return $data;
    } else if (count($anns) > 1) {
      $cls = get_called_class();
      throw new exc\Deco(array('msg' => "Object '$cls' cannot have more than 1 @order column. It is auto arrange and unique in @orderGroupBy (if defined)."));
    }
    $annCol = array_pop($anns);
    $column = $annCol->name;
    if (array_key_exists($column, $data) && is_null($instance)) {
      return $data;
    }
    $groupColumn = $annCol->getValue('orderGroupBy', array());
    $where = array();
    foreach ($groupColumn as $gColumn) {
      if (!array_key_exists($gColumn, $data) &&
          self::getPropertyAnnotationValue($gColumn, 'default', false) === false && is_null($instance)) {
        throw new exc\Deco(array('msg' => "Order in '$cls' for '$gColumn' requires value for '$gColumn' in create."));
      } else if (array_key_exists($gColumn, $data)) {
        $where[$gColumn] = $data[$gColumn];
      } else if (!is_null($instance)) {
        $where[$gColumn] = $instance->DECOget($gColumn, true);
      } else {
        $where[$gColumn] = self::getPropertyAnnotationValue($gColumn, 'default');
      }
    }
    if (!is_null($instance)) {
      $table = self::getTable();
      $query = self::db()->fluent()->
              from($table)->select(null)->select("count(*) as $column")->where($where)->execute();
      $max = self::db()->get($query)[$column] + 1;
      $value = isset($data[$column]) ? $data[$column] : $instance->DECOget($column, true);
      if ($value > $max) {
        $data[$column] = $max;
      } else {
        $data[$column] = $value;
      }
      return $data;
    }

    if ($annCol->getValue('order') == 'first') {
      $data[$column] = 1;
    } else if ($annCol->getValue('order') == 'last') {
      $table = self::getTable();
      $query = self::db()->fluent()->
              from($table)->select(null)->select("count(*) as $column")->where($where)->execute();
      $data[$column] = self::db()->get($query)[$column] + 1;
    } else {
      throw new exc\Deco(array('msg' => "Order in '$cls' for '$column' must be of type first or last."));
    }
    return $data;
  }

  static protected function maintainOrderColumnConsistency($procedure, $data, $instance = null) {
    $anns = self::getAnnotationsForPropertiesHavingAnnotation('order');
    if (count($anns) > 1) {
      $cls = get_called_class();
      throw new exc\Deco(array('msg' => "Object '$cls' cannot have more than 1 @order column. It is auto arrange and unique in @orderGroupBy (if defined)."));
    } if (count($anns) == 1) {
      $annCol = array_pop($anns);
      $column = $annCol->name;
      $table = self::getTable();
      $groupColumn = $annCol->getValue('orderGroupBy', array());
      if (!is_array($groupColumn)) {
        $groupColumn = array($groupColumn);
      }
      if ($procedure == self::CREATE && !array_key_exists($column, $data)) {
        return;
      }
      $newValue = $procedure == self::DELETE ? $instance->DECOget($column, true) : $data[$column];
      $where = array();
      foreach ($groupColumn as $gColumn) {
        if (!array_key_exists($gColumn, $data)) {
          $where[$gColumn] = self::getPropertyAnnotationValue($gColumn, 'default');
        } else {
          $where[$gColumn] = $data[$gColumn];
        }
      }
      $query = null;
      switch ($procedure) {
        case self::CREATE:
          $query = self::db()->fluent()->
                  update($table)->set(array($column => new \FluentLiteral("$column + 1")))->where("$column >= ?", $newValue)->where($where);
          break;
        case self::DELETE:
          $query = self::db()->fluent()->
                  update($table)->set(array($column => new \FluentLiteral("$column - 1")))->where("$column > ?", $newValue)->where($where);
          break;
        case self::UPDATE:
          $query = $instance->maintainOrderColumnConsistencyForUpdate($data, $column, $groupColumn, $table);
          break;
      }
      if (!is_null($query)) {
        self::db()->execute($query);
      }
    }
  }

  private function maintainOrderColumnConsistencyForUpdate($data, $column, $groupColumn, $table) {
    $newValue = $data[$column];
    $oldValue = $this->DECOget($column, true);
    $didChangeGroup = false;
    $where = array();
    foreach ($groupColumn as $gColumn) {
      if (array_key_exists($gColumn, $data) && $this->DECOget($gColumn, true) != $data[$gColumn]) {
        $didChangeGroup = true;
        continue;
      } else if (!array_key_exists($gColumn, $data)) {
        $data[$gColumn] = $this->DECOget($gColumn, true);
      }
      $where[$gColumn] = $data[$gColumn];
    }
    if ($oldValue == $newValue && !$didChangeGroup) {
      return null;
    }
    $query = null;
    if ($didChangeGroup) {
      self::maintainOrderColumnConsistency(self::DELETE, $this->DECOgetAll(true), $this);
      self::maintainOrderColumnConsistency(self::CREATE, $data, null);
      return null;
    } else if ($newValue > $oldValue) {
      $query = self::db()->fluent()->
              update($table)->set(array($column => new \FluentLiteral("$column - 1")))
              ->where("$column > ?", $oldValue)->where("$column <= ?", $newValue)->where($where);
    } else {
      settype($oldValue, 'int');
      $query = self::db()->fluent()->
              update($table)->set(array($column => new \FluentLiteral("$column + 1")))
              ->where("$column >= ?", $newValue)->where("$column < ?", $oldValue)->where($where);
    }
    return $query;
  }

  /**
   * @call: $cls::strip($dictionary) // strip all non-database properties
   */
  static protected function DECOstrip($data) {
    $anns = self::getForDatabaseProperties();
    foreach ($data as $key => $value) {
      if (!array_key_exists($key, $anns)) {
        unset($data[$key]);
      }
    }
    return $data;
  }

  static public function getReferenceToClass($parentClass) {
    $parentTable = $parentClass::getTable();
    $annCol = self::getPropertyAnnotationsForForeignKeyReferringTo($parentTable);
    $ref = $annCol->get('references');
    return array(
        'table' => self::getTable(),
        'column' => $annCol->name,
        'parentColumn' => $ref->value['column']
    );
  }

  static public function getPropertyAnnotationsForForeignKeyReferringTo($table) {
    $anns = self::getForDatabaseProperties();
    foreach ($anns as $annCol) {
      if ($annCol->hasAnnotation('references')) {
        $values = $annCol->get('references');
        if ($values->value['table'] == $table) {
          return $annCol;
        } else if ($values->value['table'] == 'self' && $table == self::getTable()) {
          return $annCol;
        }
      }
    }
    $cls = get_called_class();
    throw new exc\Database(array('msg' => "Object '$cls' does not have a foreign key that refers to '$table'."));
  }

  public function __call($method, $arguments) {
    if ($method == 'getLazy') {
      return $this->DECOgetLazy();
    } else if (preg_match('#^get[A-Z]#', $method)) {
      $property = lcfirst(preg_replace('#^get#', '', $method));
      return $this->DECOget($property);
    } else if (preg_match('#^get$#', $method)) {
      if (count($arguments) == 1) {
        return $this->DECOget($arguments[0]);
      }
      return $this->DECOgetAll();
    } else if (preg_match('#^set[A-Z]#', $method)) {
      $property = lcfirst(preg_replace('#^set#', '', $method));
      return $this->DECOset($property, $arguments[0]);
    } else if (preg_match('#^set$#', $method)) {
      if (count($arguments) == 2) {
        return $this->DECOset($arguments[0], $arguments[1]);
      }
      return $this->DECOsetAll($arguments[0]);
    } else if (method_exists(__CLASS__, "DECO$method")) {
      return call_user_func_array(array($this, "DECO$method"), $arguments);
    } else {
      $cls = get_called_class();
      throw new exc\Magic(array('msg' => "Called unknown magic method '$method' in '$cls'."));
    }
  }

  static public function __callStatic($method, $arguments) {
    if ($method == 'initBy') {
      if (count($arguments) == 1) {
        return self::DECOinitByArray($arguments[0]);
      }
      return self::DECOinitBy($arguments[0], $arguments[1]);
    } else if (preg_match('#^initBy[A-Z]#', $method)) {
      $property = preg_replace('#^initBy#', '', $method);
      return self::DECOinitBy(lcfirst($property), $arguments[0]);
    } else if (method_exists(__CLASS__, "DECO$method")) {
      return forward_static_call_array(array(__CLASS__, "DECO$method"), $arguments);
    } else {
      $cls = get_called_class();
      throw new exc\Magic(array('msg' => "Called unknown magic method '$method' in '$cls'."));
    }
  }

}
