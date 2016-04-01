<?php

/**
 * @file
 * Contains \Drupal\taxonomy_access\Form\TaxonomyAccessRoleDisableForm.
 */

namespace Drupal\taxonomy_access\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\user\RoleInterface;
use Drupal\taxonomy_access\Controller\DefaultController;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


use Drupal\Core\Form\ConfirmFormBase;

/**
 * Defines a confirmation form for deleting mymodule data.
 */
class TaxonomyAccessRoleDisableForm extends ConfirmFormBase {

  protected function taxonomy_access_disable_record($rid, $vid){
    dpm($rid, 'to be implemented disabling voc ' . $vid);
    return true ;
  }

  public function getFormId() {
    return 'taxonomy_access_role_disable';
  }

  /**
   * The ID of the item to delete.
   *
   * @var string
   */
  protected $rid;
  protected $vid;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    //$roleName = taxonomy_accessRoleName($this->id);
    //$roleName = \Drupal\taxonomy_access\Form\TaxonomyAcccessAdminRole::taxonomy_accessRoleName($this->id);
    //$role=\Drupal\User\Entity\Role::load($roleId);
    $roleName = \Drupal\taxonomy_access\Controller\DefaultController::taxonomy_accessRoleName($this->id);
    return t('Are you sure you want to delete all taxonomy access rules for %vid in the %rid role?', array('%vid' => $this->vid, '%rid' => $this->rid));
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
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $rid= NULL, $vid=NULL) {
    // Return a 404 on invalid vid or rid.
    if (empty($rid) || empty($vid)){
      throw new NotFoundHttpException();
    }
    $this->rid = $rid;
    $this->vid = $vid;
   return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->taxonomy_access_disable_record($this->rid, $this->vid);
  }

}