<?php

namespace deco\essentials\prototypes\database;

use \deco\essentials\database\util as util;
use \deco\essentials\exception as exc;
use \deco\essentials\util as commonUtil;
use \deco\essentials\util\annotation as ann;

/**
 * @noTable(private): true
 */
abstract class TableRowInMariaDB {

  use \deco\essentials\traits\database\MariaDB;

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

  public function __construct($id = null) {
    if (!is_null($id)) {
      self::initBy('id', $id, $this);
    }
  }

  /**
   * @call: $obj->initBy{Property}($value), $obj->initBy($property,$value) // with static, $obj cannot be given
   * @return: instance of self
   */
  static protected function DECOinitBy($property, $value, $obj = null) {
    if (!self::isObjectInitableBy($property)) {
      $cls = get_called_class();
      throw new exc\SingleDataModel(
      array('msg' => "Instance of '$cls' cannot be initialized with property '$property'.",
      'params' => array('property' => $property, 'class' => $cls)));
    }
    if ($obj == null) {
      $obj = new static();
    }
    $data = self::db()->getById(self::getTable(), $value, $property);
    $obj->setValuesToObject($data);
    return $obj;
  }

  /**
   * @call: $obj = $cls::initFromRow($dictionary)
   */
  static protected function DECOinitFromRow($data) {
    $obj = new static();
    $obj->setValuesToObject($data);
    return $obj;
  }

  protected function setValuesToObject($data) {
    $anns = self::getForDatabaseProperties();
    $annCol = self::getForClass();
    foreach ($data as $key => $value) {
      util\Type::convertTo($value, $anns[$key], $annCol);
      $this->$key = $value;
    }
  }

  /**
   * @call: $obj->get{Property}(), $obj->get($property) // $force cannot be applied externally
   */
  protected function DECOget($property, $force = false) {
    if (!$force) {
      $anns = self::getForDatabaseProperties();
      if (!array_key_exists($property, $anns) ||
          !$anns[$property]->getValue('get', true)) {
        $cls = get_called_class();
        throw new exc\SingleDataModel(
        array('msg' => "Property '$property' is not gettable in '$cls'.",
        'params' => array('property' => $property, 'class' => $cls)));
      }
    }
    return $this->$property;
  }

  /**
   * @call: $obj->get()
   */
  protected function DECOgetAll() {
    $anns = self::getForDatabaseProperties();
    $data = array();
    foreach ($anns as $property => $annCol) {
      if ($annCol->getValue('get', true)) {
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
    $anns = self::getForDatabaseProperties();
    commonUtil\Validation::validateObjectData($anns, $data);
    self::db()->transactionStart();
    self::checkAutoArrangeOnCreate($data);
    self::maintainSortKeyConsistency(self::CREATE, $data);
    self::db()->insert(self::getTable(), $data);
    $id = self::db()->getLastInsertId();
    self::db()->transactionCommit();
    return new static($id);
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
    self::db()->transactionStart();
    self::maintainSortKeyConsistency(self::UPDATE, $data, $this);
    self::db()->updateById(self::getTable(), $data, $this->getId());
    self::db()->transactionCommit();
    $this->setValuesToObject($data);
  }

  public function delete() {
    self::db()->transactionStart();
    self::maintainSortKeyConsistency(self::DELETE, null, $this);
    self::db()->deleteById(self::getTable(), $this->getId());
    self::db()->transactionCommit();
    return null;
  }

  static protected function checkAutoArrangeOnCreate(&$data) {
    $anns = self::getForPropertiesHavingAnnotation('autoArrange');
    if (count($anns) == 0) {
      return;
    } else if (count($anns) > 1) {
      $cls = get_called_class();
      throw new exc\Deco(array('msg' => "Object '$cls' cannot have more than 1 @autoArrange column."));
    }
    $annCol = array_pop($anns);
    $column = $annCol->name;
    if (array_key_exists($column, $data)) {
      return;
    }
    $table = self::getTable();
    $groupColumn = $annCol->getValue('arrangeGroups');
    if ($annCol->getValue('autoArrange') == 'first') {
      $data[$column] = 1;
    } else if ($annCol->getValue('autoArrange') == 'last') {
      $statement = "SELECT count(*) as $column from $table";
      $arrayToBind = null;
      if (!is_null($groupColumn)) {
        if (!array_key_exists($groupColumn, $data)) {
          throw new exc\Deco(array('msg' => "AutoArrange in '$cls' for '$column' requires group identifier in '$groupColumn'. It is not given. Object cannot be created."));
        }
        $statement .= " WHERE $groupColumn = :groupColumn";
        $arrayToBind = array('groupColumn' => $data[$groupColumn]);
      }
      $data[$column] = self::db()->getDataList($statement, $arrayToBind)[$column];
    } else {
      throw new exc\Deco(array('msg' => "AutoArrange in '$cls' for '$column' must be of type first or last."));
    }
  }

  static protected function maintainSortKeyConsistency($procedure, &$data, $instance = null) {
    $anns = self::getForPropertiesHavingAnnotation('autoArrange');
    if (count($anns) > 1) {
      $cls = get_called_class();
      throw new exc\Deco(array('msg' => "Object '$cls' cannot have more than 1 @autoArrange column."));
    } if (count($anns) == 1) {
      $annCol = array_pop($anns);
      $column = $annCol->name;
      $table = self::getTable();
      $groupColumn = $annCol->getValue('arrangeGroups', false);
      if ($procedure != self::DELETE && !array_key_exists($column, $data)) {
        return;
      }
      $newValue = $procedure == self::DELETE ? $instance->get($column, true) : $data[$column];
      $bind = array('value' => $newValue);
      $statement = null;
      switch ($procedure) {
        case self::CREATE:
          if ($groupColumn !== false) {
            $statement = "UPDATE $table SET $column = $column + 1 WHERE $column >= :value and $groupColumn = :group";
            $bind['group'] = $data[$groupColumn];
          } else {
            $statement = "UPDATE $table SET $column = $column + 1 WHERE $column >= :value";
          }
          break;
        case self::DELETE:
          if ($groupColumn !== false) {
            $statement = "UPDATE $table SET $column = $column - 1 WHERE $column > :value and $groupColumn = :group";
            $bind['group'] = $instance->get($groupColumn);
          } else {
            $statement = "UPDATE $table SET $column = $column - 1 WHERE $column > :value";
          }
          break;
        case self::UPDATE:
          list($statement, $bind) = $instance->maintainSortKeyConsistencyForUpdate($data, $column, $groupColumn, $table);
          break;
      }
      if (!is_null($statement)) {
        $query = self::db()->prepare($statement);
        self::db()->bindArrayValue($query, $bind);
        self::db()->executeQuery($query);
      }
    }
  }

  private function maintainSortKeyConsistencyForUpdate($data, $column, $groupColumn, $table) {
    $newValue = $data[$column];
    $oldValue = $this->get($column, true);
    $bind = array('value' => $newValue);
    $statement = null;
    if ($groupColumn !== false) {
      if (array_key_exists($groupColumn, $data)) {
        if ($newValue == $instance->get($column) && $data[$groupColumn] == $instance->get($groupColumn)) {
          return array(null, null);
        } else if ($data[$groupColumn] == $instance->get($groupColumn)) {
          if ($newValue == ($oldValue = $instance->get($column))) {
            return array(null, null);
          } else if ($oldValue > $newValue) {
            $statement = "UPDATE $table SET $column = $column + 1 WHERE $column >= :value AND $column < :oldValue AND $groupColumn = :group";
          } else {
            $statement = "UPDATE $table SET $column = $column - 1 WHERE $column <= :value AND $column > :oldValue AND $groupColumn = :group";
          }
          $bind['group'] = $instance->get($groupColumn);
          $bind['oldValue'] = $oldValue;
        } else {
          $oldGroupvalue = $instance->get($groupColumn);
          $statement = "UPDATE $table SET $column = $column - 1 WHERE $column > :value and $groupColumn = :oldGroup";
          $query = self::db()->prepare($statement);
          self::db()->bindArrayValue($query, array('value' => $this->get($column), 'oldGroup' => $oldGroupValue));
          self::db()->executeQuery($query);
          $statement = "UPDATE $table SET $column = $column + 1 WHERE $column > :value and $groupColumn = :newGroup";
          $bind['newGroup'] = $data[$groupColumn];
        }
      } else {
        if ($newValue == ($oldValue = $instance->get($column))) {
          return array(null, null);
        } else if ($oldValue > $newValue) {
          $statement = "UPDATE $table SET $column = $column + 1 WHERE $column >= :value AND $column < :oldValue AND $groupColumn = :group";
        } else {
          $statement = "UPDATE $table SET $column = $column - 1 WHERE $column <= :value AND $column > :oldValue AND $groupColumn = :group";
        }
        $bind['group'] = $instance->get($groupColumn);
        $bind['oldValue'] = $oldValue;
      }
    } else {
      if ($newValue == ($oldValue = $instance->get($column))) {
        return array(null, null);
      } else if ($oldValue > $newValue) {
        $statement = "UPDATE $table SET $column = $column + 1 WHERE $column >= :value AND $column < :oldValue";
      } else {
        $statement = "UPDATE $table SET $column = $column - 1 WHERE $column <= :value AND $column > :oldValue";
      }
      $bind['oldValue'] = $oldValue;
    }
    return array($statement, $bind);
  }

  /**
   * @call: $cls::strip($dictionary) // strip all non-database properties
   */
  static protected function DECOstrip(&$data) {
    $anns = self::getForDatabaseProperties();
    foreach ($data as $key => $value) {
      if (!array_key_exists($key, $anns)) {
        unset($data[$key]);
      }
    }
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
        }
      }
    }
    $cls = get_called_class();
    throw new exc\Database(array('msg' => "Object '$cls' does not have a foreign key that refers to '$table'."));
  }

  public function __call($method, $parameters) {
    if (preg_match('#^get[A-Z]#', $method)) {
      $property = lcfirst(preg_replace('#^get#', '', $method));
      return $this->DECOget($property);
    } else if (preg_match('#^get$#', $method)) {
      if (count($parameters) == 1) {
        return $this->DECOget($parameters[0]);
      }
      return $this->DECOgetAll();
    } else if (preg_match('#^set[A-Z]#', $method)) {
      $property = lcfirst(preg_replace('#^set#', '', $method));
      return $this->DECOset($property, $parameters[0]);
    } else if (preg_match('#^set$#', $method)) {
      if (count($parameters == 2)) {
        return $this->DECOset($parameters[0], $parameters[1]);
      }
      return $this->DECOsetAll($parameters[0]);
    } else if (method_exists(__CLASS__, "DECO$method")) {
      return call_user_func_array(array($this, "DECO$method"), $parameters);
    } else {
      $cls = get_called_class();
      throw new exc\Magic(array('msg' => "Called unknown magic method '$method' in '$cls'."));
    }
  }

  static public function __callStatic($method, $parameters) {
    if ($method == 'initBy') {
      return self::DECOinitBy($parameters[0], $parameters[1]);
    } else if (preg_match('#^initBy[A-Z]#', $method)) {
      $property = preg_replace('#^initBy#', '', $method);
      return self::DECOinitBy(lcfirst($property), $parameters[0]);
    } else if (method_exists(__CLASS__, "DECO$method")) {
      return forward_static_call_array(array(__CLASS__, "DECO$method"), $parameters);
    } else {
      $cls = get_called_class();
      throw new exc\Magic(array('msg' => "Called unknown magic method '$method' in '$cls'."));
    }
  }

}
