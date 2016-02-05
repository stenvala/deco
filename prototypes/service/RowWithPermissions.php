<?php

namespace deco\essentials\prototypes\service;

abstract class RowWithPermissions extends \deco\essentials\prototypes\mono\ServiceOnRow {
  
  /**      
   * @collection: abstract
   * @onConstruct: true      
   * @passThrough: true
   * @revealAs: Permissions    
   * @singular: permission
   * @has: match: permission
   * @add: property: permission
   * @remove: property: permission   
   */  
  protected $permissions;             
  
}
