<?php

/**
 * @file
 * Contains \Drupal\taxonomy_access\Form\SandboxForm.
 */

namespace Drupal\taxonomy_access\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\taxonomy_access\TaxonomyAccessService;


class SandboxForm extends \Drupal\Core\Form\FormBase {

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

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_access_sandbox_form';
  }

  function make_array($list){
    $result=[];
    foreach($list as $key =>$item){
      $result[$key]=(array)$item;
    }
    return $result;
  }

function getTerm_grants(){
  return  array
(
    'tags' => array
        (
        )

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
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $rid = NULL) {
    $vid='tags';
    $name='tac_'.$vid;
    $grants=$this->getGrants();
    $form[$name]=$this->addVocabularyTermRules(
          $vid,
          'Tags',
          (string)t('The default settings apply to all terms in %vocab that do not have their own below.', array('%vocab' => 'Tags')),
          TRUE,
          $this->taxonomy_access_grant_table($grants, $vid, (string)t('Term'),false)
        );
//  $form['tac_'.$vid]['new'][$vid]=$this->addTermFieldSet('voc'.$vid);
  $form['tac_'.$vid]['new'][$vid]=$this->addTermFieldSet('x'.$vid);
  dpm($form, 'form');
  return $form;
}


  function taxonomy_access_add_term_submit($form, \Drupal\Core\Form\FormStateInterface &$form_state) {
    $submitButton = $form_state->getTriggeringElement();
    dpm($submitButton,'submitButton');
    $vid = $submitButton['#name'];
    $allArray = $form_state->getValue();
    dpm($allArray,'allArray');
    $newArray = $form_state->getValue('new');
    dpm($newArray,'newArray');
}





// below is same as taxadminform
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

  function addVocabularyTermRules($vid, $title, $description, $open, $grants){
  //return array();
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

public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
  $rid = $form_state->getValue('rid');
  $vocabs = \Drupal\taxonomy\Entity\Vocabulary::loadMultiple();
  $vocs=$form['vocabularies']['#values'];
  dpm($vocs, 'vocs');
  // Create four lists of records to update.
  $update_terms = array();
  $skip_terms = array();
  $update_defaults = array();
  $skip_defaults = array();

  $values=$form_state->getValue();
  dpm($values, 'values');
  $grants=$form_state->getValue('grants');
  dpm($grants, 'grants');
}

}
