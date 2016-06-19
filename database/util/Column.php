<?php

/**
 * DECO Library
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\database\util;

use \deco\essentials\exception as exc;

/**
 * Table database column
 */
class Column {

  /**
   * Name of the column
   *
   * @var int
   */
  private $name;

  /**
   * @type of the column, if enum includes also list of options
   * 
   * @var string
   */
  private $type;

  /**
   * Default value for the column
   * 
   * @var custom
   */
  private $default;

  /**
   * Is auto increment
   * 
   * @var bool
   */
  private $autoIncrement = false;

  /**
   * Is primary key
   * 
   * @var bool
   */
  private $primaryKey = false;

  /**
   * Is unique
   * 
   * @var bool
   */
  private $unique = false;

  /**
   * Allow to be null
   * 
   * @var bool
   */
  private $allowNull = false;

  /**
   * Construction is done by adding name and type to the column
   * 
   * @param string $name Name of the column
   * @param string $type Type of the column
   */
  public function __construct($name, $type) {
    $this->name = $name;
    $this->type = $type;
  }

  /**
   * Set column default value
   * 
   * @param custom $default
   * 
   * @return static
   */
  public function setDefault($default) {
    $this->default = $default;
    return $this;
  }

  /**
   * Set column to be auto increment   
   * 
   * @return static
   */
  public function setAsAutoIncrement() {
    $this->autoIncrement = true;
    return $this;
  }

  /**
   * Set column to be primary key
   * 
   * @return static 
   */
  public function setAsPrimaryKey() {
    $this->primaryKey = true;
    return $this;
  }

  /**
   * Set column to be unique
   * 
   * @return static
   */
  public function setAsUnique() {
    $this->unique = true;
    return $this;
  }

  /**
   * Set column to allow null values
   * 
   * @return $this
   */
  public function allowNull() {
    $this->allowNull = true;
    return $this;
  }

  /**
   * Has column default value
   * 
   * @return bool
   */
  public function hasDefault() {
    return isset($this->default);
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

  /**
   * Get type for property based on annotation collection with annotation type
   * 
   * @param \deco\essentials\util\annotation\AnnotationCollection $annCol
   * @return string
   * 
   * @throws exc\Deco If annotation collection does not have annotation type
   */
  static public function getTypeForProperty(\deco\essentials\util\annotation\AnnotationCollection $annCol) {
    if (!$annCol->hasAnnotation('type')) {
      throw new exc\Deco(
      array('msg' => 'Annotation collection does not have annotation type.'));
    }
    $type = $annCol->getValue('type');
    // Custom behaviour
    if ($type == 'string') {
      if (count($val = $annCol->getValue('validation', array())) > 0 &&
          array_key_exists('maxLength', $val)) {
        $type = "varchar({$val['maxLength']})";
      } else {
        $type = 'text';
      }
    } else if ($type == 'enum') {
      $values = $annCol->getValue('values');      
      $type = " enum('" . implode("','", $values) . "')";
    } else if ($type == 'json'){
      return 'text';
    }
    // Otherwise just return type
    return $type;
  }

}
