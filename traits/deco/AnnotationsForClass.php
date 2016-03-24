<?php

namespace deco\essentials\traits\deco;

use \deco\essentials\util\annotation as ann;

trait AnnotationsForClass {

  private static $classAnnotations = array();

// Class
  // Returns AnnotationCollection
  public static function getClassName() {
    $cls = get_called_class();
    preg_match('#\\\\([A-Za-z]*)$#', $cls, $match);
    return $match[1];
  }

  public static function getAnnotationsForClass() {
    $cls = get_called_class();
    if (!array_key_exists($cls, self::$classAnnotations)) {
      self::$classAnnotations[$cls] = ann\AnnotationReader::getClassAnnotations($cls);
    }
    return self::$classAnnotations[$cls];
  }

  public static function getClassAnnotation($annotation) {
    return self::getAnnotationsForClass()->get($annotation);
  }

  public static function getClassAnnotationValue($annotation, $default = null) {
    return self::getAnnotationsForClass()->getValue($annotation, $default);
  }

  public static function isListOfService() {
    return self::isSubClassOf('\\deco\\essentials\\prototypes\\table\\ListOf') || 
        self::isSubClassOf('\\deco\\essentials\\prototypes\\graph\\ListOf');
  }

  public static function isSubClassOf($class) {
    $ref = new \ReflectionClass(get_called_class());
    return $ref->isSubclassOf($class);
  }

}
