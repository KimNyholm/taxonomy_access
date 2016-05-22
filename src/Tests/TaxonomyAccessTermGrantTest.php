<?php

namespace Drupal\taxonomy_access\Tests;

/**
 * Tests term grants for all possible grant combinations.
 *
 * @group taxonomy_access
 */
class TaxonomyAccessTermGrantTest extends \Drupal\taxonomy_access\Tests\TaxonomyAccessTestCase {
  // There are four roles for term access testing:
  // ctlt   Receives both "Create" and "List" in the global default.
  // ctlf   Receives "Create" but not "List" in the global default.
  // cflt   Receives "List" but not "Create" in the global default.
  // cflf   Receives neither "Create" nor "List" in the global default.
  // All roles receive the same permissions for terms and vocab defaults.
  protected $roles = array();
  protected $role_config = array(
    'ctlt' => array(),
    'ctlf' => array(),
    'cflt' => array(),
    'cflf' => array(),
  );

  protected $vocabs = array();

  public function setUp() {
    parent::setUp();

    // Configure roles with no additional permissions.
    foreach ($this->role_config as $role_name => $permissions) {
      $roleNumber= $this->taxonomyAccessService->roleIdToNumber($this->drupalCreateRole(array(), $role_name));
      $this->roles[$role_name] = $roleNumber;
    }

    // Set up our testing taxonomy.

    // We will create four vocabularies:
    // vctlt   Receives both "Create" and "List" in the vocabulary default.
    // vctlf   Receives "Create" but not "List" in the vocabulary default.
    // vcflt   Receives "List" but not "Create" in the vocabulary default.
    // vcflf   Receives neither "Create" nor "List" in the vocabulary default.
    $grant_combos = array(
      'ctlt' => array('create' => TAXONOMY_ACCESS_TERM_ALLOW, 'list' => TAXONOMY_ACCESS_TERM_ALLOW),
      'ctlf' => array('create' => TAXONOMY_ACCESS_TERM_ALLOW, 'list' => TAXONOMY_ACCESS_TERM_DENY),
      'cflt' => array('create' => TAXONOMY_ACCESS_TERM_DENY, 'list' => TAXONOMY_ACCESS_TERM_ALLOW),
      'cflf' => array('create' => TAXONOMY_ACCESS_TERM_DENY, 'list' => TAXONOMY_ACCESS_TERM_DENY),
    );

    // Grant all rows view, update, and delete.
    foreach ($grant_combos as $combo) {
      $combo['view'] = TAXONOMY_ACCESS_NODE_ALLOW;
      $combo['update'] = TAXONOMY_ACCESS_NODE_ALLOW;
      $combo['delete'] = TAXONOMY_ACCESS_NODE_ALLOW;
    }

    // Each vocabulary will have four parent terms in the same fashion:
    // ctlt_parent, ctlf_parent, cflt_parent, and cflf_parent.

    // Each of these_parent terms will have children in each class, as well:
    // ctlt_child, ctlf_child, cflt_child, and cflf_child.

    // So, each vocab looks something like:
    // - ctlt_parent
    // - - ctlt_child
    // - - ctlf_child
    // - - cflt_child
    // - - cflf_child
    // - ctlf_parent
    // - - ctlt_child
    // - - ctlf_child
    // - - cflt_child
    // - - cfl_fchild
    // - cflt_parent
    // - - ctlt_child
    // - - ctlf_child
    // - - cflt_child
    // - - cflf_child
    // - cflf_parent
    // - - ctlt_child
    // - - ctlf_child
    // - - cflt_child
    // - - cflf_child

    // Configure terms, vocabularies, and grants.
    foreach ($grant_combos as $vocab_name => $default_grants) {
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
            'create' => $default_grants['create'],
            'list' => $default_grants['list'],
            'query' => 'SELECT grant_create, grant_list FROM {taxonomy_access_default} WHERE vid = :vid AND rid = :rid',
            'args' => array(':vid' => $vocab->id(), ':rid' => $role),
            'message' => t('Configured default grants for vocab %vocab, role %role', array('%vocab' => $vocab->id(), '%role' => $name)),
          );
        }
      }
      // Create terms.
      foreach ($grant_combos as $parent_name => $parent_grants) {

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
              'create' => $parent_grants['create'],
              'list' => $parent_grants['list'],
              'query' => 'SELECT grant_create, grant_list FROM {taxonomy_access_term} WHERE tid = :tid AND rid = :rid',
              'args' => array(':tid' => $parent_id, ':rid' => $role),
              'message' => t('Configured grants for term %term, role %role', array('%term' => $parent_name, '%role' => $name)),
            );
          }
        }

        // Create child terms.
        foreach ($grant_combos as $child_name => $child_grants) {
          $child_name = $parent_name . "__" . $child_name . "_child";
          $this->vocabs[$vocab_name]['terms'][$child_name] =
            $this->createTerm($child_name, $vocab->id(), $parent_id);
          $child_id = $this->vocabs[$vocab_name]['terms'][$child_name]->id();

          // Configure grants for the child term for each role.
          if (!empty($child_grants)) {
            foreach ($this->roles as $name => $role) {
              $term_rows[] =  $this->taxonomyAccessService->_taxonomy_access_format_grant_record($child_id, $role, $child_grants);
              $this->setUpAssertions[] = array(
                'create' => $child_grants['create'],
                'list' => $child_grants['list'],
                'query' => 'SELECT grant_create, grant_list FROM {taxonomy_access_term} WHERE tid = :tid AND rid = :rid',
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
      $r = db_query($assertion['query'], $assertion['args'])->fetchAssoc();
      $this->assertTrue(
        (is_array($r)
          && $r['grant_create'] == $assertion['create']
          && $r['grant_list'] == $assertion['list']),
        $assertion['message']
      );
    }
  }
}
