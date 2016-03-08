<?php

namespace deco\essentials\util\annotation;

use \deco\essentials\exception as exc;

class AnnotationReader {

  /**
   * @return \deco\essentials\util\annotation\AnnotationCollection
   */
  static public function getAllAnnotations($docComment, $reflector = null) {
    $lines = explode(PHP_EOL, $docComment);
    $collection = new AnnotationCollection();
    $ann = null;
    foreach ($lines as $line) {      
      $line = preg_replace('#//.*$#', '', $line); // remove comment at the end of line
      if (preg_match('#\*\s@([a-zA-z]*)([\([private|protected|public]\)])?\s(.*)#', $line, $matches)) {        
        $annotation = trim($matches[1]);
        $visibility = $matches[2];
        if (strlen($visibility) == 0) {
          if ($reflector instanceof \ReflectionProperty ||
              $reflector instanceof \ReflectionMethod) {
            if ($reflector->isPrivate()) {
              $visibility = 'private';
            } else if ($reflector->isProtected()) {
              $visibility = 'protected';
            } else if ($reflector->isPublic()) {
              $visibility = 'public';
            }
          }
          if (strlen($visibility) == 0) {
            $visibility = 'public';
          }
        } else {
          preg_match('#\((.*)\)#', $visibility, $vis);
          $visibility = $vis[1];
        }
        $value = self::parseAnnotationValue($matches[3]);
        if ($collection->hasAnnotation($annotation)){
          $collection->get($annotation)->merge($value);
        } else {
          $ann = new Annotation($annotation, $value, $visibility);
          $collection->set($ann);
        }
      } else if (preg_match('#\\\\(.*)#', $line, $matches)) { // needs to be array or dictionary
        $newValue = self::parseAnnotationValue($matches[1]);            
        // picks previous annotation
        if (is_null($ann)) {
          throw new exc\Annotation(array('msg' => 'Cannot continue unexisting annotation.'));
        }
        $ann->merge($newValue);
      }
    }
    return $collection;
  }

  static public function getAnnotationValue($docComment, $annotation, $default) {
    if (strlen($docComment) == 0) {
      return $default;
    }
    $annCol = self::getAllAnnotations($docComment);
    return $annCol->getValue($annotation, $default);
  }

  // get class annotations according to object inheritance hierarchy
  static public function getClassAnnotations($class, $isParent = false) {
    $ref = new \ReflectionClass($class);
    $annotations = self::getAllAnnotations($ref->getDocComment());
    if (!(($parent = $ref->getParentClass()) === false)) {
      $parentAnnotations = self::getClassAnnotations($parent->getName(), true);
      $parentAnnotations->stripPrivate();
      $annotations = self::inheritAnnotations($parentAnnotations, $annotations);
    }
    $annotations->name = $class;
    $annotations->reflector = $ref;
    return $annotations;
  }

  static public function getObjectsTable($class) {
    $ref = new \ReflectionClass($class);
    if (false !== ($customTable = self::getAnnotationValue($ref->getDocComment(), 'table', false))) {
      return $customTable;
    }
    if (self::getAnnotationValue($ref->getDocComment(), 'noTable', false) !== false) {
      return self::getObjectsTable(get_parent_class($class));
    }
    preg_match('#[a-zA-Z]*$#', $class, $matches);
    return $matches[0];
  }

  static public function getClassMethodsAnnotations($class) {
    $ref = new \ReflectionClass($class);
    $structure = array();
    $methods = $ref->getMethods();
    foreach ($methods as $method) {
      $name = $method->getName();
      $structure[$name] = self::getClassMethodAnnotations($method);
    }
    return $structure;
  }

  static public function getClassMethodAnnotations(\ReflectionMethod $method, $isParent = false) {
    $collection = self::getAllAnnotations($method->getDocComment());
    if ($isParent) {
      $collection->stripPrivate();
    }
    if ($collection->hasAnnotation('extends') ||
        $collection->getValue('inherits', false)) {
      $class = $method->getDeclaringClass();
      $parentClass = $class->getParentClass();
      if ($parentClass !== false) {
        $parentMethod = $parentClass->getMethod($method->getName());
        if ($parentMethod !== false) {
          $parentAnnotations = self::getClassMethodAnnotations($parentMethod, true);
          return self::inheritAnnotations($parentAnnotations, $collection);
        }
      }
    }
    $collection->reflector = $method;
    $collection->name = $method->getName();
    return $collection;
  }

  static public function getClassPropertiesAnnotations($class) {
    $ref = new \ReflectionClass($class);
    $structure = array();
    $properties = $ref->getProperties();
    foreach ($properties as $property) {
      $name = $property->getName();
      $structure[$name] = self::getClassPropertyAnnotations($property);
    }
    return $structure;
  }

  static public function getClassPropertyAnnotations(\ReflectionProperty $property, $isParent = false) {
    $collection = self::getAllAnnotations($property->getDocComment());
    if ($isParent) {
      $collection->stripPrivate();
    }
    if ($collection->hasAnnotation('extends') ||
        $collection->getValue('inherits', false)) {
      $class = $property->getDeclaringClass();
      $parentClass = $class->getParentClass();
      if ($parentClass !== false) {
        $parentProperty = $parentClass->getProperty($property->getName());
        if ($parentProperty !== false) {
          $parentAnnotations = self::getClassPropertyAnnotations($parentProperty, true);
          return self::inheritAnnotations($parentAnnotations, $collection);
        }
      }
    }
    $collection->reflector = $property;
    $collection->name = $property->getName();
    return $collection;
  }

  static private function inheritAnnotations($from, $to) {
    $extends = array();
    if ($to->hasAnnotation('extends')) {
      $extends = $to->getValue('extends');
      if (!is_array($extends)) {
        $extends = array($extends);
      }
      foreach ($extends as $toExtend) {
        $from->get($toExtend)->merge($to->getValue($toExtend));
      }
    }
    // replace all that are not extended by child annotation because they are overridden
    $childAnnotations = $to->get();
    foreach ($childAnnotations as $ann) {
      if (!in_array($ann->name, $extends)) {
        $from->set($ann);
      }
    }
    return $from;
  }

  static private function parseAnnotationValue($value) {
    $value = trim($value);       
    if (preg_match('#:#', $value)) {      
      $array = array();      
      while (true){                
        preg_match('#^([^\:]*)#', $value, $matches);        
        if (count($matches) == 0 || strlen($matches[1]) == 0){
          break;
        }
        $key = trim($matches[1]);        
        $value = trim(str_replace("$key:","",$value));        
        // search for rest
        if (substr($value,0,1) == '"'){
          // make this recursive in the future, it should be
          // http://stackoverflow.com/questions/17786433/regex-to-match-nested-json-objects
          // (?<=\{)\s*[^{]*?(?=[\},])
          
          preg_match('#"(.*)"(;|$)#',$value,$matches);          
          $array[$key] = self::parseAnnotationValue($matches[1]);                    
          $value = substr($value,strlen($matches[0])+1);
        } else {          
          preg_match('#^[^;|$]*#',$value,$matches);          
          $array[$key] = $matches[0];
          $value = substr($value,strlen($matches[0])+1);
        }
      }           
      $value = $array;       
    } else if (preg_match('#,#', $value)) {
      $array = explode(',', $value);
      foreach ($array as $key => $value) {
        if (strlen(trim($value)) == 0) {
          unset($array[$key]);
        }
      }
      $value = $array;
    }
    return self::setAnnotationValueType($value);
  }

  static private function setAnnotationValueType($value) {
    if (is_array($value)) {
      foreach ($value as $key => $val) {
        $value[$key] = self::setAnnotationValueType($val);
      }
    } else {
      $value = trim($value);
      if (is_numeric($value)) {
        settype($value, 'integer');
      } elseif ($value == 'true' || $value == 'false') {
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
      } elseif (strtolower($value) == 'null') {
        return null;
      }
    }
    return $value;
  }

}
