<?php

namespace deco\essentials\database\util;

use \deco\essentials\exception as exc;

class Type {

  public static function convertTo(&$value, 
          \deco\essentials\util\annotation\AnnotationCollection $propertyAnnotations, 
          \deco\essentials\util\annotation\AnnotationCollection $classAnnotations = null){
    $type = $propertyAnnotations->getValue('type');    
    if (is_null($value) || $value === 'NULL'){
      $value = null;
      return;
    }
    switch ($type){
      case 'integer':
      case 'string':
        settype($value,$type);
        break;
      case 'bool':        
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);        
        break;
      case 'timestamp':
        if (is_numeric($value)){
          settype($value,$type);
        }
        else {          
          $dt = new \DateTime($value);
          $value = $dt->getTimestamp();
        }
        break;
      case 'enum':
        $cls = $classAnnotations->reflector->getName();
        $value = constant($cls . '::' . $value);        
        break;
      default:
        throw new exc\General(array('msg' => "Cannot convert variable '$value' to '$type'",
            'params' => array('type' => $type, 'value' => $value)));
               
    }
    
  }
  
}
