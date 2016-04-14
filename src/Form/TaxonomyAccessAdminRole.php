<?php

/**
 * @file
 * Contains \Drupal\taxonomy_access\Form\TaxonomyAccessAdminRole.
 */

namespace Drupal\taxonomy_access\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\taxonomy_access\TaxonomyAccessService;


class TaxonomyAccessAdminRole extends \Drupal\Core\Form\FormBase {

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

  public function getTitle($rid){
    $roleName=$this->taxonomyAccessService->roleNumberToName($rid);
    return "Access rules for $roleName";
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
function getDefault(){
  return
    array(
      'tac_gd___' => Array
        (
            'vid' => 'tac_gd___',
            'grant_view' => 0,
            'grant_update' => 0,
            'grant_delete' => 0,
            'grant_create' => 0,
            'grant_list' => 0,
        ),
      'tags' => Array
        (
            'vid' => 'tags',
            'grant_view' => 0,
            'grant_update' => 0,
            'grant_delete' => 0,
            'grant_create' => 0,
            'grant_list' => 0,
        ),
    );
}
function getGrants(){
  return array(
    0 => array
        (
            'vid' => 'tags',
            'grant_view' => 0,
            'grant_update' => 0,
            'grant_delete' => 0,
            'grant_create' => 0,
            'grant_list' => 0,
            'name' => 'Default',
        )

);
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
    $url=\Drupal\Core\Url::fromRoute('taxonomy_access.admin_role_enable', $urlParameters);
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

      $vid='tags';
      $vocabId='tags';
      $vocabLabel = 'Tags';
$grants=$this->getGrants();
$name='tac_'.$vid;
      $form[$name]=$this->addVocabularyTermRules(
          $vid,
          $vocabLabel,
          (string)t('The default settings apply to all terms in %vocab that do not have their own below.', array('%vocab' => $vocabLabel)),
          TRUE,
          $this->taxonomy_access_grant_table($grants, $vid, (string)t('Term'), false)
        );
          $form[$name]['new'][$vid] = $this->addTermFieldset('x'.$vid, $add_options);
    dpm($form, 'form');
    return $form;
  }

  function addTermFieldSet($vid, $add_options){
    $fieldset = array(
      '#type' => 'details',
      '#open' => TRUE,
//      '#title' => (string)t('Add term'),
//            '#tree' => TRUE,
//      '#attributes' => array('class' => array('container-inline', 'taxonomy-access-add')),
    );
/*
    $fieldset = array(
      '#type' => 'select',
      '#title' => (string)t('Term'),
      '#options' => $add_options,
    );
    $fieldset['recursive'] = array(
      '#type' => 'checkbox',
      '#title' => (string)t('with descendants'),
    );
         $fieldset['grants'] =
           $this->taxonomy_access_grant_one_row_table($vocab_default, $vid);
*/
    $fieldset['add'] = array(
      '#type' => 'submit',
      '#name' => $vid,
      '#submit' => array('::taxonomy_access_add_term_submit'),
      '#value' => (string)t('Add'),
    );
//      dpm($form[$name], 'form af ' .$name);
    // Fieldset to add a new term if there are any.
    return $fieldset;
  }

  function fieldsetEnableVocabularies($disabled){
    $fieldset = array(
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => t('Add vocabulary'),
    );
    $fieldset['enable_vocab'] = array(
      '#type' => 'select',
      '#title' => t('Vocabulary'),
      '#options' => $disabled,
    );
    $fieldset['add'] = array(
      '#type' => 'submit',
      '#submit' => array('::taxonomy_access_enable_vocab_submit'),
      '#value' => t('Add'),
    );
    return $fieldset;
  }

  function getUserDefaults($rid){
dpm($rid, 'rid');
    $defaults =
      db_query(
      'SELECT vid, grant_view, grant_update, grant_delete, grant_create,
              grant_list
       FROM {taxonomy_access_default}
         WHERE rid = :rid',
        array(':rid' => $rid))
      ->fetchAllAssoc('vid');
dpm($defaults, 'defaults');
    $defaults=$this->make_array($defaults);
    return $defaults ;
  }

  function getUserRecords($rid){
    $records =
      db_query(
        'SELECT ta.tid, td.vid, ta.grant_view, ta.grant_update, ta.grant_delete,
                ta.grant_create, ta.grant_list
         FROM {taxonomy_access_term} ta
         INNER JOIN {taxonomy_term_data} td ON ta.tid = td.tid
         WHERE rid = :rid',
        array(':rid' => $rid))
      ->fetchAllAssoc('tid');
    $records=$this->make_array($records);
    return $records ;
  }

  function makeEnableDisableStatus($rid){
    $field=NULL;
    $return=FALSE;
    // For custom roles, allow the user to enable or disable grants for the role.
    if (!in_array($rid, array(
        TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID,
        TaxonomyAccessService::TAXONOMY_ACCESS_AUTHENTICATED_RID))){
      $roles = $this->taxonomyAccessService->_taxonomy_access_user_roles();
      $roleName=$roles[$rid]->label();
      // If the role is not enabled, return only a link to enable it.
      if (!$this->taxonomyAccessService->taxonomy_access_role_enabled($rid)) {
        $field = array(
          '#markup' => '<p>' . t(
            'Access control for the %name role is disabled. <a href="@url">Enable @name</a>.',
            array(
              '%name' => $roleName,
              '@name' => $roleName,
              '@url' => $this->taxonomy_access_enable_role_url($rid))) . '</p>'
        );
        $return=TRUE;
      }
      // Otherwise, add a link to disable and build the rest of the form.
      else {
        $query = drupal_get_destination();
        $urlParameters=array('rid' => $rid, 'query' => $query);
        $url=\Drupal\Core\Url::fromRoute('taxonomy_access.admin_role_delete', $urlParameters);
        $disable_url = $url->toString();
        $field = array(
          '#markup' => '<p>' . t(
            'Access control for the %name role is enabled. <a href="@url">Disable @name</a>.',
            array(
              '%name' => $roleName,
              '@name' => $roleName,
              '@url' => $disable_url)) . '</p>'
        );
      }
    }
    return array('return' => $return, 'field' => $field);
  }

  function addVocabularyTermRules($vid, $title, $description, $open, $grants){
    $fieldset = array(
      '#type' => 'details',
  //    '#title' => (string)$title,
  //    '#description' => (string)$description,
      '#open' => $open,
    );
    $fieldset['grants'][$vid] = $grants;
//dpm($grants);
    return $fieldset ;
  }

  /**
   * Generates a grant table for multiple access rules.
   *
   * @param array $rows
   *   An array of grant row data, keyed by an ID (term, vocab, role, etc.). Each
   *   row should include the following keys:
   *   - name: (optional) The label for the row (e.g., a term, vocabulary, or
   *     role name).
   *   - view: The View grant value select box for the element.
   *   - update: The Update grant value select box for the element.
   *   - delete: The Delete grant value select box for the element.
   *   - create: The Add tag grant value select box for the element.
   *   - list: The View tag grant value select box for the element.
   * @param int $parent_vid
   *   The parent ID for the table in the form tree structure (typically a
   *   vocabulary id).
   * @param string $first_col
   *   The header for the first column (in the 'name' key for each row).
   * @param bool $delete
   *   (optional) Whether to add a deletion checkbox to each row along with a
   *   "Check all" box in the table header. The checbox is automatically disabled
   *   for TAXONOMY_ACCESS_VOCABULARY_DEFAULT. Defaults to TRUE.
   *
   * @return
   *   Renderable array containing the table.
   *
   * @see taxonomy_access_grant_table()
   */
  function taxonomy_access_grant_table(array $rows, $parent_vid, $first_col, $delete = TRUE) {
    $header = $this->taxonomy_access_grant_table_header();
/*
    if ($first_col) {
      array_unshift(
        $header,
        array('data' => $first_col, 'class' => array('taxonomy-access-label'))
      );
    }
    if ($delete) {
  //    drupal_add_js('misc/tableselect.js');
      array_unshift($header, array('class' => array('select-all')));
    }
*/
    $table = array(
      '#type' => 'table',
      '#header' => $header,
    );
    dpm($parent_vid, 'parent_id'. 'delete='.$delete);
    foreach ($rows as $id => $row) {
      $table[$parent_vid] = $this->taxonomy_access_admin_build_row($row, 'name', $delete);
  //    $table[$id] = $this->taxonomy_access_admin_build_row($row, 'name', $delete);
    }
    // Disable the delete checkbox for the default.
    if ($delete && isset($table[TaxonomyAccessService::TAXONOMY_ACCESS_VOCABULARY_DEFAULT])) {
      $table[TaxonomyAccessService::TAXONOMY_ACCESS_VOCABULARY_DEFAULT]['remove']['#disabled'] = TRUE;
    }
//    dpm($table, 'table '.$parent_vid);
    return $table;
  }

  /**
   * Generates a grant table for adding access rules with one set of values.
   *
   * @param array $rows
   *   An associative array of access rule data, with the following keys:
   *   - view: The View grant value select box for the element.
   *   - update: The Update grant value select box for the element.
   *   - delete: The Delete grant value select box for the element.
   *   - create: The Add tag grant value select box for the element.
   *   - list: The View tag grant value select box for the element.
   * @param int $id
   *   The ID for this set (e.g., a vocabulary ID).
   *
   * @return
   *   Renderable array containing the table.
   *
   * @see taxonomy_access_grant_table()
   */
  function taxonomy_access_grant_one_row_table($row, $id) {
    $header = $this->taxonomy_access_grant_table_header();
    $table = array(
      '#type' => 'table',
      '#header' => $header,
    );
    $table[$id] = $this->taxonomy_access_admin_build_row($row);
    return $table;
  }

  /**
   * Returns a header array for grant form tables.
   *
   * @return array
   *   An array of header cell data for a grant table.
   */
  function taxonomy_access_grant_table_header() {
    $header = array(
      array('data' => (string)t('View')),
  /*    array('data' => (string)t('Update')),
      array('data' => (string)t('Delete')),
      array('data' => (string)t('Add Tag')),
      array('data' => (string)t('View Tag')),

*/
    );
    foreach ($header as &$cell) {
 //     $cell['class'] = array('taxonomy-access-grant');
    }
    return $header;
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
  function taxonomy_access_admin_build_row(array $grants, $label_key = NULL, $delete = FALSE) {
/*
    if ($delete) {
      $form['remove'] = array(
        '#type' => 'checkbox',
        '#title' => (string)t('Delete access rule for @name', array('@name' => $grants[$label_key])),
        '#title_display' => 'invisible',
      );
    }
    if ($label_key) {
      $form[$label_key] = array(
        '#type' => 'markup',
        '#markup' => \Drupal\Component\Utility\Html::escape($grants[$label_key]),
      );
    }
*/

    foreach (array('view') as $grant) {
  //  foreach (array('view', 'update', 'delete', 'create', 'list') as $grant) {
//      $for = $label_key ? $grants[$label_key] : NULL;
      $form[$grant] = array(
//          '#markup' => '<p>hi kim</p>',
        '#type' => 'select',
 //       '#title' => $this->_taxonomy_access_grant_field_label($grant, $for),
 //       '#title_display' => 'invisible',
        '#default_value' => is_string($grants['grant_' . $grant]) ? $grants['grant_' . $grant] : TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE,
        '#required' => TRUE,
        '#options' => array( 
        TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW => (string)t('Allow')),
      );
    }
    foreach (array('view') as $grant) {
      $form[$grant]['#options'] = array(
        TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW => (string)t('Allow'),
        TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE => (string)t('Ignore'),
        TaxonomyAccessService::TAXONOMY_ACCESS_NODE_DENY => (string)t('Deny'),
      );
    }
/*
    foreach (array('create', 'list') as $grant) {
      $form[$grant]['#options'] = array(
        TaxonomyAccessService::TAXONOMY_ACCESS_TERM_ALLOW => (string)t('Allow'),
        TaxonomyAccessService::TAXONOMY_ACCESS_TERM_DENY => (string)t('Deny'),
      );
    }
 */
 return $form;
  }

  /**
   * Returns the proper invisible field label for each grant table element.
   */
  function _taxonomy_access_grant_field_label($grant, $for = NULL) {
    if ($for) {
      $label = array('@label' => $for);
      $titles = array(
        'view' => (string)t('View grant for @label', $label),
        'update' => (string)t('Update grant for @label', $label),
        'delete' => (string)t('Delete grant for @label', $label),
        'create' => (string)t('Add tag grant for @label', $label),
        'list' => (string)t('View tag grant for @label', $label),
      );
    }
    else {
      $titles = array(
        'view' => (string)t('View grant'),
        'update' => (string)t('Update grant'),
        'delete' => (string)t('Delete grant'),
        'create' => (string)t('Add tag grant'),
        'list' => (string)t('View tag grant'),
      );
    }

   return $titles[$grant];
  }

  /**
   * Form submission handler for taxonomy_access_admin_role().
   *
   * Processes submissions for the vocabulary 'Add' button.
   */
  function taxonomy_access_enable_vocab_submit(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $rid = $form_state->getValue('rid');
    $vid = $form_state->getValue('enable_vocab');
    $roles = $this->taxonomyAccessService->_taxonomy_access_user_roles();
    $vocab = taxonomy_vocabulary_load($vid);
    if ($this->taxonomyAccessService->taxonomy_access_enable_vocab($vid, $rid)) {
      $this->taxonomyAccessService->taxonomy_access_rebuild();
      drupal_set_message(t(
        'Vocabulary %vocab enabled successfully for the %role role.',
        array(
          '%vocab' => $vocab->label(),
          '%role' => $roles[$rid]->label())));
    }
    else {
      drupal_set_message(t('The vocabulary could not be enabled.'), 'error');
    }
  }

  /**
   * Form submission handler for taxonomy_access_admin_role().
   *
   * Processes submissions for the term 'Add' button.
   */
  function taxonomy_access_add_term_submit($form, \Drupal\Core\Form\FormStateInterface &$form_state) {
    $submitButton = $form_state->getTriggeringElement();
    dpm($submitButton,'submitButton');
    $vid = $submitButton['#name'];
    $newArray = $form_state->getValue('new');
    dpm($newArray,'newArray');
    $new = $newArray[$vid];
    $rid = $form_state->getValue('rid');
    list($type, $id) = explode(' ', $new['item']);
    $rows = array();
    dpm($new[$vid]['grants'][0], 'xxx');
  return;
    $rows[$id] =
      $this->taxonomyAccessService->_taxonomy_access_format_grant_record($id, $rid, $new[$vid]['grants'][TaxonomyAccessService::TAXONOMY_ACCESS_VOCABULARY_DEFAULT]);

    // If we are adding children recursively, add those as well.
    if ($new['recursive'] == 1) {
      $children = $this->taxonomyAccessService->_taxonomy_access_get_descendants($id);
      foreach ($children as $tid) {
        $rows[$tid] =
          $this->taxonomyAccessService->_taxonomy_access_format_grant_record($tid, $rid, $new[$vid]['grants'][TaxonomyAccessService::TAXONOMY_ACCESS_VOCABULARY_DEFAULT]);
      }
    }

    // Set the grants for the row or rows.
    $this->taxonomyAccessService->taxonomy_access_set_term_grants($rows);
    $this->taxonomyAccessService->taxonomy_access_rebuild();
  }


  /**
   * Form submission handler for taxonomy_access_admin_role().
   *
   * Processes submissions for the "Delete selected" button.
   *
   * @todo
   *   The parent form could probably be refactored to make this more efficient
   *   (by putting these elements in a flat list) but that would require changing
   *   taxonomy_access_grant_table() and taxonomy_access_build_row().
   */
  function taxonomy_access_delete_selected_submit($form, &$form_state) {
   dpm('deleting');
   $rid = $form_state->getValue('rid');
    $delete_tids = array();
    foreach ($form_state->getValue('grants') as $vid => $tids) {
      foreach ($tids as $tid => $record) {
        if (!empty($record['remove'])) {
          $delete_tids[] = $tid;
        }
      }
    }
    if ($rid) {
      if ($this->taxonomyAccessService->taxonomy_access_delete_term_grants($delete_tids, $rid)) {
        $this->taxonomyAccessService->taxonomy_access_rebuild();
        drupal_set_message(
          \Drupal::translation()->formatPlural(
            sizeof($delete_tids),
            '1 term access rule was deleted.',
            '@count term access rules were deleted.'));
      }
      else {
        drupal_set_message(t('The records could not be deleted.'), 'warning');
      }
    }
  }
  /**
  * Form submission handler for taxonomy_access_admin_form().
   *
   * Processes submissions for the 'Save all' button.
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $rid = $form_state->getValue('rid');

    // Create four lists of records to update.
    $update_terms = array();
    $skip_terms = array();
    $update_defaults = array();
    $skip_defaults = array();

    $vocabularyNames=$form['#vocabularyNames'];
    foreach ($vocabularyNames as $vocabularyName => $vid) {
      $rows=$form_state->getValue($vid);
      $element = $form[$vocabularyName];
dpm($element, 'element ' . $vocabularyName);
      foreach ($rows as $tid => $row) {
        // Check the default values for this row.
        $termDefault=$element[$vid][$tid];
        dpm($termDefault, 'term default ' . $vid . ' tid='.$tid);
        $defaults = array();
        $grants = array();
        foreach (array('view', 'update', 'delete', 'create', 'list') as $grant_name) {
          $grants[$grant_name] = $row[$grant_name];
          $defaults[$grant_name] = $termDefault[$grant_name]['#default_value'];
        }

        // Proceed if the user changed the row (values differ from defaults).
        if ($defaults != $grants) {
          // If the grants for node access match the defaults, then we
          // can skip updating node access records for this row.
          $update_nodes = FALSE;
          foreach (array('view', 'update', 'delete') as $op) {
            if ($defaults[$op] != $grants[$op]) {
              $update_nodes = TRUE;
            }
          }

          // Add the row to one of the four arrays.
          switch (TRUE) {
            // Term record with node grant changes.
            case ($tid && $update_nodes):
              $update_terms[$tid] =
                $this->taxonomyAccessService->_taxonomy_access_format_grant_record($tid, $rid, $grants);
              break;

            // Term record and no node grant changes.
            case ($tid && !$update_nodes):
              $skip_terms[$tid] =
                $this->taxonomyAccessService->_taxonomy_access_format_grant_record($tid, $rid, $grants);
              break;

            // Vocab record with node grant changes.
            case (!$tid && $update_nodes):
              $update_defaults[$vid] =
                $this->taxonomyAccessService->_taxonomy_access_format_grant_record($vid, $rid, $grants, TRUE);
              break;

            // Vocab record and no node grant changes.
            case (!$tid && !$update_nodes):
              $skip_defaults[$vid] =
                $this->taxonomyAccessService->_taxonomy_access_format_grant_record($vid, $rid, $grants, TRUE);
              break;
          }
        }
      }
    }
    // Process each set.
    if (!empty($update_terms)) {
      $this->taxonomyAccessService->taxonomy_access_set_term_grants($update_terms);
    }
    if (!empty($skip_terms)) {
      $this->taxonomyAccessService->taxonomy_access_set_term_grants($skip_terms, FALSE);
    }
    if (!empty($update_defaults)) {
      $this->taxonomyAccessService->taxonomy_access_set_default_grants($update_defaults);
    }
    if (!empty($skip_defaults)) {
      $this->taxonomyAccessService->taxonomy_access_set_default_grants($skip_defaults, FALSE);
    }
    $this->taxonomyAccessService->taxonomy_access_rebuild();
  }

}
