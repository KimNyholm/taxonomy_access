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
    );
}
public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {

  $defaults = $this->getDefault();

  // Add a fieldset for the global default.
  $form['global_default'] = array(
    '#type' => 'details',
    '#title' => t('Global default'),
    '#description' => t('The global default controls access to untagged nodes. It is also used as the default for disabled vocabularies.'),
    '#open' =>TRUE, 
  );
  $form['global_default']['grants']['global_defaults'] = $this->taxonomy_access_grant_add_table($defaults[TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT], TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT);

  foreach (['_voc1', 'voc2'] as $vid){
    $form[$vid] = array(
      '#type' => 'details',
      '#title' =>$vid, 
      '#description' => t('xxxxx It is also used as the default for disabled vocabularies.'),
      '#open' =>TRUE, 
    );
    $form[$vid]['grants'][$vid] = $this->taxonomy_access_grant_add_table($defaults[TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT], TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT);
    $form['vocabularies']['#values']=array('a', 'b', 'c');
  }

  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => (string)t('Save all'),
  );
  return $form;
}


function taxonomy_access_grant_add_table($row, $id) {
  $header = $this->taxonomy_access_grant_table_header();
  $table = array(
    '#type' => 'table',
    '#header' => $header,
  );
  $row=$this->taxonomy_access_admin_build_row($row);
  $table[$id] = $this->taxonomy_access_admin_build_row($row);
  return $table;
}

function taxonomy_access_grant_table_header() {
  $header = array(
    array('data' => (string)t('View')),
    array('data' => (string)t('Update')),
    array('data' => (string)t('Delete')),
  );
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
  $row=array();
  foreach (array('view', 'update', 'delete') as $grant) {
    $row[$grant]= array(
      '#type' => 'select',
      '#default_value' => is_string($grants['grant_' . $grant]) ? $grants['grant_' . $grant] : TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE,
      '#required' => TRUE,
      '#options' => array(
      TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW => (string)t('Allow'),
      TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE => (string)t('Ignore'),
      TaxonomyAccessService::TAXONOMY_ACCESS_NODE_DENY => (string)t('Deny'),
    ));
  }
  return $row;
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
