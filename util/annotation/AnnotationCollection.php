<?php

namespace deco\essentials\util\annotation;

use \deco\essentials\exception as exc;

class AnnotationCollection {

  public $name;
  public $reflector;  
  
  private $annotations = array();
  
  public function __construct($name=null, $reflector=null){
    $this->name = $name;    
    $this->reflector = $reflector;
  }    
  
  public function __toString(){
    $str = '';
    foreach ($this->annotations as $key => $ann){
      $str .= "$key:\n";
      if (is_array($ann->value)){
        $str .= print_r($ann->value, true);
      }
      else {
        $str .= $ann->value;
      }
      $str .= PHP_EOL;            
    }
    return $str;
    
  }
  
  public function get($name=null){
    if (is_null($name)){
      return $this->annotations;
    }
    if (!array_key_exists($name,$this->annotations)){
      throw new exc\Annotation(array('msg' => "Annotation '$name' does not exist in collection '{$this->name}'."));
    }
    return $this->annotations[$name];
  }
  
  public function getValue($name,$default = null){
    if (!array_key_exists($name, $this->annotations)){
      return $default;
    }
    $value = $this->annotations[$name]->value;    
    if (is_array($default) && !is_array($value)){
      $value = array($value);
    }
    return $value;
  }
  
  public function has($name){
    return $this->hasAnnotation($name);
  }
  
  public function hasAnnotation($name){
    if (is_array($name)){
      foreach ($name as $annotationName){
        if ($this->hasAnnotation($annotationName)){
          return true;
        }        
      }
      return false;
    }
    return array_key_exists($name,$this->annotations);
  }    
  
  public function isVisibility($value){
    if ($this->reflector instanceof \ReflectionMethod){
      switch ($value){
        case \ReflectionMethod::IS_PUBLIC:
          return $this->reflector->isPublic();
        case \ReflectionMethod::IS_PROTECTED:
          return $this->reflector->isProtected();
        case \ReflectionMethod::IS_PRIVATE:
          return $this->reflector->isPrivate();
      }
    }
    else if ($this->reflector instanceof \ReflectionProperty){
      switch ($value){
        case \ReflectionProperty::IS_PUBLIC:
          return $this->reflector->isPublic();
        case \ReflectionProperty::IS_PROTECTED:
          return $this->reflector->isProtected();
        case \ReflectionProperty::IS_PRIVATE:
          return $this->reflector->isPrivate();
      }
    }
    throw new exc\Annotation(array('msg' => "Visibility cannot be asked if reflector fo annotation collection is not of type '\ReflectionMethod' or '\ReflectionProperty'."));
  }
  
  public function show(){
    print $this->name . PHP_EOL;
    foreach ($this->annotations as $ann){
      print "- @{$ann->name}:";
      if (is_array($ann->value)){
        print_r($ann->value);
      }
      else {
        print $ann->value . PHP_EOL; 
      }
    }
  }
  
  public function set(Annotation $ann){
    $this->annotations[$ann->name] = $ann;
  }
  
  public function setName($name){
    $this->name = $name;
  }  
  
  public function stripPrivate(){
    foreach ($this->annotations as $key => $ann){
      if ($ann->isPrivate()){
        unset($this->annotations[$key]);
      }
    }
  }
    
}
