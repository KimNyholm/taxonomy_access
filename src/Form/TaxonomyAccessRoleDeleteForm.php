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

  public function getFormId() {
    return 'taxonomy_access_role_delete';
  }

  /**
   * The ID of the item to delete.
   *
   * @var string
   */
  protected $rid;

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
    $urlParameters=array('rid' => $this->rid);
    $url=Url::fromRoute('taxonomy_access.admin_role_edit', $urlParameters);
    return $url ;
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
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $rid= NULL) {
    $this->rid = $rid;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $roleId = $this->id;
    if (!in_array($roleId, [
      \Drupal\user\RoleInterface::ANONYMOUS_ID,
      \Drupal\user\RoleInterface::AUTHENTICATED_ID
      ])) {
      dpm($this->rid, 'being deleted');
      $form_state->setRedirect('taxonomy_access.settings');
    }
  }

}
