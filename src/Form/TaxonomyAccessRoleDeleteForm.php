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
use Symfony\Component\DependencyInjection\ContainerInterface;


use Drupal\Core\Form\ConfirmFormBase;

/**
 * Defines a confirmation form for deleting mymodule data.
 */
class TaxonomyAccessRoleDeleteForm extends ConfirmFormBase {

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
    $roleName = \Drupal\taxonomy_access\Controller\DefaultController::taxonomy_accessRoleName($this->rid);
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
   * Form submission handler for taxonomy_role_delete_confirm().
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $rid = $this->rid;
    if (!in_array($rid, [
      \Drupal\user\RoleInterface::ANONYMOUS_ID,
      \Drupal\user\RoleInterface::AUTHENTICATED_ID
      ])) {
      $roles = $this->taxonomyAccessService->_taxonomy_access_user_roles();
      $this->taxonomyAccessService->taxonomy_access_delete_role_grants($rid);
      drupal_set_message(t('All taxonomy access rules deleted for role %role.',
          array('%role' => $roles[$rid]->$rid)));
      $form_state->setRedirect('taxonomy_access.settings');
    }
  }

}
