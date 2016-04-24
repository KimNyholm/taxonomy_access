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
    $add_options=array('o1', 'o2');
    $vocab_defaults=$this->getDefault();
    foreach ($vocab_defaults as $vid => $vocab_default){
      $name=$vid;
      $form[$name]= array(
        '#type' => 'details',
        '#title' => $name,
        '#description' => 
              (string)t('The default settings apply to all terms in %vocab that do not have their own below.', array('%vocab' => $name)),
        '#open' => TRUE,
        );
      $table = array(
        '#type' => 'table',
        '#header' => array('fieldxyz', 'xyzcheckbox'),
        );

      $table[0]['f1'] = array(
        '#type' => 'select',
        '#required' => TRUE,
        '#options' => array('c1' => 'choise 1'),
        );
      $table[0]['f2'] = array(
          '#type' => 'checkbox',
          '#title' => 'in here',
        );
      $form[$name]['table'.$name] = $table;
      $form[$name]['recursive'.$name] = array(
          '#type' => 'checkbox',
          '#title' => 'with descendants',
        );

      if (!empty($add_options)) {
        $form[$name]['new'.$vid] = array(
          '#type' => 'details',
          '#open' => TRUE,
          '#title' => 'Add term',
          '#attributes' => array('class' => array('container-inline', 'taxonomy-access-add')),
        );
        $form[$name]['new'.$vid]['item'.$vid] = array(
          '#type' => 'select',
          '#title' => 'Term',
          '#options' => $add_options,
        );
        $form[$name]['new'.$vid]['recursive'.$vid] = array(
          '#type' => 'checkbox',
          '#title' => 'with descendants',
        );
        $form[$name]['new'.$vid]['grants'.$vid] =
          $this->taxonomy_access_grant_add_table($vocab_default, $vid);
        $form[$name]['new'.$vid]['add'.$vid] = array(
          '#type' => 'submit',
          '#vocabulary_name' => $vid,
          '#submit' => array('::taxonomy_access_add_term_submit'),
          '#value' => 'Add',
        );
      }

 }
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => (string)t('Save all'),
    );
  dpm($form, 'form');
  return $form;
}


  function taxonomy_access_add_term_submit($form, \Drupal\Core\Form\FormStateInterface &$form_state) {
    $submitButton = $form_state->getTriggeringElement();
    dpm($submitButton,'submitButton');
    $vid = $submitButton['#vocabulary_name'];
    $allArray = $form_state->getValues();
    dpm($allArray,'allArray');
    $newArray = $form_state->getValue('new');
    dpm($newArray,'newArray');
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
      $table[$id] = $this->taxonomy_access_admin_build_row($row, 'name', $delete);
    }
    // Disable the delete checkbox for the default.
    if ($delete && isset($table[TaxonomyAccessService::TAXONOMY_ACCESS_VOCABULARY_DEFAULT])) {
      $table[TaxonomyAccessService::TAXONOMY_ACCESS_VOCABULARY_DEFAULT]['remove']['#disabled'] = TRUE;
    }
//    dpm($table, 'table '.$parent_vid);
    return $table;
  }

function taxonomy_access_grant_add_table($row, $id) {
  $header = $this->taxonomy_access_grant_table_header();
  $table = array(
    '#type' => 'table',
    '#header' => $header,
  );
  $table[$id][TaxonomyAccessService::TAXONOMY_ACCESS_VOCABULARY_DEFAULT] = $this->taxonomy_access_admin_build_row($row);

  return $table;
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
  $values=$form_state->getValues();
  dpm($values, 'values');
}

}
