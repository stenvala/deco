<?php

namespace deco\essentials\traits\deco;

use \deco\essentials\util\annotation as ann;

trait Annotations {

  use AnnotationsForClass;
  
  private static $methodAnnotations = array();
  private static $propertyAnnotations = array();

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
      

  protected static function DECOgetCalledClass() {
    return get_called_class();
  }

}
