<?php

/**
 * DECO Library
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\prototypes\restmap;

use \deco\essentials\util\annotation as ann;
use \deco\essentials\exception as exc;

/**
 * Base class for instantiating services to REST end-points. 
 * Allows automatic binding of class methods to a slim application without 
 * explicitely writing which to bind and where
 * (http://www.slimframework.com/)
 */
abstract class Slim {

  use \deco\essentials\traits\deco\Annotations;

  /**
   * Slim application holder
   * 
   * @var \Slim\App
   */
  public static $app = null;

  /**
   * Automatically map methods starting with these keywords
   * 
   * @var array
   */
  public static $autoMap = array('get', 'post', 'delete', 'options', 'put');

  /**
   * Class to call in case of errors
   * 
   * @var \deco\essentials\rest\ErrorReportingInterface
   */
  public static $error = null;

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

  /**
   *
   * @var array
   */
  public $args = null;

  /**
   * Raw data read with file_get_contents('php://input')   
   * 
   * @var string
   */
  static protected $rawData;

  /**
   * Http response
   * 
   * @var \Psr\Http\Message\ResponseInterface
   */
  public $response = null;

  /**
   * Http request
   *
   * @var type \Psr\Http\Message\ServerRequestInterface
   */
  public $request = null;

  // public $body = null;

  /**
   * Which method was called based on the URI
   * 
   * @var string
   */
  public $calledMethod = null;

  /**
   * Configure static properties
   * 
   * @param array $array
   * @param $array['error'] is reference to instance that satisfies \deco\essentials\rest\ErrorReportingInterface
   */
  public static function configure($array) {
    //self::$rawData = file_get_contents('php://input');
    foreach ($array as $key => $value) {
      switch ($key) {
        case 'error':
          self::$error = $value;
          continue;
      }
    }
  }

  /**
   * Set raw data
   * 
   * @param string $str
   */
  public static function setRawData($str) {
    self::$rawData = $str;
  }

  /**
   * Construct with path
   * 
   * @param string $path   
   */
  public function __construct($path) {
    if (is_null(self::$app)) {
      self::$app = new \Slim\App();
    }
    if (is_null(self::$requestRelativeUri)) {
      self::setPathForRequest();
    }
    if (self::cannotBeTargetByPath($path)) {
      return;
    }
    $this->path = $path;
    $this->mapMethods();
  }

  /**
   * Run application
   */
  public static function run() {
    self::$app->run();
  }

  /**
   * Set content type to response
   * 
   * @param string $type
   * 
   * @return static
   */
  protected function setContentType($type) {
    $this->response = $this->response->withAddedHeader('Content-Type', $type);
    return $this;
  }

  /**
   * Set status code to response.
   * Must be public so that error handling can also call.
   * 
   * @param int $code
   * 
   * @return static
   */
  public function setStatusCode($code) {
    $this->response = $this->response->withStatus($code == 0 ? 400 : $code);
    return $this;
  }

  /**
   * Finalize response, writes to response given data as json if array is given.
   * Must be public so that error handling can also call.
   * 
   * @param custom $return
   * @return \Psr\Http\Message\ResponseInterface
   */
  public function finalize($return) {
    if (is_array($return)) {
      $this->setContentType('application/json');
      $return = json_encode($return);
    }
    return $this->response->write($return);
  }

  /**
   * For controlling access to given method
   * 
   * @param \deco\essentials\util\annotation\AnnotationCollection $method
   * 
   * @return bool
   * 
   * @throws exc\Permission if Permission is denied, never returns false
   */
  protected function permissionControl(ann\AnnotationCollection $method) {
    $cls = get_called_class();
    // based on method permissions
    $permissions = $method->getValue('permission', array());
    if (!is_array($permissions)) {
      $permissions = array($permissions);
    }
    $authStack = $method->getValue('auth', false);
    $clsAuthStack = null;
    if ($authStack == false) {
      $authStack = $cls::getClassAnnotationValue('auth', false);
      $clsAuthStack = $authStack;
    }
    if (count($permissions) > 0 &&
        $authStack !== false &&
        $this->hasPermission($permissions, $authStack)) {
      return true;
    }
    // based on general class permissions
    $clsPermissions = $cls::getClassAnnotationValue('permission', array());
    if (count($clsPermissions) > 0) {
      if (is_null($clsAuthStack)) {
        $clsAuthStack = $cls::getClassAnnotationValue('auth');
      }
      if ($this->hasPermission($clsPermissions, $clsAuthStack)) {
        return true;
      }
    }
    if (count($clsPermissions) == 0 && count($permissions) == 0) {
      return true;
    }
    // no access
    throw new exc\Permission(array('msg' => "Permission denied."));
  }

  /**
   * Checks if permission exists by going through a stack of methods and finally calling it with a permission
   * 
   * @param array/string $permissions Array or string accepted permissions
   * @param array of strings $stack Stack of permissions to traverse in the object, could use also path arguments
   * @return bool if finally permissions exist or not
   * @throws exc\Deco If requested parameter cannot be find in args
   */
  protected function hasPermission($permissions, $stack) {
    $prev = $this;
    foreach ($stack as $method) {
      if ($method == end($stack)) {
        if (!is_array($permissions)) {
          $permissions = array($permissions);
        }
        foreach ($permissions as $perm) {
          try {
            if ($prev->$method($perm)) {
              return true;
            }
          } catch (\Exception $e) {
            // do nothing
          }
        }
        return false;
      } else {
        // check if there is variable from $args to pass-by to the given method
        if (preg_match('#^([a-zA-Z]*)\(([a-zA-Z])\)$#', $method, $matches)) {
          if (!array_key_exists($matches[2], $this->args)) {
            $cls = get_called_class();
            throw new exc\Deco(array('msg' => "Error in @auth of '$cls'."));
          }
          $prev = $prev->$method($this->args[$matches[2]]);
        } else {
          $prev = $prev->$method();
        }
      }
    }
  }

  /**
   * Create path for URI from method parameter list
   * 
   * @param \ReflectionMethod $method
   * @return string
   */
  private static function getPathPartDefinedByParameters(\ReflectionMethod $method) {
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
  private static function getPathFromMethod(ann\AnnotationCollection $method, $httpMethod) {
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
      $self = $this; // Need to create this for closure, otherwise $this refers to Slim object
      $mw = $method->getValue('middleware', null);
      if (is_null($mw) && method_exists($this, 'middleware')) {
        $mw = 'middleware';
      }
      $callback = function($request, $response, $args) use ($method, $mw, $name, $self) {
        $fun = function() use ($request, $response, $args, $method, $mw, $name, $self) {
          $self->calledMethod = $name;
          $self->request = $request;
          $self->response = $response;
          $self->args = $args;
          $self->parsedBody = $this->request->getParsedBody();
          if (!is_null($mw)) {
            $self->$mw(); // user identification is done in middleware, therefore permission control cannot exist without middleware
            $self->permissionControl($method);
          }
          if ($method->reflector->getNumberOfParameters() == 0) {
            return $self->$name();
          }
          $array = array();
          $params = $method->reflector->getParameters();
          foreach ($params as $param) {
            if (array_key_exists($param->getName(), $self->args)) {
              array_push($array, $self->args[$param->getName()]);
            }
          }
          return call_user_func_array(array($self, $name), $array);
        };
        if (!is_null(self::$error)) {
          try {
            return $fun();
          } catch (\Exception $e) {
            $self::$error->setService($self);
            $parts = explode(PHP_EOL, $e->getTraceAsString());
            return $self::$error->report($e);
          }
        } else {
          return $fun();
        }
      };

      self::$app->$httpMethod($path, $callback);
    }
  }

  /**
   * Checks if URI can refer to given path 
   * 
   * @param string $path
   * @return bool
   */
  private static function cannotBeTargetByPath($path) {
    $path = preg_replace("#\[.*#", "", $path); // Remove optional parameters from the end
    $regex = preg_replace("#{[a-zA-z]*}#", "[^/]*", $path);
    return !preg_match('#^' . $regex . '#', self::$requestRelativeUri);
  }

  /**
   * Checks if the current path is going to the correct method
   * 
   * @param string $method
   * @return bool
   */
  private static function isCorrectHttpMethod($method) {
    return strtoupper($method) === strtoupper($_SERVER['REQUEST_METHOD']);
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
   * For sorting method base on their name lengths
   * 
   * @param array of $methods
   */
  private static function sortMethods(&$methods) {
    $sortFun = function($value1, $value2) {
      return strlen($value1->name) > strlen($value2->name) ? -1 : 1;
    };
    usort($methods, $sortFun);
  }

}
