<?php

/**
 * @file
 * Contains \Drupal\taxonomy_access\Form\TaxonomyAccessRoleDeleteForm.
 */

namespace Drupal\taxonomy_access\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\user\RoleInterface;
use Drupal\Core\Url;
use Drupal\Core\Form\ConfirmFormBase;

use Drupal\taxonomy_access\TaxonomyAccessService;

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
    $roleName = $this->taxonomyAccessService->roleNumberToName($this->rid);
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
    $deleted = $this->taxonomyAccessService->taxonomy_access_delete_role_grants($rid);
    if ($deleted){
      drupal_set_message(t('All taxonomy access rules deleted for role %role.',
        array('%role' => $this->taxonomyAccessService->roleNumberToName($rid))));
    } else {
      drupal_set_message(t('Taxonomy access rules not deleted for role %role.',
        array('%role' => $this->taxonomyAccessService->roleNumberToName($rid))), 'error');
    }
    $urlParameters=array('rid' => $rid);
    $form_state->setRedirect('taxonomy_access.admin_role_edit', $urlParameters);
  }

}