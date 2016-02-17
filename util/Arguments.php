<?php

namespace deco\essentials\util;

class Arguments {

  // property value list to array
  static function pvToArray($ar) {
    $data = array();
    for ($i = 0; $i < count($ar); $i = $i + 2) {
      $data[$ar[$i]] = $ar[$i + 1];
    }
    return $data;
  }

}
