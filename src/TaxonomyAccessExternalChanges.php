<?php
namespace Drupal\taxonomy_access;

/**
 * Tests the module's response to changes from other modules.
 */
class TaxonomyAccessExternalChanges extends TaxonomyAccessTestCase {
  public static function getInfo() {
    return array(
      'name' => 'External changes',
      'description' => "Test the module's response to changes from other modules.",
      'group' => 'Taxonomy Access Control',
    );
  }

  public function setUp() {
    parent::setUp();
  }

  /*
1. delete a term
2. delete a role
3. delete a field attachment
4. modify a field attachment
5. delete a vocabulary
6. add terms to node
7. remove terms from node
  */
}
