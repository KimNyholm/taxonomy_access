<?php

/**
 * @file
 * Contains \Drupal\taxonomy_access\Form\TaxonomyAccessRoleDeleteForm.
 */

namespace Drupal\taxonomy_access\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\user\RoleInterface;
use Drupal\taxonomy_access\Controller\DefaultController;
use Drupal\Core\Url;


use Drupal\Core\Form\ConfirmFormBase;

/**
 * Defines a confirmation form for deleting mymodule data.
 */
class TaxonomyAccessRoleDeleteForm extends ConfirmFormBase {

  protected function taxonomy_access_delete_record($table, $roleId){
    $config = \Drupal::service('config.factory')->getEditable('taxonomy_access.settings');
    $rows=$config->get($table);
    unset($rows[$roleId]);
    $config
      ->set($table, $rows)
      ->save();
    return true ;
  }

  public function getFormId() {
    return 'taxonomy_access_role_delete';
  }

  /**
   * The ID of the item to delete.
   *
   * @var string
   */
  protected $id;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    //$roleName = taxonomy_accessRoleName($this->id);
    //$roleName = \Drupal\taxonomy_access\Form\TaxonomyAcccessAdminRole::taxonomy_accessRoleName($this->id);
    //$role=\Drupal\User\Entity\Role::load($roleId);
    $roleName = \Drupal\taxonomy_access\Controller\DefaultController::taxonomy_accessRoleName($this->id);
    return t('Are you sure you want to delete all taxonomy access rules for the role %id?', array('%id' => $roleName));
  }

  /**
   * {@inheritdoc}
   */
    public function getCancelUrl() {
      return new Url('taxonomy_access.settings');
  }

  /**
   * {@inheritdoc}
   */
    public function getConfirmText() {
    return t('Delete all');
  }

  /**
   * {@inheritdoc}
   *
   * @param int $id
   *   (optional) The ID of the item to be deleted.
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $roleId = NULL) {
    $this->id = $roleId;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm($this->id, 'being deleted');
    $this->taxonomy_access_delete_record('taxonomy_access_default', $this->id);
  }

}
