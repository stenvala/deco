<?php

namespace deco\essentials\repositories;

class Recursion extends \deco\essentials\prototypes\mono\Row {  
  
  /**  
   * @type: integer
   * @references: table: self; column: id; allowNull: true; onDelete: cascade; onUpdate: cascade; default: NULL
   * @index: true
   * @default: NULL
   */
  protected $parentId;
  
  /**
   * @type: string
   * @validation: maxLength: 100   
   */
  protected $title;  
  
}
