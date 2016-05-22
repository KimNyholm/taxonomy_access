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
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfirmFormBase;

class TaxonomyAccessRoleDisableForm extends ConfirmFormBase {

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
    return t('Are you sure you want to delete all taxonomy access rules for %vid in the %rid role?', 
      array('%vid' => $this->vid, '%rid' => $this->taxonomyAccessService->roleNumberToName($this->rid)));
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
    $rid = $this->rid ;
    $vid = $this->vid ;
    $roles = $this->taxonomyAccessService->_taxonomy_access_user_roles();
    // Do not proceed for invalid role IDs, and do not allow the global default
    // to be deleted.
    if (empty($vid) || empty($rid) || !isset($roles[$rid])) {
      return FALSE;
    }
    if ($this->taxonomyAccessService->taxonomy_access_disable_vocab($vid, $rid)) {
      drupal_set_message(
        t('All Taxonomy access rules deleted for %vocab in role %role.',
          array(
            '%vocab' => $this->vid,
            '%role' => $roles[$rid]->label())
         ));
      $urlParameters=array('rid' => $rid);
      $form_state->setRedirect('taxonomy_access.admin_role_edit', $urlParameters);
      
    }
  }

}
