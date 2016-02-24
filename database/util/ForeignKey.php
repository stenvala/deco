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
 * Foreign key in table (single column)
 */
class ForeignKey {

  /**
   * Name of the foreign key
   * 
   * @var string
   */
  private $name;

  /**
   * Name of the column
   *
   * @var string
   */
  private $column;

  /**
   * Name of the table where this column refers to
   *
   * @var string
   */
  private $table;

  /**
   * Column name in the foreign table
   *
   * @var string
   */
  private $foreignColumn;

  /**
   * To do on update
   * 
   * @var string
   */
  private $onDelete;

  /**
   * To do on delete
   * 
   * @var string
   */
  private $onUpdate;

  /**
   * Initialize with all the properties
   * 
   * @param string $name
   * @param string $column
   * @param string $table
   * @param string $foreignColumn
   * @param string $onDelete
   * @param string $onUpdate
   */
  public function __construct($name, $column, $table, $foreignColumn, $onDelete, $onUpdate) {
    $this->name = $name;
    $this->column = $column;
    $this->table = $table;
    $this->foreignColumn = $foreignColumn;
    $this->onDelete = $onDelete;
    $this->onUpdate = $onUpdate;
  }

  /**
   * @call get{Property}() Returns requested property
   *
   * @return custom
   */
  public function __call($name, $arguments) {
    if (preg_match('#^get[A-Z]#', $name)) {
      $col = lcfirst(preg_replace('#^get#', '', $name));
      return $this->$col;
    }
  }

}
