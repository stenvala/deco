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
 * Base class for automatic mappers of REST end-points
 */
abstract class AutoMapper {

  use \deco\essentials\traits\deco\Annotations;

  protected $app;  

  /**
   * Automatically map methods starting with these keywords
   * 
   * @var array
   */
  public static $autoMap = array('get', 'post', 'delete', 'options', 'put');

  /**
   * Request relative URI
   * 
   * @var string
   */
  public static $requestRelativeUri = null;

  /**
   * Base of service
   * 
   * @var string
   */
  public $path;

  public function __construct($app, $path) {
    $this->app = $app;    
    $this->path = $path;
    if (is_null(self::$requestRelativeUri)) {
      self::setPathForRequest();
    }
  }

  /**
   * Set path for URI
   */
  private static function setPathForRequest() {
    $script = $_SERVER['SCRIPT_NAME'];
    $uri = $_SERVER['REQUEST_URI'];
    $base = str_replace('/index.php', '', $script);
    self::$requestRelativeUri = str_replace($base, '', $uri);
  }

  /**
   * Create path for URI from method parameter list
   * 
   * @param \ReflectionMethod $method
   * @return string
   */
  protected static function getPathPartDefinedByParameters(\ReflectionMethod $method) {
    $path = '';
    $pathEnd = '';
    $reqPar = $method->getNumberOfRequiredParameters();
    foreach ($method->getParameters() as $key => $param) {
      if ($key < $reqPar) {
        $path .= '/{' . preg_replace('#_$#', '+', $param->name) . '}';
      } else {
        $path .= '[/{' . $param->name . '}';
        $pathEnd .= ']';
      }
    }
    return $path . $pathEnd;
  }

  /**
   * Create path for URI from method name
   * 
   * @param \deco\essentials\util\annotation\AnnotationCollection $method
   * @param string $httpMethod
   * @return string
   */
  protected static function getPathFromMethod($method, $httpMethod) {
    $customPath = $method->getValue('route', null);
    if (!is_null($customPath)) {
      return $customPath;
    }
    $reflector = $method->reflector;
    $name = preg_replace("#^$httpMethod#", "", $reflector->getName());
    if (($pos = strpos($name, '_')) !== false) {
      $name = substr($name, 0, $pos);
    }
    $path = '';
    while (strlen($name) > 0) {
      preg_match('@([A-Z])([a-z]*)([A-Z])?@', $name, $temp);
      if (count($temp) == 0) {
        $path .= '/' . strtolower($name);
        break;
      } else {
        $part = $temp[1] . $temp[2];
        $path .= '/' . strtolower($part);
        $name = substr($name, strlen($part));
      }
    }
    $path .= self::getPathPartDefinedByParameters($reflector);
    return $path;
  }

  /**
   * Checks if the current path is going to the correct method
   * 
   * @param string $method
   * @return bool
   */
  protected static function isCorrectHttpMethod($method) {
    return strtoupper($method) === strtoupper($_SERVER['REQUEST_METHOD']);
  }

  /**
   * Checks if URI can refer to given path 
   * 
   * @param string $path
   * @return bool
   */
  protected static function cannotBeTargetByPath($path) {
    $path = preg_replace("#\[.*#", "", $path); // Remove optional parameters from the end
    $regex = preg_replace("#{[a-zA-z]*}#", "[^/]*", $path);
    return !preg_match('#^' . $regex . '#', self::$requestRelativeUri);
  }

  /**
   * For sorting method base on their name lengths
   * 
   * @param array of $methods
   */
  protected static function sortMethods(&$methods) {
    $sortFun = function($value1, $value2) {
      return strlen($value1->name) > strlen($value2->name) ? -1 : 1;
    };
    usort($methods, $sortFun);
  }

}
