<?php

namespace deco\essentials\services;

use \deco\essentials\exception as exp;
use \deco\essentials\util\annotation as ann;

/**
 * @contains: \deco\essentials\repositories\User  
 * @revealAs: User
 */
class User extends \deco\essentials\prototypes\service\RowWithPermissions {
 
  /**         
   * @collection: \deco\essentials\repositories\UserPermission   
   * @inherits: true
   */  
  protected $permissions;    
  
  /**
   * @repository: \deco\essentials\repositories\Session
   * @deleteAll: deleteAllSessions     
   * @passThrough: true
   */  
  protected $session;
  
  public function deleteAllSessions() {
    $sessionCls = self::getPropertyAnnotationValue('session', 'repository');
    $userCls = self::getClassAnnotationValue('contains');
    $foreign = $sessionCls::getReferenceToClass($userCls);    
    $foreignValue = $this->instance->get($foreign->value['parentColumn']);    
    self::db()->deleteById($foreign['table'],$foreign['column'],$foreignValue);
  }
  
  /**   
   * @return: string
   */
  public function createSession() {    
    $sessionCls = self::getPropertyAnnotationValue('session', 'repository');        
    $session = $sessionCls::createFor($this->instance);        
    $this->session = $session;
    return $session->getSession();
  }          
  
  static public function authenticateByPassword($userName, $password) {           
    $self = new static($userName,'userName');        
    if (!$self->instance->getIsActive()){
      throw new exp\exception\Authentication(array('msg' => 'User has been deactivated.'));
    }
    if (!$self->instance->verifyPassword($password)) {            
      throw new exp\exception\Authentication(array('msg' => 'False credentials.'));
    }    
    return $self;
  }

  static public function authenticateBySession($session) {        
    $self = new static($session->getOwnerId());    
    if (!$self->instance->getIsActive()){
      throw new exp\exception\Authentication(array('msg' => 'User has been deactivated.'));
    }    
    $self->session = $session;    
    return $self;
  }

  static public function authenticateBySessionString($session) {
    $sessionCls = self::getPropertyAnnotationValue('session', 'repository');        
    $sessionObj = $sessionCls::initBySession($session);    
    return self::authenticateBySession($sessionObj);
  } 
  
}
