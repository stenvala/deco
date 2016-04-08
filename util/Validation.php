<?php

// GUMP: https://github.com/Wixel/GUMP

namespace deco\essentials\util;

class Validation {

  public static function validateObjectData($anns, $data, $requireAll = true) {
    $failed = array();
    foreach ($data as $column => $value) {
      if (array_key_exists($column, $anns)) { // Check for enum
        $annCol = $anns[$column];
        if ($annCol->getValue('type') == 'enum') {          
          $accept = $annCol->getValue('values',array());          
          if (!in_array($value, $accept)) {            
            array_push($failed, $column);            
          } 
          continue;
        }        
        $validation = $annCol->getValue('validation', array());        
        if (count($validation) == 0) {
          continue;
        }
        if (!self::validate($validation, $value, $annCol->getValue('type'))) {
          array_push($failed, $column);
        }
      }
    }
    if (count($failed) > 0) {
      throw new \deco\essentials\exception\Validation(array(
      'fields' => $failed
      ));
    }
  }

  public static function validate($validation, $value, $type) {
    $rules = array('required');
    if (array_key_exists('email', $validation)) {
      array_push($rules, 'valid_email');
    }
    if (array_key_exists('starts', $validation)) {
      array_push($rules, 'starts,' . $validation['starts']);
    }
    if (array_key_exists('regex', $validation)) {
      $regex = is_array($validation['regex']) ? implode(',',$validation['regex']) : $validation['regex'];
      error_log($regex);
      error_log($value);
      if (!preg_match($regex, $value)) {
        return false;
      }
    }
    if ($type == 'string') {
      if (array_key_exists('maxLength', $validation)) {
        array_push($rules, 'max_len,' . $validation['maxLength']);
      }
      if (array_key_exists('minLength', $validation)) {
        if ($validation['minLength'] === 0 && strlen($value) == 0){
          return true;
        }
        array_push($rules, 'min_len,' . $validation['minLength']);
      }
    } else if ($type == 'integer' || $type == 'timestamp') {
      
      if ($type == 'integer') {
        array_push($rules, 'integer');
      }
      if (array_key_exists('min', $validation)) {
        array_push($rules, 'min_numeric,' . $validation['min']);        
      }
      if (array_key_exists('max', $validation)) {
        array_push($rules, 'max_numeric,' . $validation['max']);
      }
    }
    if (count($rules) == 1) {
      return true;
    }  
    $valid = \GUMP::is_valid(array('temp' => $value), array('temp' => implode('|', $rules)));
    return $valid === true;
  }

}
