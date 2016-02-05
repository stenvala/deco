<?php

namespace deco\essentials\repositories;

class User extends \deco\essentials\prototypes\mono\Row {

  /**
   * @type: string
   * @validation: minLength: 2; maxLength: 20; deny: \s;
   * @index: true
   * @unique: true
   * @notNull: true   
   */
  protected $userName;

  /**
   * @type: string
   * @validation: minLength: 2; maxLength: 100;
   * @notNull: true
   */
  protected $givenName;

  /**
   * @type: string
   * @validation: minLength: 2; maxLength: 100;
   * @notNull: true
   */
  protected $familyName;

  /**
   * @type: string
   * @validation: email: true; maxLength: 100;
   */
  protected $email;

  /**
   * @type: string
   * @get: false  
   * @validation: maxLength: 100;
   */
  protected $passwordHash;

  static public function create($data) {
    if (!array_key_exists('password', $data)) {
      $data['password'] = hash('ripemd160', uniqid() . time());
    }
    $data['passwordHash'] = self::hashPassword($data['password']);
    unset($data['password']);
    return self::DECOcreate($data);
  }

  public function getName() {
    return $this->givenName_ . ' ' . $this->familyName_;
  }

  public function setPassword($password) {
    $passwordHash = self::hashPassword($password);
    $this->DECOset('passwordHash', $passwordHash, true);
  }

  public function verifyPassword($password) {
    return password_verify($password, $this->DECOget('passwordHash', true));
  }

  static public function hashPassword($password, $cost = 11) {
    $options = array('cost' => $cost);
    return password_hash($password, PASSWORD_BCRYPT, $options);
  }

}
