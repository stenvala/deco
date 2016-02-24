<?php

/**
 * DECO Framework
 * 
 * @link: https://github.com/stenvala/deco-essentials
 * @copyright: Copyright (c) 2016- Antti Stenvall
 * @license: https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\database\util;

/**
 * Holds a table structure for a table model class
 */
class TableForClass extends Table {

  /**
   * Name of class including namespace
   *  
   * @var string
   */
  protected $cls;

  /**
   * Annotation for all the database properties of $cls
   * 
   * @var \deco\essentials\util\annotation\AnnotationCollection
   */
  protected $anns;

  /**
   * Construcs Table model from class
   * 
   * @param string $className
   */
  public function __construct($className) {
    $this->cls = $className;
    $this->anns = $className::getForDatabaseProperties();
    parent::__construct($className::getTable());
    // parse    
    $this->findColumns();
    $this->findForeignKeys();
    $this->findIndexes();
  }

  /**
   * Create columns
   */
  private function findColumns() {
    foreach ($this->anns as $annCol) {
      $name = $annCol->reflector->name;
      $type = Column::getTypeForProperty($annCol);
      $column = new Column($name, $type);
      // other properties that can be given to column
      if (($val = $annCol->getValue('default', false)) !== false)
        $column->setDefault($val);
      if ($annCol->getValue('unique', false) !== false)
        $column->setAsUnique();
      if ($annCol->getValue('primaryKey', false) !== false)
        $column->setAsPrimaryKey();
      if ($annCol->getValue('autoIncrement', false) !== false)
        $column->setAsAutoIncrement();
      if ($annCol->getValue('notNull', false) !== false)
        $column->allowNull();
      $this->addColumn($column);
    }
  }

  /**
   * Create foreign keys
   */
  private function findForeignKeys() {
    foreach ($this->anns as $annCol) {
      if (($ref = $annCol->getValue('references', false)) !== false) {
        $table = $ref['table'];
        $foreignColumn = array_key_exists('column', $ref) ? $ref['column'] : 'id';
        $onDelete = array_key_exists('onDelete', $ref) ? $ref['onDelete'] : 'SET NULL';
        $onUpdate = array_key_exists('onUpdate', $ref) ? $ref['onUpdate'] : 'CASCADE';
        $name = array_key_exists('name', $ref) ? $ref['name'] : $table . '_FK';
        $con = new ForeignKey($name, $annCol->reflector->name, $table, $foreignColumn, $onDelete, $onUpdate);
        $this->addForeignKey($con);
      }
    }
  }

  /**
   * Find indices, also from class annotations
   */
  private function findIndexes() {
    $cls = $this->cls;
    $index = $cls::getClassAnnotationValue('index', false);
    // class indexes having multiple columns
    if ($index !== false) {
      foreach ($index as $name => $columns) {
        $kind = '';
        if (in_array(strtoupper($columns[0]), array('UNIQUE', 'FULLTEXT', 'SPATIAL')))
          $kind = $columns[0];
        if ($kind != '') {
          unset($columns[0]);
          $columns = array_values($columns);
        }
        $ind = new Index($name, $columns);
        $ind->setKind(strtoupper($kind));
        $this->addIndex($ind);
      }
    }
    // load from member properties    
    foreach ($this->anns as $annCol) {
      if (($index = $annCol->getValue('index', false)) !== false) {
        $column = $annCol->reflector->name;
        $name = is_bool($index) ? $column . '_INDEX' : $index;
        $this->addIndex(new Index($name, $column));
      }
    }
  }

}
