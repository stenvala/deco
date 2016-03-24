<?php

/**
 * DECO Library
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\robot;

/**
 * Builds automatically services from repositories
 * 
 */
class Services {

  private $repositories = array();

  public function readRepositories($pattern) {
    $classes = Util::filterAbstractClasses(Util::getClasses($pattern));
    $this->addRepositories($classes);
    $this->addDependencies();
  }

  public function getRepositories() {
    return $this->repositories;
  }

  protected function addRepositories($classes) {
    foreach ($classes as $cls) {
      $this->repositories[$cls] = new Repository($cls);
    }
  }

  protected function addDependencies() {
    foreach ($this->repositories as $cls => $repo) {
      $ref = new \ReflectionClass($cls);
      $namespace = $ref->getNamespaceName();
      $i = "$cls";
      $var = $cls::getForDatabaseProperties();
      foreach ($var as $key => $annCol) {
        if ($annCol->hasAnnotation('references')) {
          $value = $annCol->getValue('references');
          $ref = "$namespace\\{$value['table']}";
          if (array_key_exists('one-to-one', $value) && $value['one-to-one']) {
            $repo->addPeer($ref);
            $this->repositories[$ref]->addPeer($i);
          } else {
            $repo->addParent($ref);
            $this->repositories[$ref]->addChild($i);
          }
        }
      }
    }
  }

  public function writeServices($namespace, $to) {
    foreach ($this->repositories as $repo) {
      $repo->writeServices($namespace, $to);
    }
  }

}
