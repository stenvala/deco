<?php

namespace deco\essentials\traits\deco;

trait AnnotationsIncludingDatabaseProperties {

  use Annotations;

  private static $propertyAnnotationsOfDatabaseProperties = array();

  // Returns array of AnnotationCollections, keys are property names
  static public function getForDatabaseProperties() {
    $cls = self::DECOgetCalledClass();
    if (!array_key_exists($cls, self::$propertyAnnotationsOfDatabaseProperties)) {
      $anns = self::getAnnotationsForProperties();
      $cb = function($annCol) {
        return self::isDatabaseProperty($annCol);
      };
      self::$propertyAnnotationsOfDatabaseProperties[$cls] = array_filter($anns, $cb);
    }
    return self::$propertyAnnotationsOfDatabaseProperties[$cls];
  }

  static public function isDatabaseProperty($annCol) {
    if (!$annCol->reflector->isProtected() ||
        $annCol->getValue('nodb', false)) {
      return false;
    }
    return true;
  }

  static public function isObjectInitableBy($property) {
    $annCol = self::getPropertyAnnotations($property);
    if ($annCol->reflector->isProtected() &&
        !$annCol->getValue('nodb', false) &&
        ($annCol->getValue('primaryKey', false) ||
        $annCol->getValue('unique', false))) {
      return true;
    }
    return false;
  }

  static public function propertyNameToDatabaseColumn($propertyName) {
    if (preg_match('#^([a-zA-Z]*)_$#', $propertyName, $matches)) {
      return $matches[1];
    }
    return $propertyName;
  }

  static public function getDatabaseHardColumnNames() {
    $anns = self::getForDatabaseProperties();
    $filter = function($annCol){
      return !$annCol->getValue('lazy',false);
    };
    return array_keys(array_filter($anns,$filter));
  }
  
  static public function getDatabaseSortColumns() {
    $order = self::getClassAnnotationValue('orderBy', array());
    if (!is_array($order)){
      return array($order);
    }
    return $order;
  }

  static public function getDatabaseSortString() {
    $columns = self::getDatabaseSortColumns();
    if (count($columns) == 0) {
      return '';
    }
    return 'order by ' . implode(',', $columns);
  }

  static public function getForDatabasePropertiesWithColumnKeys($anns = null) {
    $newData = array();
    $self = get_called_class();
    $cb = function($item, $key) use ($newData, $self) {
      $key = $self::propertyNameToDatabaseColumn($key);
      $newData[$key] = $item;
    };
    if (is_null($anns)) {
      $anns = self::getForDatabaseProperties();
    }
    array_walk($anns, $cb);
    return $newData;
  }

}
