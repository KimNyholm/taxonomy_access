<?php
namespace Drupal\taxonomy_access\Tests;

/**
 * Tests the module's configuration forms.
 *
 * @group z_taxonomy_access
 */
class TaxonomyAccessConfigTest extends \Drupal\taxonomy_access\Tests\TaxonomyAccessTestCase {

  protected $articles = array();
  protected $pages = array();
  protected $vocabs = array();
  protected $terms = array();

  public static function getInfo() {
    return array(
      'name' => 'Configuration forms',
      'description' => 'Test module configuration forms.',
      'group' => 'Taxonomy Access Control',
    );
  }

  public function setUp() {
    parent::setUp();

    // Add two taxonomy fields to pages.
    foreach (array('v1', 'v2') as $vocab) {
      $this->vocabs[$vocab] = $this->createVocab($vocab);
      $this->createField($vocab);
      $this->terms[$vocab . 't1'] =
        $this->createTerm($vocab . 't1', $this->vocabs[$vocab]);
      $this->terms[$vocab . 't2'] =
        $this->createTerm($vocab . 't2', $this->vocabs[$vocab]);
    }

    // Set up a variety of nodes with different term combinations.
    $this->articles['no_tags'] = $this->createArticle();
    $this->articles['one_tag'] =
      $this->createArticle(array($this->randomName()));
    $this->articles['two_tags'] =
      $this->createArticle(array($this->randomName(), $this->randomName()));

    $this->pages['no_tags'] = $this->createPage();
    foreach ($this->terms as $t1) {
      $this->pages[$t1->name] = $this->createPage(array($t1->name));
      foreach ($this->terms as $t2) {
        $this->pages[$t1->name . '_' . $t2->name] =
          $this->createPage(array($t1->name, $t2->name));
      }
    }
  }

  /**
    // Add two taxonomy fields to pages.
    foreach (array('v1', 'v2') as $vocab) {
      $this->vocabs[$vocab] = $this->createVocab($vocab);
      $this->createField($vocab);
      $this->terms[$vocab . 't1'] =
        $this->createTerm($vocab . 't1', $this->vocabs[$vocab]);
      $this->terms[$vocab . 't2'] =
        $this->createTerm($vocab . 't2', $this->vocabs[$vocab]);
    }

    // Set up a variety of nodes with different term combinations.
    $this->articles['no_tags'] = $this->createArticle();
    $this->articles['one_tag'] =
      $this->createArticle(array($this->randomName()));
    $this->articles['two_tags'] =
      $this->createArticle(array($this->randomName(), $this->randomName()));

    $this->pages['no_tags'] = $this->createPage();
    foreach ($this->terms as $t1) {
      $this->pages[$t1->name] = $this->createPage(array($t1->name));
      foreach ($this->terms as $t2) {
        $this->pages[$t1->name . '_' . $t2->name] =
          $this->createPage(array($t1->name, $t2->name));
      }
    }
  }

  /**
   * Creates a page with the specified terms.
   *
   * @param array $terms
   *   (optional) An array of term names to tag the page.  Defaults to array().
   *
   * @return object
   *   The node object.
   */
  function createPage($tags = array()) {
    $v1 = array();
    $v2 = array();

    foreach ($tags as $name) {
      switch ($this->terms[$name]->vid) {
        case ($this->vocabs['v1']->vid):
          $v1[] = array('tid' => $this->terms[$name]->tid);
          break;

        case ($this->vocabs['v2']->vid):
          $v2[] = array('tid' => $this->terms[$name]->tid);
          break;
      }
    }

    // Bloody $langcodes.
    $v1 = array(\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED => $v1);
    $v2 = array(\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED => $v2);

    $settings = array(
      'type' => 'page',
      'v1' => $v1,
      'v2' => $v2,
    );

    return $this->drupalCreateNode($settings);
  }

/*
@todo
- check anon and auth forms
- add recursive for vocab and for term
- change multiple
- delete multiple
- configure create and list
 */

  /**
   * Tests the initial state of the test environment.
   *
   * Verifies that:
   * - Access to all nodes is denied for anonymous users.
   * - The main admin page provides the correct configuration links.
   */
  public function testSetUpCheck() {
    // Visit all nodes as anonymous and verify that access is denied.
    foreach ($this->articles as $key => $article) {
      $this->drupalGet('node/' . $article->nid);
      $this->assertResponse(403, t("Access to %name article (nid %nid) is denied.", array('%name' => $key, '%nid' => $article->nid)));
    }
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->nid);
      $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
    }

    // Log in as the regular_user.
    $this->drupalLogin($this->users['regular_user']);

    // Visit all nodes and verify that access is denied.
    foreach ($this->articles as $key => $article) {
      $this->drupalGet('node/' . $article->nid);
      $this->assertResponse(403, t("Access to %name article (nid %nid) is denied.", array('%name' => $key, '%nid' => $article->nid)));
    }
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->nid);
      $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
    }

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Confirm that only edit links are available for anon. and auth.
    $this->checkRoleConfig(array(
      \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE => TRUE,
      \Drupal\Core\Session\AccountInterface::AUTHENTICATED_RID => TRUE,
    ));
  }

  /**
   * Tests configuring a global default.
   *
   * Verifies that:
   * - Access is updated for all nodes when there are no other configurations.
   * - Access is updated for the correct nodes when there are specific term
   *    and vocabulary configurations.
   */
  public function testGlobalDefaultConfig() {
    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Use the admin form to give anonymous view allow in the global default.
    $this->drupalGet(TAXONOMY_ACCESS_CONFIG . '/role/' . \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE . '/edit');
    $edit = array();
    $this->configureFormRow($edit, TAXONOMY_ACCESS_GLOBAL_DEFAULT, TAXONOMY_ACCESS_VOCABULARY_DEFAULT, TAXONOMY_ACCESS_NODE_ALLOW);
    $this->drupalPost(NULL, $edit, 'Save all');

    // Log out.
    $this->drupalLogout();

    // Visit each node and verify that access is allowed.
    foreach ($this->articles as $key => $article) {
      $this->drupalGet('node/' . $article->nid);
      $this->assertResponse(200, t("Access to %name article (nid %nid) is allowed.", array('%name' => $key, '%nid' => $article->nid)));
    }
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->nid);
      $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->nid)));
    }

    // Add some specific configurations programmatically.

    // Set the v1 default to view allow.
    $default_config = _taxonomy_access_format_grant_record(
      $this->vocabs['v1']->vid, \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE, array('view' => TAXONOMY_ACCESS_NODE_ALLOW), TRUE
    );
    taxonomy_access_set_default_grants(array($default_config));

    // Set v1t1 and v2t1 to view allow.
    $term_configs = array();
    foreach (array('v1t1', 'v2t1') as $name) {
      $term_configs[] = _taxonomy_access_format_grant_record(
        $this->terms[$name]->vid, \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE, array('view' => TAXONOMY_ACCESS_NODE_ALLOW)
      );
    }
    taxonomy_access_set_term_grants($term_configs);

    // This leaves articles and the v2t2 page controlled by the global default.

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Use the admin form to give anonymous view deny in the global default.
    $this->drupalGet(TAXONOMY_ACCESS_CONFIG . '/role/' . \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE . '/edit');
    $edit = array();
    $this->configureFormRow($edit, TAXONOMY_ACCESS_GLOBAL_DEFAULT, TAXONOMY_ACCESS_VOCABULARY_DEFAULT, TAXONOMY_ACCESS_NODE_DENY);
    $this->drupalPost(NULL, $edit, 'Save all');

    // Log out.
    $this->drupalLogout();

    // Visit each artile and verify that access is denied.
    foreach ($this->articles as $key => $article) {
      $this->drupalGet('node/' . $article->nid);
      $this->assertResponse(403, t("Access to %name article (nid %nid) is denied.", array('%name' => $key, '%nid' => $article->nid)));
    }

    // Visit each page.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->nid);

      switch (TRUE) {
        // If the page has no tags, access should be denied.
        case ($key == 'no_tags'):
        // If the page is tagged with v2t2, access should be denied.
        case (strpos($key, 'v2t2') !== FALSE):
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
          break;

        // Otherwise, access should be allowed.
        default:
          $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->nid)));
          break;
      }
    }
  }

  /**
   * Tests configuring vocabulary defaults.
   *
   * Verifies that:
   * - Access is updated correctly when the vocabulary default is added and
   *   configured.
   * - Access is updated correctly when there is a specific term configuration
   *   in the vocabulary.
   * - Access is updated correctly when multiple defaults are changed.
   * - Access is updated correctly when the vocabulary default is deleted.
   */
  public function testVocabularyDefaultConfig() {
    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Enable the vocabulary.
    $this->drupalGet(TAXONOMY_ACCESS_CONFIG . '/role/' . \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE . '/edit');
    // @todo
    //   - Ensure that all vocabularies are options in the "Add" fieldset.
    $edit = array();
    $edit['enable_vocab'] = $this->vocabs['v1']->vid;
    $this->drupalPost(NULL, $edit, t('Add'));

    // @todo
    //   - Ensure that the vocabulary is removed from the "Add" fieldset.
    //   - Ensure that the fieldset for the vocabulary appears.
    //   - Ensure that no other fieldsets or rows appear.

    // Give anonymous view allow for the v1 default.
    $edit = array();
    $this->configureFormRow($edit, $this->vocabs['v1']->vid, TAXONOMY_ACCESS_VOCABULARY_DEFAULT, TAXONOMY_ACCESS_NODE_ALLOW);
    $this->drupalPost(NULL, $edit, 'Save all');

    // Log out.
    $this->drupalLogout();

    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->nid);

      // If the page is tagged with a v1 term, access should be allowed.
      if (strpos($key, 'v1') !== FALSE) {
        $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->nid)));
      }
      // Otherwise, access should be denied.
      else {
        $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
      }
    }

    // Programmatically enable v2 and add a specific configuration for v2t1.
    taxonomy_access_enable_vocab($this->vocabs['v2']->vid, \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE);
    $term_config = _taxonomy_access_format_grant_record(
      $this->terms['v2t1']->tid, \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE, array('view' => TAXONOMY_ACCESS_NODE_IGNORE)
    );
    taxonomy_access_set_term_grants(array($term_config));

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Use the admin form to give anonymous view deny for the v2 default.
    $this->drupalGet(TAXONOMY_ACCESS_CONFIG . '/role/' . \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE . '/edit');
    $edit = array();
    $this->configureFormRow($edit, $this->vocabs['v2']->vid, TAXONOMY_ACCESS_VOCABULARY_DEFAULT, TAXONOMY_ACCESS_NODE_DENY);
    $this->drupalPost(NULL, $edit, 'Save all');

    $this->drupalGet(TAXONOMY_ACCESS_CONFIG . '/role/' . \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE . '/edit');

    // Log out.
    $this->drupalLogout();
    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->nid);

      switch (TRUE) {
        // If the page is tagged with v2t2, the v2 default is inherited: Deny.
        case (strpos($key, 'v2t2') !== FALSE):
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
          break;

        // Otherwise, if the page is tagged with v1, it's allowed.
        case (strpos($key, 'v1') !== FALSE):
          $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->nid)));
          break;

        // Access should be denied by default.
        default:
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
          break;
      }
    }

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Use the form to change the configuration: Allow for v2; Deny for v1.
    $this->drupalGet(TAXONOMY_ACCESS_CONFIG . '/role/' . \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE . '/edit');
    $edit = array();
    $this->configureFormRow($edit, $this->vocabs['v2']->vid, TAXONOMY_ACCESS_VOCABULARY_DEFAULT, TAXONOMY_ACCESS_NODE_ALLOW);
    $this->configureFormRow($edit, $this->vocabs['v1']->vid, TAXONOMY_ACCESS_VOCABULARY_DEFAULT, TAXONOMY_ACCESS_NODE_DENY);
    $this->drupalPost(NULL, $edit, 'Save all');

    // Log out.
    $this->drupalLogout();

    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->nid);

      switch (TRUE) {
        // If the page is tagged with a v1 term, access should be denied.
        case (strpos($key, 'v1') !== FALSE):
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
          break;

        // Otherwise, if the page is tagged with v2t2, the default is
        // inherited and access should be allowed.
        case (strpos($key, 'v2t2') !== FALSE):
          $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->nid)));
          break;

        // Access should be denied by default.
        default:
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
          break;
      }
    }

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Use the admin form to disable v1.
    $this->drupalGet(TAXONOMY_ACCESS_CONFIG . '/role/' . \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE . '/edit');
    $this->clickLink(t('delete all v1 access rules'));
    $this->assertText("Are you sure you want to delete all Taxonomy access rules for v1", t('Disable form for vocabulary loaded.'));
    $this->drupalPost(NULL, array(), 'Delete all');

    // Log out.
    $this->drupalLogout();

    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->nid);

      // If the page is tagged with v2t2, access should be allowed.
      if (strpos($key, 'v2t2') !== FALSE) {
        $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->nid)));
      }
      // Otherwise, access should be denied.
      else {
        $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
      }
    }
  }

  /**
   * Tests configuring specific terms.
   *
   * Verifies that:
   * - Access is updated correctly when the term configuration is added.
   * - Access is updated correctly when there is a vocabulary default.
   * - Access is updated correctly when multiple configurations are changed.
   * - Access is updated correctly when the term configuration is deleted.
   */
  public function testTermConfig() {
    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Use the admin form to enable v1 and give anonymous view allow for v1t1.
    $this->drupalGet(TAXONOMY_ACCESS_CONFIG . '/role/' . \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE . '/edit');
    $edit = array();
    $edit['enable_vocab'] = $this->vocabs['v1']->vid;
    $this->drupalPost(NULL, $edit, t('Add'));
    $edit = array();
    $this->addFormRow($edit, $this->vocabs['v1']->vid, $this->terms['v1t1']->tid, TAXONOMY_ACCESS_NODE_ALLOW);
    $this->drupalPost(NULL, $edit, 'Add');

    // Log out.
    $this->drupalLogout();

    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->nid);

      // If the page is tagged with v1t1, access should be allowed.
      if (strpos($key, 'v1t1') !== FALSE) {
        $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->nid)));
      }
      // Otherwise, access should be denied.
      else {
        $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
      }
    }

    // Enable v2 programmatically.
    taxonomy_access_enable_vocab($this->vocabs['v2']->vid, \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE);

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Use the admin form to give anonymous view deny for v2t1.
    $this->drupalGet(TAXONOMY_ACCESS_CONFIG . '/role/' . \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE . '/edit');
    $edit = array();
    $this->addFormRow($edit, $this->vocabs['v2']->vid, $this->terms['v2t1']->tid, TAXONOMY_ACCESS_NODE_DENY);
    $this->drupalPost(NULL, $edit, 'Add');

    // Log out.
    $this->drupalLogout();

    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->nid);

      switch (TRUE) {
        // If the page is tagged with v2t1, access should be denied.
        case (strpos($key, 'v2t1') !== FALSE):
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
          break;

        // Otherwise, if the page is tagged with v1t1, it's allowed.
        case (strpos($key, 'v1t1') !== FALSE):
          $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->nid)));
          break;

        // Access should be denied by default.
        default:
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
          break;
      }
    }

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Use the form to change the configuration: Allow for v2t1; Deny for v1t1.
    $this->drupalGet(TAXONOMY_ACCESS_CONFIG . '/role/' . \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE . '/edit');
    $edit = array();
    $this->configureFormRow(
      $edit, $this->vocabs['v2']->vid, $this->terms['v2t1']->tid, TAXONOMY_ACCESS_NODE_ALLOW
    );
    $this->configureFormRow(
      $edit, $this->vocabs['v1']->vid, $this->terms['v1t1']->tid, TAXONOMY_ACCESS_NODE_DENY
    );
    $this->drupalPost(NULL, $edit, 'Save all');

    // Log out.
    $this->drupalLogout();

    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->nid);

      switch (TRUE) {
        // If the page is tagged with v1t1, access should be denied.
        case (strpos($key, 'v1t1') !== FALSE):
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
          break;

        // Otherwise, if the page is tagged with v2t1, it's allowed.
        case (strpos($key, 'v2t1') !== FALSE):
          $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->nid)));
          break;

        // Access should be denied by default.
        default:
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
          break;
      }
    }

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Use the form to delete the v2t1 configuration.
    $this->drupalGet(TAXONOMY_ACCESS_CONFIG . '/role/' . \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE . '/edit');
    $edit = array();
    $edit["grants[{$this->vocabs['v2']->vid}][{$this->terms['v2t1']->tid}][remove]"] = 1;
    $this->drupalPost(NULL, $edit, 'Delete selected');

    // Log out.
    $this->drupalLogout();

    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->nid);

      // Access to all pages should be denied.
      $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
    }
  }

  /**
   * Tests adding a term configuration with children.
   *
   * @todo
   *   Check that node access is updated for these as well.
   */
  public function testTermWithChildren() {
    // Create some additional taxonomy terms in a hierarchy:
    // v1
    // - v1t1
    // - - v1t1c1
    // - - - v1t1c1g1
    // - - - v1t1c1g2
    // - - v1t1c2
    // - - v1t2

    $this->terms['v1t1c1'] = $this->createTerm(
      'v1t1c1',
      $this->vocabs['v1'],
      $this->terms['v1t1']->tid
    );
    $this->terms['v1t1c2'] = $this->createTerm(
      'v1t1c2',
      $this->vocabs['v1'],
      $this->terms['v1t1']->tid
    );
    $this->terms['v1t1c1g1'] = $this->createTerm(
      'v1t1c1g1',
      $this->vocabs['v1'],
      $this->terms['v1t1c1']->tid
    );
    $this->terms['v1t1c1g2'] = $this->createTerm(
      'v1t1c1g2',
      $this->vocabs['v1'],
      $this->terms['v1t1c1']->tid
    );

    // Add pages tagged with each.
    foreach (array('v1t1c1', 'v1t1c2', 'v1t1c1g1', 'v1t1c1g2') as $name) {
      $this->pages[$name] = $this->createPage(array($name));
    }

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Enable v1 programmatically.
    taxonomy_access_enable_vocab($this->vocabs['v1']->vid, \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE);
    // Use the admin form to give anonymous view allow for v1t1 and children.
    $this->drupalGet(TAXONOMY_ACCESS_CONFIG . '/role/' . \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE . '/edit');
    $edit = array();
    $edit["new[{$this->vocabs['v1']->vid}][recursive]"] = 1;
    $this->addFormRow($edit, $this->vocabs['v1']->vid, $this->terms['v1t1']->tid, TAXONOMY_ACCESS_NODE_ALLOW);
    $this->drupalPost(NULL, $edit, 'Add');

  }

  /**
   * Tests enabling and disabling TAC for a custom role.
   */
  public function testRoleEnableDisable() {
    // Save some typing.
    $rid = $this->user_roles['regular_user']->rid;
    $name = $this->user_roles['regular_user']->name;

    // Check that the role is disabled by default.
    $this->checkRoleConfig(array(
      \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE => TRUE,
      \Drupal\Core\Session\AccountInterface::AUTHENTICATED_RID => TRUE,
      $rid => FALSE,
    ));

    // Test enabling the role.
    $this->drupalGet(TAXONOMY_ACCESS_CONFIG . "/role/$rid/edit");

    // Check that there is:
    // - An enable link
    // - No disable link
    // @todo
    //   - No grant tables.
    $this->checkRoleEnableLink($rid, TRUE);
    $this->checkRoleDisableLink($rid, FALSE);

    // Enable the role and check that there is:
    // - A disable link
    // - No enable link
    // @todo
    //   - A global default table (with correct values?)
    //   - An "Add vocabulary" fieldset.
    //   - No vocabulary fieldsets or term data.
    $this->clickLink(format_string('Enable @name', array('@name' => $name)));
    $this->checkRoleEnableLink($rid, FALSE);
    $this->checkRoleDisableLink($rid, TRUE);

    // Update the global default to allow view.
    $edit = array();
    $this->configureFormRow($edit, TAXONOMY_ACCESS_GLOBAL_DEFAULT, TAXONOMY_ACCESS_VOCABULARY_DEFAULT, TAXONOMY_ACCESS_NODE_ALLOW);
    $this->drupalPost(NULL, $edit, 'Save all');

    // Confirm that all three roles are enabled.
    $this->checkRoleConfig(array(
      \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE => TRUE,
      \Drupal\Core\Session\AccountInterface::AUTHENTICATED_RID => TRUE,
      $rid => TRUE,
    ));

    // Check that the role is configured.
    $r =
      db_query(
        'SELECT grant_view FROM {taxonomy_access_default}
         WHERE vid = :vid AND rid = :rid',
        array(':vid' => TAXONOMY_ACCESS_GLOBAL_DEFAULT, ':rid' => $rid)
      )
      ->fetchField();
    $this->assertTrue($r == TAXONOMY_ACCESS_NODE_ALLOW, t('Used form to grant the role %role view in the global default.', array('%role' => $name)));

    // Log in as the regular_user.
    $this->drupalLogout();
    $this->drupalLogin($this->users['regular_user']);

    // Visit each node and verify that access is allowed.
    foreach ($this->articles as $key => $article) {
      $this->drupalGet('node/' . $article->nid);
      $this->assertResponse(200, t("Access to %name article (nid %nid) is allowed.", array('%name' => $key, '%nid' => $article->nid)));
    }
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->nid);
      $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->nid)));
    }

    // Log in as the administrator.
    $this->drupalLogout();
    $this->drupalLogin($this->users['site_admin']);

    // Test disabling the role.
    $this->drupalGet(TAXONOMY_ACCESS_CONFIG . "/role/$rid/edit");
    $this->clickLink(t('Disable @name', array('@name' => $name)));
    $this->assertText("Are you sure you want to delete all taxonomy access rules for the role $name", t('Disable form for role loaded.'));
    $this->drupalPost(NULL, array(), 'Delete all');

    // Confirm that a confirmation message appears.
    $this->assertText("All taxonomy access rules deleted for role $name", t('Confirmation message found.'));

    // Check that there is:
    // - An enable link
    // - No disable link
    // @todo
    //   - No grant tables.
    $this->checkRoleEnableLink($rid, TRUE);
    $this->checkRoleDisableLink($rid, FALSE);

    // Confirm edit/enable/disable links are in their original state.
    $this->checkRoleConfig(array(
      \Drupal\user\RoleInterface::ANONYMOUS_ID => TRUE,
      \Drupal\user\RoleInterface::AUTHENTICATED_ID => TRUE,
      $rid => FALSE,
    ));

    // Check that the role is no longer configured.
    $r =
      db_query(
        'SELECT grant_view FROM {taxonomy_access_default}
         WHERE rid = :rid',
        array(':rid' => $rid)
      )
      ->fetchAll();
    $this->assertTrue(empty($r), t('All records removed for role %role.', array('%role' => $name)));

    // @todo
    //   - Add a term configuration and make sure that gets deleted too.

    // Log in as the regular_user.
    $this->drupalLogout();
    $this->drupalLogin($this->users['regular_user']);

    // Visit all nodes and verify that access is again denied.
    foreach ($this->articles as $key => $article) {
      $this->drupalGet('node/' . $article->nid);
      $this->assertResponse(403, t("Access to %name article (nid %nid) is denied.", array('%name' => $key, '%nid' => $article->nid)));
    }
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->nid);
      $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->nid)));
    }
  }
}
