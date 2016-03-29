<?php

/**
 * @file
 * Contains Drupal\taxonomy_access\TaxonomyAccessService.
 */

namespace Drupal\taxonomy_access;

class TaxonomyAccessService {
  
  protected $demo_value;
  
  public function __construct() {
    $this->demo_value = 'Upchuk';
  }
  
  public function getDemoValue() {
    return $this->demo_value;
  }
  
}

