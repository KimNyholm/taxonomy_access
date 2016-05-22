<?php

namespace Drupal\taxonomy_access\Tests;

use Drupal\taxonomy_access\TaxonomyAccessService;

/**
 * Tests node access for all possible grant combinations.
 *
 * @group taxonomy_access
 */
class TaxonomyAccessNodeGrantTest extends \Drupal\taxonomy_access\Tests\TaxonomyAccessTestCase {

  // There are three roles for node access testing:
  // global_allow   Receives "Allow" in the global default.
  // global_ignore  Receives "Ignore" in the global default.
  // global_deny    Receives "Deny" in the global default.
  // All roles receive the same permissions for terms and vocab defaults.
  protected $roles = array();
  protected $role_config = array(
    'global_allow' => array(),
    'global_ignore' => array(),
    'global_deny' => array(),
  );

  protected $vocabs = array();

  public function setUp() {
    parent::setUp();

    // Configure roles with no additional permissions.
    foreach ($this->role_config as $role_name => $permissions) {
      $roleNumber= $this->taxonomyAccessService->roleIdToNumber($this->drupalCreateRole(array(), $role_name));
      $this->roles[$role_name] = $roleNumber;
    }

    $node_grants = array('view', 'update', 'delete');

    // Set up our testing taxonomy.

    // We will create 4 vocabularies: a, i, d, and nc
    // These names indicate what grant the vocab. default will have for view.
    // (NC means the vocab default is not configured.)

    $grant_types = array(
      'a' => array(),
      'i' => array(),
      'd' => array(),
      'nc' => array(),
    );

    // View alone can be used to test V/U/D because the logic is identical.
    foreach ($node_grants as $grant) {
      $grant_types['a'][$grant] = TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW;
      $grant_types['i'][$grant] = TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE;
      $grant_types['d'][$grant] = TaxonomyAccessService::TAXONOMY_ACCESS_NODE_DENY;
    }

    // Each vocabulary will have four parent terms in the same fashion:
    // a_parent, i_parent, d_parent, and nc_parent.

    // Each of these_parent terms will have children in each class, as well:
    // a_child, i_child, d_child, and nc_child.

    // So, each vocab looks something like:
    // - a_parent
    // - - a_child
    // - - i_child
    // - - d_child
    // - - nc_child
    // - i_parent
    // - - a_child
    // - - i_child
    // - - d_child
    // - - nc_child
    // - d_parent
    // - - a_child
    // - - i_child
    // - - d_child
    // - - nc_child
    // - nc_parent
    // - - a_child
    // - - i_child
    // - - d_child
    // - - nc_child

    $term_rows = array();
    $default_rows = array();
    $this->setUpAssertions = array();

    // Configure terms, vocabularies, and grants.
    foreach ($grant_types as $vocab_name => $default_grants) {
      // Create the vocabulary.
      $vocab_name = "v" . $vocab_name;
      $this->vocabs[$vocab_name] = array();
      $this->vocabs[$vocab_name]['vocab'] = $this->createVocab($vocab_name);
      $this->vocabs[$vocab_name]['terms'] = array();
      $vocab = $this->vocabs[$vocab_name]['vocab'];
      // Add a field for the vocabulary to pages.
      $this->createField($vocab_name);

      // Configure default grants for the vocabulary for each role.
      if (!empty($default_grants)) {
        foreach ($this->roles as $name => $role) {
          $default_rows[] =  $this->taxonomyAccessService->_taxonomy_access_format_grant_record($vocab->id(), $role, $default_grants, TRUE);
          $this->setUpAssertions[] = array(
            'grant' => $default_grants['view'],
            'query' => 'SELECT grant_view FROM {taxonomy_access_default} WHERE vid = :vid AND rid = :rid',
            'args' => array(':vid' => $vocab->id(), ':rid' => $role),
            'message' => t('Configured default grants for vocab %vocab, role %role', array('%vocab' => $vocab->id(), '%role' => $name)),
          );
        }
      }

      // Create terms.
      foreach ($grant_types as $parent_name => $parent_grants) {

        // Create parent term.
        $parent_name = $vocab_name . "__" . $parent_name . "_parent";
        $this->vocabs[$vocab_name]['terms'][$parent_name] =
          $this->createTerm($parent_name, $vocab->id());
        $parent_id = $this->vocabs[$vocab_name]['terms'][$parent_name]->id();

        // Configure grants for the parent term for each role.
        if (!empty($parent_grants)) {
          foreach ($this->roles as $name => $role) {
            $term_rows[] =  $this->taxonomyAccessService->_taxonomy_access_format_grant_record($parent_id, $role, $parent_grants);
            $this->setUpAssertions[] = array(
              'grant' => $parent_grants['view'],
              'query' => 'SELECT grant_view FROM {taxonomy_access_term} WHERE tid = :tid AND rid = :rid',
              'args' => array(':tid' => $parent_id, ':rid' => $role),
              'message' => t('Configured grants for term %term, role %role', array('%term' => $parent_name, '%role' => $name)),
            );
          }
        }
        // Create child terms.
        foreach ($grant_types as $child_name => $child_grants) {
          $child_name = $parent_name . "__" . $child_name . "_child";
          $this->vocabs[$vocab_name]['terms'][$child_name] =
            $this->createTerm($child_name, $vocab->id(), $parent_id);
          $child_id = $this->vocabs[$vocab_name]['terms'][$child_name]->id();

          // Configure grants for the child term for each role.
          if (!empty($child_grants)) {
            foreach ($this->roles as $name => $role) {
              $term_rows[] =  $this->taxonomyAccessService->_taxonomy_access_format_grant_record($child_id, $role, $child_grants);
              $this->setUpAssertions[] = array(
                'grant' => $child_grants['view'],
                'query' => 'SELECT grant_view FROM {taxonomy_access_term} WHERE tid = :tid AND rid = :rid',
                'args' => array(':tid' => $child_id, ':rid' => $role),
                'message' => t('Configured grants for term %term, role %role', array('%term' => $child_name, '%role' => $name)),
              );
            }
          }
        }
      }
    }

    // Set the grants.
    $this->taxonomyAccessService->taxonomy_access_set_default_grants($default_rows);
    $this->taxonomyAccessService->taxonomy_access_set_term_grants($term_rows);
  }

  /**
   * Verifies that all grants were properly stored during setup.
   */
  public function testSetUpCheck() {
    // Check that all records were properly stored.
    foreach ($this->setUpAssertions as $assertion) {
      $r = db_query($assertion['query'], $assertion['args'])->fetchField();
      $this->assertTrue(
        (is_numeric($r) && $r == $assertion['grant']),
        $assertion['message']
      );
    }
  }

  // Role config tests:
  // Create a role
  // Create a user with the role
  // Configure role grants via form
  // Add, with children, delete
  // Confirm records stored
  // Confirm node access properly updated
  // Go back and edit, repeat.
  // Disable role.
  // Confirm form.
  // Update node access if prompted.
  // Confirm records deleted.
  // Confirm node access updated.

  // 1. delete a term
  // 2. change a grant config
  // 3. delete a grant config
  // 4. change a vocab default
  // 5. delete a voacb default
  // 6. disable a role
  // 7. delete a role
  // 8. delete a field attachment
  // 9. delete a vocabulary
}
