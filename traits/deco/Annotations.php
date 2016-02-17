<?php

namespace deco\essentials\traits\deco;

use \deco\essentials\util\annotation as ann;

trait Annotations {

  private static $classAnnotations = array();
  private static $methodAnnotations = array();
  private static $propertyAnnotations = array();

  // Class
  // Returns AnnotationCollection
  public static function getClassName() {
    $cls = get_called_class();
    preg_match('#\\\\([A-Za-z]*)$#', $cls, $match);
    return $match[1];
  }

  public static function getAnnotationsForClass() {
    $cls = self::DECOgetCalledClass();
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

  // Method
  // Returns array of AnnotationCollections, keys are method names
  public static function getAnnotationsForMethods() {
    $cls = self::DECOgetCalledClass();
    if (!array_key_exists($cls, self::$methodAnnotations)) {
      self::$methodAnnotations[$cls] = ann\AnnotationReader::getClassMethodsAnnotations($cls);
    }
    return self::$methodAnnotations[$cls];
  }

  public static function getMethodAnnotations($method) {
    return self::getAnnotationsForMethods()[$method];
  }

  public static function getMethodAnnotation($method, $annotation) {
    return self::getAnnotationsForMethods()[$method]->get($annotation);
  }

  public static function getMethodAnnotationValue($method, $annotation, $default = null) {
    return self::getAnnotationsForMethods()[$method]->getValue($annotation, $default);
  }

  // Returns array of AnnotationCollections, keys are method names
  public static function getAnnotationsForMethodsHavingVisibility($visibility) {
    $annotations = self::getAnnotationsForMethods();
    $filter = function($annotation) use ($visibility) {
      return $annotation->isVisibility($visibility);
    };
    return array_filter($annotations, $filter);
  }

  // Property
  // Returns array of AnnotationCollections, keys are property names
  public static function getAnnotationsForProperties() {
    $cls = self::DECOgetCalledClass();
    if (!array_key_exists($cls, self::$propertyAnnotations)) {
      self::$propertyAnnotations[$cls] = ann\AnnotationReader::getClassPropertiesAnnotations($cls);
    }
    return self::$propertyAnnotations[$cls];
  }

  public static function getPropertyNames() {
    return array_keys(self::getAnnotationsForProperties());
  }

  public static function getPropertyAnnotations($property) {
    return self::getAnnotationsForProperties()[$property];
  }

  public static function getPropertyAnnotation($property, $annotation) {
    return self::getAnnotationsForProperties()[$property]->get($annotation);
  }

  public static function getPropertyAnnotationValue($property, $annotation, $default = null) {
    return self::getAnnotationsForProperties()[$property]->getValue($annotation, $default);
  }

  // Returns array of AnnotationCollections, keys are property names
  public static function getAnnotationsForPropertiesHavingAnnotation($name, $value = null) {
    $properties = self::getAnnotationsForProperties();
    $matchValue = func_num_args() == 2;
    $filter = function($annotation) use ($name, $value, $matchValue) {
      if ($annotation->hasAnnotation($name)) {
        if ($matchValue && $annotation->getValue($name) != $value) {
          return false;
        }
        return true;
      }
      return false;
    };
    return array_filter($properties, $filter);
  }

  // Returns array of AnnotationCollections, keys are property names
  public static function getAnnotationsForPropertiesNotHavingAnnotation($name, $value = null) {
    $properties = self::getAnnotationsForProperties();
    $matchValue = func_num_args() == 2;
    $filter = function($annotation) use ($name, $value, $matchValue) {
      if ($annotation->hasAnnotation($name)) {
        if ($matchValue && $annotation->getValue($name) != $value) {
          return true;
        }
        return false;
      }
      return true;
    };
    return array_filter($properties, $filter);
  }

  // Returns array of AnnotationCollections, keys are property names
  public static function getAnnotationsForPropertiesHavingAnnotationWithProperty($name, $property, $value = null) {
    $properties = self::getAnnotationsForProperties();
    $matchValue = func_num_args() == 2;
    $filter = function($annotation) use ($name, $value, $matchValue) {
      if ($annotation->hasAnnotation($name)) {
        if ($matchValue && $annotation->getValue($name) != $value) {
          return false;
        }
        return true;
      }
      return false;
    };
    return array_filter($properties, $filter);
  }

  public static function isSubClassOf($class) {
    $ref = new \ReflectionClass(get_called_class());
    return $ref->isSubclassOf($class);
  }

  protected static function DECOgetCalledClass() {
    return get_called_class();
  }

}
