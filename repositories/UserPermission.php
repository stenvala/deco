<?php

namespace deco\essentials\repositories;

class UserPermission extends \deco\essentials\prototypes\mono\Row {
 
  /**
   * @type: integer
   * @references: table: User; column: id; onUpdate: cascade; onDelete: cascade;  
   * @index: true
   */
  protected $ownerId;
  
  /**   
   * @type: enum
   * @values: SUPER, 
   * \\: DELETE_USER, GET_USER, PUT_USER, POST_USER, CRUD_USER, ANY_USER
   * @index: true   
   */
  protected $permission;    
  
}
