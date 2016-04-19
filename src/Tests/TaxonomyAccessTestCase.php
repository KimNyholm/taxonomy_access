<?php

namespace Drupal\taxonomy_access\Tests;

use Drupal\taxonomy_access\TaxonomyAccessService;

/**
 * Provides a base test class and helper methods for automated tests.
 */
class TaxonomyAccessTestCase extends \Drupal\simpletest\WebTestBase {

  protected $strictConfigSchema = FALSE ;

  public static $modules = array('taxonomy_access');

  protected $taxonomyAccessService ;

  protected $profile = 'standard';

  // There are four types of users:
  // site admins, taxonomy admins, content editors, and regular users.

  protected $users = [];

  protected $user_roles = [];

  protected $user_config = [
    'site_admin' => [
      'access content',
      'access site reports',
      'access administration pages',
      'administer site configuration',
      'create article content',
      'edit any article content',
      'create page content',
      'edit any page content',
    ],
    'tax_admin' => [
      'access content',
      'administer taxonomy',
    ],
    'editor' => [
      'access content',
      'create article content',
      'create page content',
    ],
    'regular_user' => ['access content'],
  ];

  protected function randomName(){
    return $this->randomMachineName();
  }

  public function setUp() {
    // Enable module and dependencies.
    parent::setUp();
    $this->taxonomyAccessService = \Drupal::service('taxonomy_access.taxonomy_access_service');

    // Rebuild node access on installation.
    node_access_rebuild();

    // Configure users with base permission patterns.
    $allRoles=$this->taxonomyAccessService->_taxonomy_access_user_roles();
    foreach ($this->user_config as $userType => $permissions) {
      $user=$this->drupalCreateUser($permissions);
      $this->users[$userType]=$user;

      // Save the role ID separately so it's easy to retrieve.
      $userName=$user->label();
      foreach ($allRoles as $roleNumber => $role) {
        $rid=$this->taxonomyAccessService->roleNumberToRid($roleNumber);
 //       $this->pass('Checking ' . $rid . ' for user ' . $userName . ' of type ' . $userType);
        if ($user->hasRole($rid) && ($rid != \Drupal\user\RoleInterface::AUTHENTICATED_ID)) {
        //if ($this->users[$user]->hasRole($role->id())){
          $this->user_roles[$userType] = $role;
        }
      }
    }

    // Give the anonymous and authenticated roles ignore grants.
    $rows = [];
    foreach ([TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, TaxonomyAccessService::TAXONOMY_ACCESS_AUTHENTICATED_RID] as $rid) {
      $ignore = [
        'view' =>TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE,
        'update' =>TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE,
        'delete' =>TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE,
      ];
      $rows[] = $this->taxonomyAccessService->_taxonomy_access_format_grant_record(TAXONOMY_ACCESS_GLOBAL_DEFAULT, $rid, $ignore, TRUE);
    }
    $this->taxonomyAccessService->taxonomy_access_set_default_grants($rows);

    foreach ([TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID, TaxonomyAccessService::TAXONOMY_ACCESS_AUTHENTICATED_RID] as $rid) {
      $r = db_query('SELECT grant_view FROM {taxonomy_access_default}
           WHERE vid = :vid AND rid = :rid', [
        ':vid' =>TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT,
        ':rid' => $rid,
      ])
        ->fetchField();
      $this->assertTrue(is_numeric($r) && $r == 0, t("Set global default for role %rid to <em>Ignore</em>", [
        '%rid' => $rid
        ]));
    }
  }

  public /**
   * Creates a vocabulary with a certain name.
   *
   * @param string $machine_name
   *   A machine-safe name.
   *
   * @return object
   *   The vocabulary object.
   */
  function createVocab($machine_name) {
    $vocabulary = \Drupal\taxonomy\Entity\Vocabulary::create(
      array(
        'vid' => $machine_name,
        'machine_name' => $machine_name,
        'name' => $machine_name,
      ))->save();
    $vocabulary=\Drupal\taxonomy\Entity\Vocabulary::load($machine_name);
    return $vocabulary;
  }

  /**
   * Creates a new term in the specified vocabulary.
   *
   * @param string $machine_name
   *   A machine-safe name.
   * @param object $vocab
   *   A vocabulary object.
   * @param int|null $parent
   *   (optional) The tid of the parent term, if any.  Defaults to NULL.
   *
   * @return object
   *   The taxonomy term object.
   */
  function createTerm($taxonomyName, $vocab, $parent = 0) {
    $vid=$vocab->id();
    $term = \Drupal\taxonomy\Entity\Term::create(
        [ 'name' => $taxonomyName, 'vid' => $vid, 'parent'=>array($parent)]);
    $term-> save();
    $tid=$term->id();
    $term = \Drupal\taxonomy\Entity\Term::load($tid);
    return $term;
  }

  public /**
   * Creates a taxonomy field and adds it to the page content type.
   *
   * @param string $machine_name
   *   The machine name of the vocabulary to use.
   * @param string $widget
   *   (optional) The name of the widget to use.  Defaults to 'options_select'.
   * @param int $count
   *   (optional) The allowed number of values.  Defaults to unlimited.
   *
   * @return array
   *   Array of instance data.
   */
  function createField($vocabulary_name){
  $fieldStorage = [
       'field_name' => $vocabulary_name,
       'type' => 'entity_reference',
       'entity_type' => 'node',
       'cardinality' => \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
       'settings' => [
         'target_type' => 'taxonomy_term',
       ],
     ];
     \Drupal\field\Entity\FieldStorageConfig::create($fieldStorage)->save();
    $field=array(
       'entity_type' => 'node',
       'field_name' => $vocabulary_name,
       'bundle' => 'page',
       'settings' => array(
          'handler' => 'default:taxonomy_term',
          'handler_settings' => array(
            'target_bundles' => array($vocabulary_name => $vocabulary_name),
          ),
        )
      );

    \Drupal\field\Entity\FieldConfig::create($field)->save();
    $field['bundle']='article';
    \Drupal\field\Entity\FieldConfig::create($field)->save();
  }

  public /**
   * Creates an article with the specified terms.
   *
   * @param array $autocreate
   *   (optional) An array of term names to autocreate. Defaults to array().
   * @param array $existing
   *   (optional) An array of existing term IDs to add.
   *
   * @return object
   *   The node object.
   */
  function createArticle($autocreate = [], $existing = []) {
    $values = [];
    foreach ($autocreate as $name) {
      $values[] = [
        'tid' => 'autocreate',
        'vid' => 1,
        'name' => $name,
        'vocabulary_machine_name' => 'tags',
      ];
    }
    foreach ($existing as $tid) {
      $values[] = [
        'tid' => $tid,
        'vid' => 1,
        'vocabulary_machine_name' => 'tags',
      ];
    }

    // Bloody $langcodes.
    $values = [\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED => $values];

    $settings = [
      'type' => 'article',
      'field_tags' => $values,
    ];

    return $this->drupalCreateNode($settings);
  }

  public /**
   * Submits the node access rebuild form.
   */
  function rebuild() {
    $this->drupalPostForm('admin/reports/status/rebuild', [], t('Rebuild permissions'));
    $this->assertText(t('The content access permissions have been rebuilt.'));
  }

  public /**
   * Asserts that a status column and "Configure" link is found for the role.
   *
   * @param array $statuses
   *   An associative array of role statuses, keyed by role ID. Each item
   *   should be TRUE if the role is enabled, and FALSE otherwise.
   */
  function checkRoleConfig(array $statuses) {
    $roles = $this->taxonomyAccessService->_taxonomy_access_user_roles();

    // Log in as the administrator.
    $this->drupalLogout();
    $this->drupalLogin($this->users['site_admin']);
    $this->drupalGet(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG);

    foreach ($statuses as $rid => $status) {
      // Assert that a "Configure" link is available for the role.
      $this->assertLinkByHref(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG . "/role/$rid/edit", 0, t('"Configure" link is available for role %rid.', [
        '%rid' => $rid
        ]));
    }

    // Retrieve the grant status table.
    $shown = [];
    $table = $this->xpath('//table/tbody');
    $table = reset($table);
    // SimpleXML has fake arrays so we have to do this to get the data out.
    if (isset($table->tr)){
      foreach ($table->tr as $row) {
        $tds = [];
        foreach ($row->td as $value) {
          $tds[] = (string) $value;
        }
        $shown[$tds[0]] = $tds[1];
      }
    }
//$this->pass(var_export($shown, true));
    foreach ($statuses as $rid => $status) {
      //$rid = $this->taxonomyAccessService->roleIdToNumber($roles[$rid]->id());
      $roleName = $roles[$rid]->label();
//$this->pass($roleName, true);
      if (!isset($shown[$roleName])){
        $shown[$roleName] = '';
      }
      // Assert that the form shows the passed status.
      if ($status) {
        $this->assertTrue($shown[$roleName] == t('Enabled'), format_string('Role %role is enabled.', [
          '%role' => $roleName
          ]));
      }
      else {
        $this->assertTrue($shown[$roleName] == t('Disabled'), format_string('Role %role is disabled.', [
          '%role' => $roleName
          ]));
      }

      // Assert that a "Configure" link is available for the role.
      $this->assertLinkByHref(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG . "/role/$rid/edit", 0, t('"Configure" link is available for role %rid.', [
        '%rid' => $rid
        ]));
    }

  }

  public /**
   * Asserts that an enable link is or is not found for the role.
   *
   * @param int $rid
   *   The role ID to check.
   * @param bool $found
   *   Whether the link should be found, or not.
   */
  function checkRoleEnableLink($rid, $found) {
    if ($found) {
      $this->assertLinkByHref(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG . "/role/$rid/enable", 0, t('Enable link is available for role %rid.', [
        '%rid' => $rid
        ]));
    }
    else {
      $this->assertNoLinkByHref(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG . "/role/$rid/enable", t('Enable link is not available for role %rid.', [
        '%rid' => $rid
        ]));
    }
  }

  public /**
   * Asserts that a disable link is or is not found for the role.
   *
   * @param int $rid
   *   The role ID to check.
   * @param bool $found
   *   Whether the link should be found, or not.
   */
  function checkRoleDisableLink($rid, $found) {
    if ($found) {
      $this->assertLinkByHref(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG . "/role/$rid/delete", 0, t('Disable link is available for role %rid.', [
        '%rid' => $rid
        ]));
    }
    else {
      $this->assertNoLinkByHref(TaxonomyAccessService::TAXONOMY_ACCESS_CONFIG . "/role/$rid/delete", t('Disable link is not available for role %rid.', [
        '%rid' => $rid
        ]));
    }
  }

  public /**
   * Adds a term row on the role configuration form.
   *
   * @param array &$edit
   *   The form data to post.
   * @param int $vid
   *   (optional) The vocabulary ID. Defaults to
   *   TAXONOMY_ACCESS_GLOBAL_DEFAULT.
   * @param $int tid
   *   (optional) The term ID. Defaults to TAXONOMY_ACCESS_VOCABULARY_DEFAULT.
   * @param int $view
   *   (optional) The view grant value. Defaults to
   *    TAXONOMY_ACCESS_NODE_IGNORE.
   * @param int $update
   *   (optional) The update grant value. Defaults to
   * @param int $delete
   *   (optional) The delete grant value. Defaults to
   *   TAXONOMY_ACCESS_NODE_IGNORE.
   * @param int $create
   *   (optional) The create grant value. Defaults to
   *   TAXONOMY_ACCESS_TERM_DENY.
   * @param int $list
   *   (optional) The list grant value. Defaults to TAXONOMY_ACCESS_TERM_DENY.
   */
  function addFormRow(&$edit, $vid =TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT, $tid =TaxonomyAccessService::TAXONOMY_ACCESS_VOCABULARY_DEFAULT, $view = TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE, $update = TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE, $delete = TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE, $create = TaxonomyAccessService::TAXONOMY_ACCESS_TERM_DENY, $list = TaxonomyAccessService::TAXONOMY_ACCESS_TERM_DENY) {
    $new_value = $tid ? "term $tid" : "default $vid";
    $edit["new[$vid][item]"] = $new_value;
    $edit["new[$vid][grants][$vid][0][view]"] = $view;
    $edit["new[$vid][grants][$vid][0][update]"] = $update;
    $edit["new[$vid][grants][$vid][0][delete]"] = $delete;
    $edit["new[$vid][grants][$vid][0][create]"] = $create;
    $edit["new[$vid][grants][$vid][0][list]"] = $list;
  }

  public /**
   * Configures a row on the TAC configuration form.
   *
   * @param array &$edit
   *   The form data to post.
   * @param int $vid
   *   (optional) The vocabulary ID. Defaults to
   *   TAXONOMY_ACCESS_GLOBAL_DEFAULT.
   * @param $int tid
   *   (optional) The term ID. Defaults to TAXONOMY_ACCESS_VOCABULARY_DEFAULT.
   * @param int $view
   *   (optional) The view grant value. Defaults to
   *    TAXONOMY_ACCESS_NODE_IGNORE.
   * @param int $update
   *   (optional) The update grant value. Defaults to
   * @param int $delete
   *   (optional) The delete grant value. Defaults to
   *   TAXONOMY_ACCESS_NODE_IGNORE.
   * @param int $create
   *   (optional) The create grant value. Defaults to
   *   TAXONOMY_ACCESS_TERM_DENY.
   * @param int $list
   *   (optional) The list grant value. Defaults to TAXONOMY_ACCESS_TERM_DENY.
   */
  function configureFormRow(&$edit, $vid =TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT, $tid =TaxonomyAccessService::TAXONOMY_ACCESS_VOCABULARY_DEFAULT, $view = TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE, $update = TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE, $delete = TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE, $create = TaxonomyAccessService::TAXONOMY_ACCESS_TERM_DENY, $list = TaxonomyAccessService::TAXONOMY_ACCESS_TERM_DENY) {
    $edit["grants[$vid][$tid][view]"] = $view;
    $edit["grants[$vid][$tid][update]"] = $update;
    $edit["grants[$vid][$tid][delete]"] = $delete;
    $edit["grants[$vid][$tid][create]"] = $create;
    $edit["grants[$vid][$tid][list]"] = $list;
  }

}
