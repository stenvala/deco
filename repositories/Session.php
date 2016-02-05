<?php

namespace deco\essentials\repositories;

class Session extends \deco\essentials\prototypes\mono\Row {
 
  /**
   * @type: integer
   * @references: table: User; column: id; onUpdate: cascade; onDelete: cascade;  
   * @index: true
   */
  protected $ownerId;
  /**   
   * @type: string
   * @validation: maxLength: 100
   * @index: true   
   * @unique: true
   */
  protected $session;
  /**
   * @type: string   
   * @default: null   
   */
  protected $identifier;
  /**
   * @type: timestamp   
   * @default: CURRENT_TIMESTAMP   
   * @set: false
   */
  protected $timeCreated;  
  /**
   * @type: timestamp
   * @notNull: true
   * @set: false
   * @default: CURRENT_TIMESTAMP
   * @onUpdate: CURRENT_TIMESTAMP   
   */
  protected $timeAccessed;
    
  static public function createFor($user) {
    $hash = hash('ripemd160', uniqid() . $user->getUserName()); 
    $data = array('ownerId' => $user->getId(), 'session' => $hash);
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
      $data['identifier'] = $_SERVER['HTTP_USER_AGENT'];
    }
    $session = self::create($data);        
    return $session;
  }

}
