<?php

namespace Drupal\taxonomy_access\Tests;

/**
 * Test callback cleanup during disabling of module works.
 *
 * @group taxonomy_access
 */
class TaxonomyAccessCallbackCleanupTest extends \Drupal\taxonomy_access\Tests\TaxonomyAccessTestCase {

  protected $profile = 'standard';

  public function setUp() {
    parent::setUp();
  }

  /**
   * Verifies that the module's callbacks are cleaned up during disable.
   */
  public function testCallbackCleanup() {

    // The problem only happens on new fields after the module is installed.
    $content_type = $this->drupalCreateContentType();

    // Create a new field with type taxonomy_term_reference.
    $field_name = \Drupal\Component\Utility\Unicode::strtolower();
    $field_type = [
      'field_name' => $field_name,
      'type' => 'taxonomy_term_reference',
      'cardinality' => 1,
    ];
    $field_type = field_create_field($field_type);

    // Add an instance of the field to content type.
    $field_instance = [
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => $content_type->name,
    ];
    $field_instance = field_create_instance($field_instance);

    // Trigger hook_disable to see if the callbacks are cleaned up.
    module_disable([
      'taxonomy_access'
      ], TRUE);

    // Create a user so that we can check if we can access the node add pages.
    $this->privileged_user = $this->drupalCreateUser([
      'bypass node access'
      ]);
    $this->drupalLogin($this->privileged_user);

    // If the callbacks are not cleaned up we would get a fatal error.
    $this->drupalGet('node/add/' . $content_type->name);
    $this->assertText(t('Create @name', ['@name' => $content_type->name]), t('New content can be added'));
  }

}
