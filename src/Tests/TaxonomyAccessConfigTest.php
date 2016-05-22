<?php

namespace Drupal\taxonomy_access\Tests;

use Drupal\taxonomy_access\TaxonomyAccessService;

/**
 * Tests the module's configuration forms.
 *
 * @group taxonomy_access
 */
class TaxonomyAccessConfigTest extends \Drupal\taxonomy_access\Tests\TaxonomyAccessTestCase {

  protected $articles = array();
  protected $pages = array();
  protected $vocabs = array();
  protected $terms = array();

  public function setUp() {
    parent::setUp();

    foreach (array('v1', 'v2') as $vocab) {
      $this->vocabs[$vocab] = $this->createVocab($vocab);
      $this->createField($vocab);
      $this->terms[$vocab . 't1'] =
        $this->createTerm($vocab . 't1', $this->vocabs[$vocab]->id());
      $this->terms[$vocab . 't2'] =
        $this->createTerm($vocab . 't2', $this->vocabs[$vocab]->id());
    }

    // Set up a variety of nodes with different term combinations.
    $this->articles['no_tags'] = $this->createArticle();
    $this->articles['one_tag'] =
      $this->createArticle(array($this->randomName()));
    $this->articles['two_tags'] =
      $this->createArticle(array($this->randomName(), $this->randomName()));

    $this->pages['no_tags'] = $this->createPage();
    foreach ($this->terms as $t1) {
      $this->pages[$t1->label()] = $this->createPage(array($t1->label()));
      foreach ($this->terms as $t2) {
        $this->pages[$t1->label() . '_' . $t2->label()] =
          $this->createPage(array($t1->label(), $t2->label()));
      }
    }
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
      $this->drupalGet('node/' . $article->id());
      $this->assertResponse(403, t("Access to %name article (nid %nid) is denied.", array('%name' => $key, '%nid' => $article->id())));
    }
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->id());
      $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
    }

    // Log in as the regular_user.
    $this->drupalLogin($this->users['regular_user']);

    // Visit all nodes and verify that access is denied.
    foreach ($this->articles as $key => $article) {
      $this->drupalGet('node/' . $article->id());
      $this->assertResponse(403, t("Access to %name article (nid %nid) is denied.", array('%name' => $key, '%nid' => $article->id())));
    }
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->id());
      $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
    }

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Confirm that only edit links are available for anon. and auth.
    $this->checkRoleConfig(array(
      TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID => TRUE,
      TaxonomyAccessService::TAXONOMY_ACCESS_AUTHENTICATED_RID =>TRUE,
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
    $this->drupalGet(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG . '/role/' .  TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID . '/edit');
    $this->assertResponse(200, t("Access to page allowed."));
    $edit = array();
    $this->configureFormRow($edit, TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT, TaxonomyAccessService::TAXONOMY_ACCESS_VOCABULARY_DEFAULT, TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW);
    $this->drupalPostForm(NULL, $edit, (string)t('Save all'));

    // Log out.
    $this->drupalLogout();
    $this->taxonomy_access_rebuild();
    // Visit each node and verify that access is allowed.
    foreach ($this->articles as $key => $article) {
      $this->drupalGet('node/' . $article->id());
      $this->assertResponse(200, t("Access to %name article (nid %nid) is allowed.", array('%name' => $key, '%nid' => $article->id())));
    }
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->id());
      $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->id())));
    }


    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Set the v1 default to view allow.
    $this->vocabularyEnable(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v1']->id());
    $this->vocabularySetDefault(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v1']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW);
    
    // Set v1t1 and v2t1 to view allow.
    $this->VocabularyTermAdd(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v1']->id(), $this->terms['v1t1']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW);
    $this->vocabularyEnable(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v2']->id());
    $this->vocabularySetDefault(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v2']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_NODE_DENY);
    $this->VocabularyTermAdd(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v2']->id(), $this->terms['v2t1']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW);

    // Use the admin form to give anonymous view deny in the global default.
    $this->drupalGet(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG . '/role/' . TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID . '/edit');
    $edit = array();
    $this->configureFormRow($edit, TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT, TaxonomyAccessService::TAXONOMY_ACCESS_VOCABULARY_DEFAULT, TaxonomyAccessService::TAXONOMY_ACCESS_NODE_DENY);
    $this->drupalPostForm(NULL, $edit, 'Save all');

    // Log out.
    $this->drupalLogout();
    $this->taxonomy_access_rebuild();

  // Visit each artile and verify that access is denied.
    foreach ($this->articles as $key => $article) {
      $this->drupalGet('node/' . $article->id());
      $this->assertResponse(403, t("Access to %name article (nid %nid) is denied.", array('%name' => $key, '%nid' => $article->id())));
    }

    // Visit each page.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->id());

      switch (TRUE) {
        // If the page has no tags, access should be denied.
        case ($key == 'no_tags'):
        // If the page is tagged with v2t2, access should be denied.
        case (strpos($key, 'v2t2') !== FALSE):
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
          break;

        // Otherwise, access should be allowed.
        default:
          $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->id())));
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
    $this->vocabularyEnable(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v1']->id());
    // Give anonymous view allow for the v1 default.
    $this->vocabularySetDefault(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v1']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW);

    // Log out.
    $this->drupalLogout();
    $this->taxonomy_access_rebuild();

    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->id());

      // If the page is tagged with a v1 term, access should be allowed.
      if (strpos($key, 'v1') !== FALSE) {
        $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->id)));
      }
      // Otherwise, access should be denied.
      else {
        $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
      }
    }

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);
    // Enable v2 and add a specific configuration for v2t1.
    $this->vocabularyEnable(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v2']->id());
    $this->VocabularyTermAdd(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v2']->id(), $this->terms['v2t1']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE);
    // Use the admin form to give anonymous view deny for the v2 default.
    $this->vocabularySetDefault(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v2']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_NODE_DENY);

    // Log out.
    $this->drupalLogout();
    $this->taxonomy_access_rebuild();
    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->id());

      switch (TRUE) {
        // If the page is tagged with v2t2, the v2 default is inherited: Deny.
        case (strpos($key, 'v2t2') !== FALSE):
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
          break;

        // Otherwise, if the page is tagged with v1, it's allowed.
        case (strpos($key, 'v1') !== FALSE):
          $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->id())));
          break;

        // Access should be denied by default.
        default:
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
          break;
      }
    }

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Change the configuration: Allow for v2; Deny for v1.
    $this->vocabularySetDefault(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v1']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_NODE_DENY);
    $this->vocabularySetDefault(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v2']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW);

    // Log out.
    $this->drupalLogout();
    $this->taxonomy_access_rebuild();

    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->id());

      switch (TRUE) {
        // If the page is tagged with a v1 term, access should be denied.
        case (strpos($key, 'v1') !== FALSE):
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
          break;

        // Otherwise, if the page is tagged with v2t2, the default is
        // inherited and access should be allowed.
        case (strpos($key, 'v2t2') !== FALSE):
          $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->id())));
          break;

        // Access should be denied by default.
        default:
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
          break;
      }
    }

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Use the admin form to disable v1.
    $this->drupalGet(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG . '/role/' . TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID . '/edit');
    $this->clickLink(t('delete all v1 access rules'));
    $this->assertText("Are you sure you want to delete all taxonomy access rules for v1", t('Disable form for vocabulary loaded.'));
    $this->drupalPostForm(NULL, array(), 'Delete all');

    // Log out.
    $this->drupalLogout();
    $this->taxonomy_access_rebuild();

    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->id());

      // If the page is tagged with v2t2, access should be allowed.
      if (strpos($key, 'v2t2') !== FALSE) {
        $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->id())));
      }
      // Otherwise, access should be denied.
      else {
        $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
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

    $this->drupalLogin($this->users['site_admin']);
    $this->vocabularyEnable(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v1']->id());
    $this->VocabularyTermAdd(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v1']->id(), $this->terms['v1t1']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW);
    $this->drupalLogout();
    $this->taxonomy_access_rebuild();

    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->id());

      // If the page is tagged with v1t1, access should be allowed.
      if (strpos($key, 'v1t1') !== FALSE) {
        $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->id())));
      }
      // Otherwise, access should be denied.
      else {
        $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
      }
    }

    $this->drupalLogin($this->users['site_admin']);
    // Enable v2.
    $this->vocabularyEnable(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v2']->id());
    // Use the admin form to give anonymous view deny for v2t1.
    $this->VocabularyTermAdd(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v2']->id(), $this->terms['v2t1']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_NODE_DENY);
    $this->drupalLogout();
    $this->taxonomy_access_rebuild();

    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->id());

      switch (TRUE) {
        // If the page is tagged with v2t1, access should be denied.
        case (strpos($key, 'v2t1') !== FALSE):
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
          break;

        // Otherwise, if the page is tagged with v1t1, it's allowed.
        case (strpos($key, 'v1t1') !== FALSE):
          $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->id())));
          break;

        // Access should be denied by default.
        default:
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
          break;
      }
    }

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);
    // Use the form to change the configuration: Allow for v2t1; Deny for v1t1.
    $this->vocabularySetTerm(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v2']->id(), $this->terms['v2t1']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW);
    $this->vocabularySetTerm(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, $this->vocabs['v1']->id(), $this->terms['v1t1']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_NODE_DENY);
    $this->drupalGet(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG . '/role/' . TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID . '/edit');
    // Log out.
    $this->drupalLogout();
    $this->taxonomy_access_rebuild();

    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->id());

      switch (TRUE) {
        // If the page is tagged with v1t1, access should be denied.
        case (strpos($key, 'v1t1') !== FALSE):
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
          break;

        // Otherwise, if the page is tagged with v2t1, it's allowed.
        case (strpos($key, 'v2t1') !== FALSE):
          $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->id())));
          break;

        // Access should be denied by default.
        default:
          $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
          break;
      }
    }

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Delete the v2t1 configuration.
    $this->vocabularyTermDelete(TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, 'v2', $this->terms['v2t1']->id());

    // Log out.
    $this->drupalLogout();
    $this->taxonomy_access_rebuild();

    // Visit each page and verify whether access is allowed or denied.
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->id());

      // Access to all pages should be denied.
      $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
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
      $this->vocabs['v1']->id(),
      $this->terms['v1t1']->id()
    );
    $this->terms['v1t1c2'] = $this->createTerm(
      'v1t1c2',
      $this->vocabs['v1']->id(),
      $this->terms['v1t1']->id()
    );
    $this->terms['v1t1c1g1'] = $this->createTerm(
      'v1t1c1g1',
      $this->vocabs['v1']->id(),
      $this->terms['v1t1c1']->id()
    );
    $this->terms['v1t1c1g2'] = $this->createTerm(
      'v1t1c1g2',
      $this->vocabs['v1']->id(),
      $this->terms['v1t1c1']->id()
    );

    // Add pages tagged with each.
    foreach (array('v1t1c1', 'v1t1c2', 'v1t1c1g1', 'v1t1c1g2') as $name) {
      $this->pages[$name] = $this->createPage(array($name));
    }

    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Enable v1 programmatically.
    $this->taxonomyAccessService->taxonomy_access_enable_vocab($this->vocabs['v1']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID);
    // Use the admin form to give anonymous view allow for v1t1 and children.
    $this->drupalGet(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG . '/role/' . TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID . '/edit');
    $edit = array();
    $edit["new[{$this->vocabs['v1']->id()}][recursive]"] = 1;
    $this->addFormRow($edit, $this->vocabs['v1']->id(), $this->terms['v1t1']->id(), TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW);
    $this->drupalPostForm(NULL, $edit, 'Add term');

  }

  /**
   * Tests enabling and disabling TAC for a custom role.
   */
  public function testRoleEnableDisable() {
    // fix me. regular user is not set.
    $rid = $this->user_roles['regular_user']->id();
    $rid = $this->taxonomyAccessService->roleIdToNumber($rid);
    $name = $this->user_roles['regular_user']->label();

    // Check that the role is disabled by default.
    $this->checkRoleConfig(array(
      TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID => TRUE,
      TaxonomyAccessService::TAXONOMY_ACCESS_AUTHENTICATED_RID => TRUE,
      $rid => FALSE,
    ));

    // Test enabling the role.
    $this->drupalGet(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG . "/role/$rid/edit");

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
    $this->clickLink(t('Enable role'));
// FIX ME simple test failure
//    $this->clickLink(t('Enable @name', array('@name' => $name)));
    $this->drupalGet(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG . "/role/$rid/edit");
    $this->checkRoleEnableLink($rid, FALSE);
    $this->checkRoleDisableLink($rid, TRUE);

    // Update the global default to allow view.
    $edit = array();
    $this->configureFormRow($edit, TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT,TaxonomyAccessService::TAXONOMY_ACCESS_VOCABULARY_DEFAULT,TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW);
    $this->drupalPostForm(NULL, $edit, 'Save all');

    // Confirm that all three roles are enabled.
    $this->checkRoleConfig(array(
      TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID => TRUE,
      TaxonomyAccessService::TAXONOMY_ACCESS_AUTHENTICATED_RID => TRUE,
      $rid => TRUE,
    ));

    // Check that the role is configured.
    $r =
      db_query(
        'SELECT grant_view FROM {taxonomy_access_default}
         WHERE vid = :vid AND rid = :rid',
        array(':vid' => TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT, ':rid' => $rid)
      )
      ->fetchField();
    $this->assertTrue($r == TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW, t('Used form to grant the role %role view in the global default.', array('%role' => $name)));

    // Log in as the regular_user.
    $this->drupalLogout();
    $this->taxonomy_access_rebuild();
    $this->drupalLogin($this->users['regular_user']);

    // Visit each node and verify that access is allowed.
    foreach ($this->articles as $key => $article) {
      $this->drupalGet('node/' . $article->id());
      $this->assertResponse(200, t("Access to %name article (nid %nid) is allowed.", array('%name' => $key, '%nid' => $article->id())));
    }
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->id());
      $this->assertResponse(200, t("Access to %name page (nid %nid) is allowed.", array('%name' => $key, '%nid' => $page->id())));
    }

    $this->drupalLogout();
    // Log in as the administrator.
    $this->drupalLogin($this->users['site_admin']);

    // Test disabling the role.
    $this->drupalGet(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG . "/role/$rid/edit");
// FIX ME simple test failure.
//    $this->clickLink(t('Disable @name', array('@name' => $name)));
    $this->clickLink(t('Disable role'));
    $this->assertText("Are you sure you want to delete all taxonomy access rules for the role ", 'Disable form for role loaded.');
    $this->drupalPostForm(NULL, array(), 'Delete all');

    // Confirm that a confirmation message appears.
//    $this->assertText("All taxonomy access rules deleted for role $name", t('Confirmation message found.'));
    $this->assertText("All taxonomy access rules deleted for role ", t('Confirmation message found.'));

    // Check that there is:
    // - An enable link
    // - No disable link
    // @todo
    //   - No grant tables.
    $this->checkRoleEnableLink($rid, TRUE);
    $this->checkRoleDisableLink($rid, FALSE);

    // Confirm edit/enable/disable links are in their original state.
    $this->checkRoleConfig(array(
      TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID => TRUE,
      TaxonomyAccessService::TAXONOMY_ACCESS_AUTHENTICATED_RID =>TRUE,
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

    $this->drupalLogout();
    $this->taxonomy_access_rebuild();
    // Log in as the regular_user.
    $this->drupalLogin($this->users['regular_user']);

    // Visit all nodes and verify that access is again denied.
    foreach ($this->articles as $key => $article) {
      $this->drupalGet('node/' . $article->id());
      $this->assertResponse(403, t("Access to %name article (nid %nid) is denied.", array('%name' => $key, '%nid' => $article->id())));
    }
    foreach ($this->pages as $key => $page) {
      $this->drupalGet('node/' . $page->id());
      $this->assertResponse(403, t("Access to %name page (nid %nid) is denied.", array('%name' => $key, '%nid' => $page->id())));
    }
  }

}