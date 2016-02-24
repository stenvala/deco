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
 * Index in table
 */
class Index {

  /**
   * Name of index
   * 
   * @var string
   */
  protected $name;

  /**
   * Columns that are in this index
   * 
   * @var array of strings
   */
  protected $columns;

  /**
   * Additional attributes for the index (for example: unique, primary key)
   * 
   * @var any 
   */
  protected $kind = '';

  /**
   * Construct index
   * 
   * @param string $name
   * @param string or array of strings $columns
   */
  public function __construct($name, $columns) {
    if (!is_array($columns)) {
      $columns = array($columns);
    }
    $this->name = $name;
    $this->columns = $columns;
  }

  /**
   * Add attributes to the column
   * 
   * @param string $kind
   */
  public function setKind($kind) {
    $this->kind = $kind;
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
