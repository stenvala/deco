<?php

/**
 * DECO Framework
 * 
 * @link https://github.com/stenvala/deco-essentials
 * @copyright Copyright (c) 2016- Antti Stenvall
 * @license https://github.com/stenvala/deco-essentials/blob/master/LICENSE (MIT License)
 */

namespace deco\essentials\rest;

/**
 * Interface for reporting errors that occur in a SlimClass service
 */
interface ErrorReportingInterface {

  public function setService($obj);
  
  public function report(\deco\essentials\exception $e);
  
  
}
