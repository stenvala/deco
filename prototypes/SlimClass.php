<?php

namespace deco\essentials\prototypes;

use \deco\essentials\util\annotation as ann;
use \deco\essentials\exception as exc;

abstract class SlimClass {

  use \deco\essentials\traits\deco\Annotations;

  const CT_PLAIN = 0;
  const CT_JSON = 1;
  const CT_HTML = 2;

  /**
   * @type: \Slim\App
   */
  public static $app = null;
  public static $autoMap = array('get', 'post', 'delete', 'options', 'put');
  public static $error = null;
  public static $requestRelativeUri = null;
  public $path;
  public $args = null;
  public $response = null;
  public $request = null;
  public $body = null;
  public $calledMethod = null;

  public static function configure($array) {
    foreach ($array as $key => $value) {
      switch ($key) {
        case 'error':
          self::$error = $value;
          continue;
      }
    }
  }

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

  public static function run() {
    self::$app->run();
  }

  public function setContentType($type) {
    $this->response = $this->response->withAddedHeader('Content-Type', $type);
  }

  public function setStatusCode($code) {
    $code = $code == 0 ? 400 : $code;
    $this->response = $this->response->withStatus($code);
  }

  public function finalize($return) {
    if (is_array($return)) {
      $this->setContentType('application/json');
    }        
    return $this->response->write(json_encode($return));
  }

  protected function permissionControl(ann\AnnotationCollection $method) {
    $cls = get_called_class();
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
      return;
    }
    $clsPermissions = $cls::getClassAnnotationValue('permission', array());
    if (count($clsPermissions) > 0) {
      if (is_null($clsAuthStack)) {
        $clsAuthStack = $cls::getClassAnnotationValue('auth');
      }
      if ($this->hasPermission($clsPermissions, $clsAuthStack)) {
        return;
      }
    }
    if (count($clsPermissions) == 0 && count($permissions) == 0) {
      return;
    }
    // no access
    throw new exc\Permission(array('msg' => "Permission denied."));
  }

  protected function hasPermission($permissions, $stack) {    
    $prev = $this;    
    foreach ($stack as $method) {
      if ($method == end($stack)) {      
        return $prev->$method($permissions);
      } else {
        // check if there is variable from $args to send
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
            $self->$mw();
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
            return $self::$error->report($e);
          }
        } else {
          return $fun();
        }
      };
      
      self::$app->$httpMethod($path, $callback);
    }
  }

  private static function cannotBeTargetByPath($path) {
    $path = preg_replace("#\[.*#","",$path); // Remove optional parameters
    $regex = preg_replace("#{[a-zA-z]*}#","[^/]*",$path);        
    return !preg_match('#^' . $regex . '#', self::$requestRelativeUri);
  }

  private static function isCorrectHttpMethod($method) {
    return strtoupper($method) === strtoupper($_SERVER['REQUEST_METHOD']);
  }

  private static function setPathForRequest() {
    $script = $_SERVER['SCRIPT_NAME'];
    $uri = $_SERVER['REQUEST_URI'];
    $base = str_replace('/index.php', '', $script);
    self::$requestRelativeUri = str_replace($base, '', $uri);
  }

  private static function sortMethods(&$methods) {
    $sortFun = function($value1, $value2) {
      return strlen($value1->name) > strlen($value2->name) ? -1 : 1;
    };
    usort($methods, $sortFun);
  }

}
