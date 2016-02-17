<?php

namespace deco\essentials\util;

class DynamicClassConstants {

  private static $constructedClassConstants = array();

  static public function create($files) {
    if (!is_array($files)) {
      $files = array($files);
    }
    foreach ($files as $pattern) {
      $files = glob($pattern);
      foreach ($files as $file) {       
        if (preg_match('#/([a-zA-Z]*)\.php$#', $file, $className)) {
          $contents = file_get_contents($file);
          preg_match('#namespace\s(.*);#', $contents, $namespace);
          $cls = '\\' . $namespace[1] . '\\' . $className[1];          
          self::constructClassConstantsFromAnnotationsFor($cls);
        }
      }
    }
  }

  static private function constructClassConstantsFromAnnotationsFor($cls) {    
    if (array_key_exists($cls, self::$constructedClassConstants)) {
      return;
    }
    self::$constructedClassConstants[$cls] = true;    
    $anns = $cls::getAnnotationsForProperties();
    foreach ($anns as $annCol) {
      if ('enum' == ($type = $annCol->getValue('type'))) {
        $values = $annCol->getValue('values');
        foreach ($values as $value) {          
          \runkit_constant_add($cls . "::" . $value, $value);
        }
      }
    }
    $clsAnnCol = $cls::getAnnotationsForClass();
    if ($clsAnnCol->has('const')) {
      $values = $clsAnnCol->getValue('values');
      foreach ($values as $value) {
        \runkit_constant_add($cls . "::" . $value, $value);
      }
    }
  }

}
