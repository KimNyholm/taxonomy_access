<?php

/**
 * @file
 * Contains \Drupal\taxonomy_access\Form\TaxonomyAccessAdminRole.
 */

namespace Drupal\taxonomy_access\Form;

use Drupal\taxonomy_access\TaxonomyAccessService;

use Drupal\Core\Url;
use Drupal\taxonomy_access\Controller\DefaultController;
use Symfony\Component\DependencyInjection\ContainerInterface;


class TaxonomyAccessAdminRole extends \Drupal\Core\Form\ConfigFormBase {

  protected $taxonomyAccessService ;

  /**
   * Class constructor.
   */
  public function __construct($taxonomyAccessService) {
    $this->taxonomyAccessService = $taxonomyAccessService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('taxonomy_access.taxonomy_access_service')
    );
  }

  static public function taxonomy_accessRoleName($roleId){
    $role=\Drupal\User\Entity\Role::load($roleId);
    $roleName=empty($role) ? "Unkownn role id '$roleId'" : $role->label();
    return $roleName;
  }

  /**
   * Form submission handler for taxonomy_access_admin_role().
   *
   * Processes submissions for the vocabulary 'Add' button.
   */
  function taxonomy_access_enable_vocab_submit($form, &$form_state) {
    dpm('we got here');
    return ;
    $roleId = $form_state['values']['roleId'];
    $vid = $form_state['values']['enable_vocab'];
    $roles = _taxonomy_access_user_roles();
    $vocab = taxonomy_vocabulary_load($vid);
    if (taxonomy_access_enable_vocab($vid, $rid)) {
      drupal_set_message(t(
        'Vocabulary %vocab enabled successfully for the %role role.',
        array(
          '%vocab' => $vocab->name,
          '%role' => $roles[$rid])));
    }
    else {
      drupal_set_message(t('The vocabulary could not be enabled.'), 'error');
    }
  }

  protected function taxonomy_access_grant_add_table($row, $id) {
    $header = $this->taxonomy_access_grant_table_header();
    $table = array(
      '#type' => 'taxonomy_access_grant_table',
      '#tree' => TRUE,
      '#header' => $header,
    );
    $table[$id][TAXONOMY_ACCESS_VOCABULARY_DEFAULT] = $this->taxonomy_access_admin_build_row($row);
    return $table;
}

/**
 * Returns a header array for grant form tables.
 *
 * @return array
 *   An array of header cell data for a grant table.
 */
  protected function taxonomy_access_grant_table_header() {
    $header = array(
      array('data' => t('View')),
      array('data' => t('Update')),
      array('data' => t('Delete')),
      array('data' => t('Add Tag')),
      array('data' => t('View Tag')),
    );
    foreach ($header as &$cell) {
      $cell['class'] = array('taxonomy-access-grant');
    }
  return $header;
  }

  /**
   * Returns the proper invisible field label for each grant table element.
   */
  protected function _taxonomy_access_grant_field_label($grant, $for = NULL) {
    if ($for) {
      $label = array('@label', $for);
      $titles = array(
        'view' => t('View grant for @label', $label),
        'update' => t('Update grant for @label', $label),
        'delete' => t('Delete grant for @label', $label),
        'create' => t('Add tag grant for @label', $label),
        'list' => t('View tag grant for @label', $label),
      );
    }
    else {
      $titles = array(
        'view' => t('View grant'),
        'update' => t('Update grant'),
        'delete' => t('Delete grant'),
        'create' => t('Add tag grant'),
        'list' => t('View tag grant'),
      );
    }

   return $titles[$grant];
  }

  /**
   * Assembles a row of grant options for a term or default on the admin form.
   *
   * @param array $grants
   *   An array of grants to use as form defaults.
   * @param $label_key
   *   (optional) Key of the column to use as a label in each grant row. Defaults
   *   to NULL.
   */
  protected function taxonomy_access_admin_build_row(array $grants, $label_key = NULL, $delete = FALSE) {
    $form['#tree'] = TRUE;
    if ($delete) {
      $form['remove'] = array(
        '#type' => 'checkbox',
        '#title' => t('Delete access rule for @name', array('@name' => $grants[$label_key])),
        '#title_display' => 'invisible',
      );
    }
    if ($label_key) {
      $form[$label_key] = array(
        '#type' => 'markup',
        '#markup' => \Drupal\Component\Utility\Html::escape($grants[$label_key]),
      );
    }
    foreach (array('view', 'update', 'delete', 'create', 'list') as $grant) {
      $for = $label_key ? $grants[$label_key] : NULL;
      $form[$grant] = array(
        '#type' => 'select',
        '#title' => $this->_taxonomy_access_grant_field_label($grant, $for),
        '#title_display' => 'invisible',
        '#default_value' => is_string($grants['grant_' . $grant]) ? $grants['grant_' . $grant] : TAXONOMY_ACCESS_NODE_IGNORE,
        '#required' => TRUE,
      );
    }
    foreach (array('view', 'update', 'delete') as $grant) {
      $form[$grant]['#options'] = array(
        TAXONOMY_ACCESS_NODE_ALLOW => t('Allow'),
        TAXONOMY_ACCESS_NODE_IGNORE => t('Ignore'),
        TAXONOMY_ACCESS_NODE_DENY => t('Deny'),
      );
    }
    foreach (array('create', 'list') as $grant) {
      $form[$grant]['#options'] = array(
        TAXONOMY_ACCESS_TERM_ALLOW => t('Allow'),
        TAXONOMY_ACCESS_TERM_DENY => t('Deny'),
      );
    }
    return $form;
  }

  protected function getDefaultsForRole($roleId){
    $config = \Drupal::config('taxonomy_access.settings');
    $allDefaults=$config->get('taxonomy_access_default');
    $roleDefaults=isset($allDefaults[$roleId]) ? $allDefaults[$roleId] : array(); ;
    return $roleDefaults;
  }

  protected function getEditableConfigNames() {
    return [
      'taxonomy_access.settings',
    ];
  }

  public function getTitle($rid){
    $roleName=TaxonomyAccessAdminRole::taxonomy_accessRoleName($rid);
    return "Access rules for $roleName";
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    dpm('standard submit');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_access_admin_role';
  }

function make_array($list){
  $result=[];
  foreach($list as $key =>$item){
    $result[$key]=(array)$item;
  }
  return $result;
}

/**
 * Generates a URL to enable a role with a token for CSRF protection.
 *
 * @param int $rid
 *   The role ID.
 *
 * @return string
 *   The full URL for the request path.
 */
static function taxonomy_access_enable_role_url($rid) {
  // Create a query array with a token to validate the sumbission.
  //  $query = drupal_get_destination();
  //  $query['token'] = drupal_get_token($rid);
  $urlParameters=array('rid' => $rid);
  $url=Url::fromRoute('taxonomy_access.admin_role_enable', $urlParameters);
  return $url->toString();
}

/**
 * Form constructor for a form to manage grants by role.
 *
 * Drupal 7: taxonomy_access_admin_role($form, $form_state, $rid) {
 *
 * @param int $rid
 *   The role ID.
 *
 * @see taxonomy_access_admin_form_submit()
 * @see taxonomy_access_menu()
 * @ingroup forms
 */
public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $rid = NULL) {
  // Always include the role ID in the form.
  $form['rid'] = array('#type' => 'value', '#value' => $rid);

  // For custom roles, allow the user to enable or disable grants for the role.
  if (!in_array($rid, array(
      \Drupal\user\RoleInterface::ANONYMOUS_ID,
      \Drupal\user\RoleInterface::AUTHENTICATED_ID))){
    $roles = $this->taxonomyAccessService->_taxonomy_access_user_roles();
    // If the role is not enabled, return only a link to enable it.
    if (!$this->taxonomyAccessService->taxonomy_access_role_enabled($rid)) {
      $form['status'] = array(
        '#markup' => '<p>' . t(
          'Access control for the %name role is disabled. <a href="@url">Enable @name</a>.',
          array(
            '%name' => $roles[$rid],
            '@name' => $roles[$rid],
            '@url' => $this->taxonomy_access_enable_role_url($rid))) . '</p>'
      );
      return $form;
    }
    // Otherwise, add a link to disable and build the rest of the form.
    else {
      $query = drupal_get_destination();
      $urlParameters=array('rid' => $rid, 'query' => $query);
      $url=Url::fromRoute('taxonomy_access.admin_role_delete', $urlParameters);
      $disable_url = $url->toString();
      $form['status'] = array(
        '#markup' => '<p>' . t(
          'Access control for the %name role is enabled. <a href="@url">Disable @name</a>.',
          array(
            '%name' => $roles[$rid],
            '@name' => $roles[$rid],
            '@url' => $disable_url)) . '</p>'
      );
    }
  }

  // Retrieve role grants and display an administration form.
  // Disable list filtering while preparing this form.
  $this->taxonomyAccessService->taxonomy_access_disable_list();

// Fetch all grants for the role.
$defaults =
  db_query(
    'SELECT vid, grant_view, grant_update, grant_delete, grant_create,
            grant_list
     FROM {taxonomy_access_default}
       WHERE rid = :rid',
      array(':rid' => $rid))
    ->fetchAllAssoc('vid');
  $defaults=$this->make_array($defaults);
  $records =
  array();
/*    db_query(
      'SELECT ta.tid, td.vid, ta.grant_view, ta.grant_update, ta.grant_delete,
              ta.grant_create, ta.grant_list
       FROM {taxonomy_access_term} ta
       INNER JOIN {taxonomy_term_data} td ON ta.tid = td.tid
       WHERE rid = :rid',
      array(':rid' => $rid))
    ->fetchAllAssoc('tid', PDO::FETCH_ASSOC);
*/
  $term_grants = array();
  foreach ($records as $record) {
    $term_grants[$record['vid']][$record['tid']] = $record;
  }

  // Add a fieldset for the global default.
  $form['global_default'] = array(
    '#type' => 'fieldset',
    '#title' => t('Global default'),
    '#description' => t('The global default controls access to untagged nodes. It is also used as the default for disabled vocabularies.'),
    '#collapsible' => TRUE,
    // Collapse if there are vocabularies configured.
    '#collapsed' => (sizeof($defaults) > 1),
  );
  // Print term grant table.
  $form['global_default']['grants'] = $this->taxonomy_access_grant_add_table($defaults[TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT], TaxonomyAccessService::TAXONOMY_ACCESS_VOCABULARY_DEFAULT);

  // Fetch all vocabularies and determine which are enabled for the role.
  $vocabs = array();
  $disabled = array();
  foreach (\Drupal\taxonomy\Entity\Vocabulary::loadMultiple() as $vocab) {
    $vocabs[$vocab->id()] = $vocab;
    if (!isset($defaults[$vocab->id()])) {
      $disabled[$vocab->id()] = $vocab->label();
    }
  }

  // Add a fieldset to enable vocabularies.
  if (!empty($disabled)) {
    $form['enable_vocabs'] = array(
      '#type' => 'fieldset',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#title' => t('Add vocabulary'),
      '#attributes' => array('class' => array('container-inline', 'taxonomy-access-add')),
    );
    $form['enable_vocabs']['enable_vocab'] = array(
      '#type' => 'select',
      '#title' => t('Vocabulary'),
      '#options' => $disabled,
    );
    $form['enable_vocabs']['add'] = array(
      '#type' => 'submit',
      '#submit' => array('taxonomy_access_enable_vocab_submit'),
      '#value' => t('Add'),
    );
  }

  // Add a fieldset for each enabled vocabulary.
  foreach ($defaults as $vid => $vocab_default) {
    if (!empty($vocabs[$vid])) {
      $vocab = $vocabs[$vid];
      $name = $vocab->machine_name;

      // Fetch unconfigured terms and reorder term records by hierarchy.
      $sort = array();
      $add_options = array();
      if ($tree = taxonomy_get_tree($vid)) {
        foreach ($tree as $term) {
          if (empty($term_grants[$vid][$term->tid])) {
            $add_options["term $term->tid"] = str_repeat('-', $term->depth) . ' ' .check_plain($term->name);
          }
          else {
            $sort[$term->tid] = $term_grants[$vid][$term->tid];
            $sort[$term->tid]['name'] =  str_repeat('-', $term->depth) . ' ' . check_plain($term->name);
          }
        }
        $term_grants[$vid] = $sort;
      }

      $grants = array(TAXONOMY_ACCESS_VOCABULARY_DEFAULT => $vocab_default);
      $grants[TAXONOMY_ACCESS_VOCABULARY_DEFAULT]['name'] = t('Default');
      if (!empty($term_grants[$vid])) {
        $grants += $term_grants[$vid];
      }
      $form[$name] = array(
        '#type' => 'fieldset',
        '#title' => $vocab->name,
        '#attributes' => array('class' => array('taxonomy-access-vocab')),
        '#description' => t('The default settings apply to all terms in %vocab that do not have their own below.', array('%vocab' => $vocab->name)),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      );
      // Term grant table.
      $form[$name]['grants'] =
        taxonomy_access_grant_table($grants, $vocab->vid, t('Term'), !empty($term_grants[$vid]));
      // Fieldset to add a new term if there are any.
      if (!empty($add_options)) {
        $form[$name]['new'] = array(
          '#type' => 'fieldset',
          '#collapsible' => TRUE,
          '#collapsed' => TRUE,
          '#title' => t('Add term'),
          '#tree' => TRUE,
          '#attributes' => array('class' => array('container-inline', 'taxonomy-access-add')),
        );
        $form[$name]['new'][$vid]['item'] = array(
          '#type' => 'select',
          '#title' => t('Term'),
          '#options' => $add_options,
        );
        $form[$name]['new'][$vid]['recursive'] = array(
          '#type' => 'checkbox',
          '#title' => t('with descendants'),
        );
        $form[$name]['new'][$vid]['grants'] =
          taxonomy_access_grant_add_table($vocab_default, $vid);
        $form[$name]['new'][$vid]['add'] = array(
          '#type' => 'submit',
          '#name' => $vid,
          '#submit' => array('taxonomy_access_add_term_submit'),
          '#value' => t('Add'),
        );
      }
      $disable_url = url(
        TAXONOMY_ACCESS_CONFIG . "/role/$rid/disable/$vid",
        array('query' => drupal_get_destination())
      );
      $form[$name]['disable'] = array(
          '#markup' => '<p>' . t(
            'To disable the %vocab vocabulary, <a href="@url">delete all @vocab access rules</a>.',
            array('%vocab' => $vocab->name, '@vocab' => $vocab->name, '@url' => $disable_url)) . '</p>'
      );
    }
  }
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save all'),
    '#submit' => array('taxonomy_access_save_all_submit'),
  );
  if (!empty($term_grants)) {
    $form['actions']['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete selected'),
      '#submit' => array('taxonomy_access_delete_selected_submit'),
    );
  }

  return $form;
}

/*
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $roleId = NULL) {
    $config = $this->config('taxonomy_access.settings');
    // Always include the role ID in the form.
    $form['roleId'] = ['#type' => 'value', '#value' => $roleId];

    // For custom roles, allow the user to enable or disable grants for the role.
    if (!in_array($roleId, [
      \Drupal\user\RoleInterface::ANONYMOUS_ID,
      \Drupal\user\RoleInterface::AUTHENTICATED_ID
    ])) {
      $roles = $this->taxonomyAccessService->_taxonomy_access_user_roles();
      $role=$roles[$roleId];
      $name=$role->label();
      // If the role is not enabled, return only a link to enable it.
      if (!DefaultController::taxonomy_access_role_enabled($roleId)) {
        $form['status'] = [
          '#markup' => '<p>' . t('Access control for the %name role is disabled. <a href="@url">Enable @name</a>.', [
            '%name' => $name,
            '@name' => $name,
            '@url' => DefaultController::taxonomy_access_enable_role_url($roleId),
          ]) . '</p>'
          ];
        return $form;
      }
        // Otherwise, add a link to disable and build the rest of the form.
      else {
        $form['status'] = [
          '#markup' => '<p>' . t('Access control for the %name role is enabled. <a href="@url">Disable @name</a>.', [
            '@name' => $name,
            '%name' => $name,
            '@url' => DefaultController::taxonomy_access_delete_role_url($roleId),
          ]) . '</p>'
          ];
      }
    }
    // Retrieve role grants and display an administration form.
    // Disable list filtering while preparing this form.
    //taxonomy_access_disable_list();
    $defaults=$this->GetDefaultsForRole($roleId);
    $records = db_query('SELECT ta.tid, td.vid, ta.grant_view, ta.grant_update, ta.grant_delete,
              ta.grant_create, ta.grant_list
       FROM {taxonomy_access_term} ta
       INNER JOIN {taxonomy_term_data} td ON ta.tid = td.tid
       WHERE rid = :rid', [
      ':rid' => $rid
      ])
      ->fetchAllAssoc('tid', PDO::FETCH_ASSOC);
    $term_grants = [];
    foreach ($records as $record) {
      $term_grants[$record['vid']][$record['tid']] = $record;
    }

    // Add a fieldset for the global default.
    $form['global_default'] = [
      '#type' => 'fieldset',
      '#title' => t('Global default'),
      '#description' => t('The global default controls access to untagged nodes. It is also used as the default for disabled vocabularies.'),
      '#collapsible' => TRUE,
      // Collapse if there are vocabularies configured.
    '#collapsed' => (sizeof($defaults) > 1),
    ];
    // Print term grant table.
    $form['global_default']['grants'] = $this->taxonomy_access_grant_add_table($defaults[TAXONOMY_ACCESS_GLOBAL_DEFAULT], TAXONOMY_ACCESS_VOCABULARY_DEFAULT);

    // Fetch all vocabularies and determine which are enabled for the role.
    $vocabs = [];
    $disabled = [];
    foreach (\Drupal\taxonomy\Entity\Vocabulary::loadMultiple() as $vocab) {
      $vocabs[$vocab->id()] = $vocab;
      if (!isset($defaults[$vocab->id()])) {
        $disabled[$vocab->id()] = $vocab->label();
      }
    }
    // Add a fieldset to enable vocabularies.
    if (!empty($disabled)) {
      $form['enable_vocabs'] = [
        '#type' => 'fieldset',
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#title' => t('Add vocabulary'),
        '#attributes' => [
          'class' => [
            'container-inline',
            'taxonomy-access-add',
          ]
          ],
      ];
      $form['enable_vocabs']['enable_vocab'] = [
        '#type' => 'select',
        '#title' => t('Vocabulary'),
        '#options' => $disabled,
      ];
      $form['enable_vocabs']['add'] = [
        '#type' => 'submit',
        '#submit' => [
          '::taxonomy_access_enable_vocab_submit'
          ],
        '#value' => t('Add me'),
      ];
    }

/*
    // Add a fieldset for each enabled vocabulary.
    foreach ($defaults as $vid => $vocab_default) {
      if (!empty($vocabs[$vid])) {
        $vocab = $vocabs[$vid];
        $name = $vocab->machine_name;

        // Fetch unconfigured terms and reorder term records by hierarchy.
        $sort = [];
        $add_options = [];
        if ($tree = \Drupal::entityManager()->getStorage("taxonomy_term")->loadTree($vid)) {
          foreach ($tree as $term) {
            if (empty($term_grants[$vid][$term->tid])) {
              $add_options["term $term->tid"] = str_repeat('-', $term->depth) . ' ' . \Drupal\Component\Utility\Html::escape($term->name);
            }
            else {
              $sort[$term->tid] = $term_grants[$vid][$term->tid];
              $sort[$term->tid]['name'] = str_repeat('-', $term->depth) . ' ' . \Drupal\Component\Utility\Html::escape($term->name);
            }
          }
          $term_grants[$vid] = $sort;
        }

        $grants = [TAXONOMY_ACCESS_VOCABULARY_DEFAULT => $vocab_default];
        $grants[TAXONOMY_ACCESS_VOCABULARY_DEFAULT]['name'] = t('Default');
        if (!empty($term_grants[$vid])) {
          $grants += $term_grants[$vid];
        }
        $form[$name] = [
          '#type' => 'fieldset',
          '#title' => $vocab->name,
          '#attributes' => [
            'class' => [
              'taxonomy-access-vocab'
              ]
            ],
          '#description' => t('The default settings apply to all terms in %vocab that do not have their own below.', [
            '%vocab' => $vocab->name
            ]),
          '#collapsible' => TRUE,
          '#collapsed' => FALSE,
        ];
        // Term grant table.
        $form[$name]['grants'] = taxonomy_access_grant_table($grants, $vocab->vid, t('Term'), !empty($term_grants[$vid]));
        // Fieldset to add a new term if there are any.
        if (!empty($add_options)) {
          $form[$name]['new'] = [
            '#type' => 'fieldset',
            '#collapsible' => TRUE,
            '#collapsed' => TRUE,
            '#title' => t('Add term'),
            '#tree' => TRUE,
            '#attributes' => [
              'class' => [
                'container-inline',
                'taxonomy-access-add',
              ]
              ],
          ];
          $form[$name]['new'][$vid]['item'] = [
            '#type' => 'select',
            '#title' => t('Term'),
            '#options' => $add_options,
          ];
          $form[$name]['new'][$vid]['recursive'] = [
            '#type' => 'checkbox',
            '#title' => t('with descendants'),
          ];
          $form[$name]['new'][$vid]['grants'] = taxonomy_access_grant_add_table($vocab_default, $vid);
          $form[$name]['new'][$vid]['add'] = [
            '#type' => 'submit',
            '#name' => $vid,
            '#submit' => [
              'taxonomy_access_add_term_submit'
              ],
            '#value' => t('Add'),
          ];
        }
        // @FIXME
        // url() expects a route name or an external URI.
        // $disable_url = url(
        //         TAXONOMY_ACCESS_CONFIG . "/role/$rid/disable/$vid",
        //         array('query' => drupal_get_destination())
        //       );
        $form[$name]['disable'] = [
          '#markup' => '<p>' . t('To disable the %vocab vocabulary, <a href="@url">delete all @vocab access rules</a>.', [
            '%vocab' => $vocab->name,
            '@vocab' => $vocab->name,
            '@url' => $disable_url,
          ]) . '</p>'
          ];
      }
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save all'),
//      '#submit' => [
//        'taxonomy_access_save_all_submit'
//        ],
    ];
    if (!empty($term_grants)) {
      $form['actions']['delete'] = [
        '#type' => 'submit',
        '#value' => t('Delete selected'),
        '#submit' => [
          'taxonomy_access_delete_selected_submit'
          ],
      ];
    }
    return $form;
  }
*/

}
