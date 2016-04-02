<?php
namespace Drupal\taxonomy_access;

/**
 * Provides a base test class and helper methods for automated tests.
 */
class TaxonomyAccessTestCase extends \Drupal\simpletest\WebTestBase {

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
      'administer permissions',
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

  public function setUp() {
    // Enable module and dependencies.
    parent::setUp('taxonomy_access');

    // Rebuild node access on installation.
    node_access_rebuild();

    // Configure users with base permission patterns.
    foreach ($this->user_config as $user => $permissions) {
      $this->users[$user] = $this->drupalCreateUser($permissions);

      // Save the role ID separately so it's easy to retrieve.
      foreach ($this->users[$user]->roles as $rid => $role) {
        if ($rid != \Drupal\user\RoleInterface::AUTHENTICATED_ID) {
          $this->user_roles[$user] = user_role_load($rid);
        }
      }
    }

    // Give the anonymous and authenticated roles ignore grants.
    $rows = [];
    foreach ([\Drupal\user\RoleInterface::ANONYMOUS_ID, \Drupal\user\RoleInterface::AUTHENTICATED_ID] as $rid) {
      $ignore = [
        'view' => TAXONOMY_ACCESS_NODE_IGNORE,
        'update' => TAXONOMY_ACCESS_NODE_IGNORE,
        'delete' => TAXONOMY_ACCESS_NODE_IGNORE,
      ];
      $rows[] = _taxonomy_access_format_grant_record(TAXONOMY_ACCESS_GLOBAL_DEFAULT, $rid, $ignore, TRUE);
    }
    taxonomy_access_set_default_grants($rows);

    foreach ([\Drupal\user\RoleInterface::ANONYMOUS_ID, \Drupal\user\RoleInterface::AUTHENTICATED_ID] as $rid) {
      $r = db_query('SELECT grant_view FROM {taxonomy_access_default}
           WHERE vid = :vid AND rid = :rid', [
        ':vid' => TAXONOMY_ACCESS_GLOBAL_DEFAULT,
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
    $vocabulary = new stdClass();
    $vocabulary->name = $machine_name;
    $vocabulary->description = $this->randomName();
    $vocabulary->machine_name = $machine_name;
    $vocabulary->help = '';
    $vocabulary->weight = mt_rand(0, 10);
    taxonomy_vocabulary_save($vocabulary);
    return $vocabulary;
  }

  public /**
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
  function createTerm($machine_name, $vocab, $parent = NULL) {
    $term = new stdClass();
    $term->name = $machine_name;
    $term->description = $machine_name;
    // Use the first available text format.
    $term->format = db_query_range('SELECT format FROM {filter_format}', 0, 1)->fetchField();
    $term->vid = $vocab->vid;
    $term->vocabulary_machine_name = $vocab->machine_name;
    if (!is_null($parent)) {
      $term->parent = $parent;
    }
    taxonomy_term_save($term);
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
  function createField($machine_name, $widget = 'options_select', $count = FIELD_CARDINALITY_UNLIMITED) {
    $field = [
      'field_name' => $machine_name,
      'type' => 'taxonomy_term_reference',
      'cardinality' => $count,
      'settings' => [
        'allowed_values' => [
          [
            'vocabulary' => $machine_name,
            'parent' => 0,
          ]
          ]
        ],
    ];
    $field = field_create_field($field);

    $instance = [
      'field_name' => $machine_name,
      'bundle' => 'page',
      'entity_type' => 'node',
      'widget' => [
        'type' => $widget
        ],
      'display' => [
        'default' => ['type' => 'taxonomy_term_reference_link']
        ],
    ];

    return field_create_instance($instance);
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
    $this->drupalPost('admin/reports/status/rebuild', [], t('Rebuild permissions'));
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
    $roles = _taxonomy_access_user_roles();

    // Log in as the administrator.
    $this->drupalLogout();
    $this->drupalLogin($this->users['site_admin']);
    $this->drupalGet(TAXONOMY_ACCESS_CONFIG);

    foreach ($statuses as $rid => $status) {
      // Assert that a "Configure" link is available for the role.
      $this->assertLinkByHref(TAXONOMY_ACCESS_CONFIG . "/role/$rid/edit", 0, t('"Configure" link is available for role %rid.', [
        '%rid' => $rid
        ]));
    }

    // Retrieve the grant status table.
    $shown = [];
    $table = $this->xpath('//table/tbody');
    $table = reset($table);
    // SimpleXML has fake arrays so we have to do this to get the data out.
    foreach ($table->tr as $row) {
      $tds = [];
      foreach ($row->td as $value) {
        $tds[] = (string) $value;
      }
      $shown[$tds[0]] = $tds[1];
    }

    foreach ($statuses as $rid => $status) {
      // Assert that the form shows the passed status.
      if ($status) {
        $this->assertTrue($shown[$roles[$rid]] == t('Enabled'), format_string('Role %role is enabled.', [
          '%role' => $rid
          ]));
      }
      else {
        $this->assertTrue($shown[$roles[$rid]] == t('Disabled'), format_string('Role %role is disabled.', [
          '%role' => $rid
          ]));
      }

      // Assert that a "Configure" link is available for the role.
      $this->assertLinkByHref(TAXONOMY_ACCESS_CONFIG . "/role/$rid/edit", 0, t('"Configure" link is available for role %rid.', [
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
      $this->assertLinkByHref(TAXONOMY_ACCESS_CONFIG . "/role/$rid/enable", 0, t('Enable link is available for role %rid.', [
        '%rid' => $rid
        ]));
    }
    else {
      $this->assertNoLinkByHref(TAXONOMY_ACCESS_CONFIG . "/role/$rid/enable", t('Enable link is not available for role %rid.', [
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
      $this->assertLinkByHref(TAXONOMY_ACCESS_CONFIG . "/role/$rid/delete", 0, t('Disable link is available for role %rid.', [
        '%rid' => $rid
        ]));
    }
    else {
      $this->assertNoLinkByHref(TAXONOMY_ACCESS_CONFIG . "/role/$rid/delete", t('Disable link is not available for role %rid.', [
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
  function addFormRow(&$edit, $vid = TAXONOMY_ACCESS_GLOBAL_DEFAULT, $tid = TAXONOMY_ACCESS_VOCABULARY_DEFAULT, $view = TAXONOMY_ACCESS_NODE_IGNORE, $update = TAXONOMY_ACCESS_NODE_IGNORE, $delete = TAXONOMY_ACCESS_NODE_IGNORE, $create = TAXONOMY_ACCESS_TERM_DENY, $list = TAXONOMY_ACCESS_TERM_DENY) {
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
  function configureFormRow(&$edit, $vid = TAXONOMY_ACCESS_GLOBAL_DEFAULT, $tid = TAXONOMY_ACCESS_VOCABULARY_DEFAULT, $view = TAXONOMY_ACCESS_NODE_IGNORE, $update = TAXONOMY_ACCESS_NODE_IGNORE, $delete = TAXONOMY_ACCESS_NODE_IGNORE, $create = TAXONOMY_ACCESS_TERM_DENY, $list = TAXONOMY_ACCESS_TERM_DENY) {
    $edit["grants[$vid][$tid][view]"] = $view;
    $edit["grants[$vid][$tid][update]"] = $update;
    $edit["grants[$vid][$tid][delete]"] = $delete;
    $edit["grants[$vid][$tid][create]"] = $create;
    $edit["grants[$vid][$tid][list]"] = $list;
  }

}
