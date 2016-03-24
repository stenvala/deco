<?php

/**
 * DECO Library
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\prototypes\restmap;

/**
 * Base class for instantiating services to REST end-points. 
 * Allows automatic binding of class methods to a Lumen application without 
 * explicitely writing which to bind and where
 * (https://lumen.laravel.com)
 */
abstract class Lumen extends AutoMapper {

  use \deco\essentials\traits\deco\Annotations;

  /**
   * Construct with path
   * 
   * @param string $path   
   */
  public function __construct($app, $path) {    
    parent::__construct($app, $path);
    if (self::cannotBeTargetByPath($path)) {
      return;
    }    
    $this->mapMethods();
  }

  /**
   * Maps methods to URI, finally, maps only the method that is requested
   */
  private function mapMethods() {
    $methods = self::getAnnotationsForMethodsHavingVisibility(\ReflectionMethod::IS_PUBLIC);
    self::sortMethods($methods);
    $regex = '#(^' . implode(self::$autoMap, '|^') . ')([a-zA-Z]+)?(_[a-zA-Z])?$#';
    foreach ($methods as $method) {
      $name = $method->name;
      if (!preg_match($regex, $name, $temp) ||
          $method->reflector->isStatic() ||
          $method->reflector->getDeclaringClass()->isAbstract()) {
        continue;
      }
      $httpMethod = $temp[1];
      $path = $this->path . self::getPathFromMethod($method, $httpMethod);
      if (self::cannotBeTargetByPath($path) || !self::isCorrectHttpMethod($httpMethod)) {
        continue;
      }
      $self = $this;
      $callback = function() use ($self, $name) {        
        return call_user_func_array(array($self, $name), func_get_args());
      };

      $this->app->$httpMethod($path, $callback);
    }
  }

}
