<?php

namespace deco\essentials\util\annotation;

use \deco\essentials\exception as exc;

class Annotation {
  
  const IS_PUBLIC = 0;
  const IS_PROTECTED = 1;
  const IS_PRIVATE = 2;
  
  public $name;  
  public $value;
  public $visibility;        
  
  public function __construct($name, $value, $visibility = self::IS_PUBLIC){    
    $this->name = $name;
    $this->value = $value;    
    $this->visibility = $visibility;        
  }
  
  public function isVisibility($type){
    return $this->visibility == $type;
  }
  
  public function isPrivate(){
    return $this->isVisibility(self::IS_PRIVATE);
  }    
  
  public function isProtected(){
    return $this->isVisibility(self::IS_PROTECTED);
  }    

  public function isPublic(){
    return $this->isVisibility(self::IS_PUBLIC);
  }    
  
  public function merge($value,$keyForExistingIfNotArray = null){
    if (!is_array($this->value)){
      if (is_null($keyForExistingIfNotArray)) {
        $this->value = array($this->value);
      }
      else {
        $this->value = array($keyForExistingIfNotArray => $this->value);
      }
      // throw new exc\Annotation(array('msg' => "Value of annotation '{$this->name}' is not list. Merge not possible."));
    }    
    if (!is_array($value)){
      $value = array($value);
    }
    $isAssoc = array_keys($this->value) !== range(0, count($this->value) - 1);
    if ($isAssoc){      
      foreach ($value as $key => $val){
         $this->value[$key] = $val;    
      }
    }
    else {
      $this->value = array_merge($this->value,$value);    
    }
  }
  
  public function setVisibilityWithString($string){
    $this->visibility = self::getVisibilityForString($string);    
  }
  
  static public function getVisibilityForString($string){
    switch (strtolower($string)){
      case 'public':
        return self::IS_PUBLIC;
      case 'protected':
        return self::IS_PROTECTED;
      case 'private':
        return self::IS_PRIVATE;
      default:
        throw new exc\Annotation(array('msg' => "Cannot determine visibility for string '{$string}'."));
    }
  }
  
}
