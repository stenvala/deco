<?php

namespace deco\essentials\repositories;

/**
 * @noTable(private): true
 * @order: ord
 */
class RecursionWithOrder extends Recursion {  
    
  /**
   * Rows having same parentId are kept in order. This is the order keeper.
   * 
   * @type: integer
   * @index: true
   * @validation: minValue: 1   
   * @order: last // options: last, first for new that does not have defined order
   * @orderGroupBy: parentId
   */
  protected $ord;
 
  
  
}
