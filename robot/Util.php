<?php

/**
 * DECO Library
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\robot;

/**
 * Utilities for robots
 * 
 */
class Util {

  static public function getClasses($pattern) {
    $classes = array();
    $patterns = is_array($pattern) ? $pattern : array($pattern);
    foreach ($patterns as $pat) {
      $files = glob($pat);
      foreach ($files as $file) {
        $cls = Util::getClass($file);
        array_push($classes,$cls);
      }
    }
    return $classes;
  }

  static public function getClass($file) {
    $str = file_get_contents($file);
    preg_match('#^namespace\s*([a-zA-Z' . preg_quote('\\') . ']*)#m', $str, $matches);
    $namespace = $matches[1];
    preg_match('#class\s*([a-zA-Z]*)\s*#m', $str, $matches);
    $class = $matches[1];
    return "$namespace\\$class";
  }

  static public function filterAbstractClasses($classes){
    $cb = function($cls){
      $rc = new \ReflectionClass($cls);
      return !$rc->isAbstract();
    };
    return array_filter($classes, $cb);
    
    
  }
  
}
