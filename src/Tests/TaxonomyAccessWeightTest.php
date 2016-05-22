<?php
namespace Drupal\taxonomy_access\Tests;

/**
 * Test module weight.
 *
 * @group taxonomy_access
 */
class TaxonomyAccessWeightTest extends \Drupal\simpletest\WebTestBase {

  protected $profile = 'standard';

  public function setUp() {
    parent::setUp();
  }

  /**
   * Verifies that this module is weighted below the Taxonomy module.
   */
  public function testWeight() {

    // Verify weight.
    $tax_weight = db_query("SELECT weight FROM {system}
         WHERE name = 'taxonomy'")
      ->fetchField();
    $tax_access_weight = db_query("SELECT weight FROM {system}
         WHERE name = 'taxonomy_access'")
      ->fetchField();
    $this->assertTrue($tax_access_weight > $tax_weight, t("Weight of this module is @tax_access_weight. Weight of the Taxonomy module is @tax_weight.", [
      '@tax_access_weight' => $tax_access_weight,
      '@tax_weight' => $tax_weight,
    ]));

    // Disable module and set weight of the Taxonomy module to a high number.
    module_disable([
      'taxonomy_access'
      ], TRUE);
    db_update('system')
      ->fields(['weight' => rand(5000, 9000)])
      ->condition('name', 'taxonomy')
      ->execute();

    // Re-enable module and re-verify weight.
    module_enable([
      'taxonomy_access'
      ], TRUE);
    $tax_weight = db_query("SELECT weight FROM {system}
         WHERE name = 'taxonomy'")
      ->fetchField();
    $tax_access_weight = db_query("SELECT weight FROM {system}
         WHERE name = 'taxonomy_access'")
      ->fetchField();
    $this->assertTrue($tax_access_weight > $tax_weight, t("Weight of this module is @tax_access_weight. Weight of the Taxonomy module is @tax_weight.", [
      '@tax_access_weight' => $tax_access_weight,
      '@tax_weight' => $tax_weight,
    ]));
  }

}
