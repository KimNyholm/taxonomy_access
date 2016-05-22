<?php

/**
 * @file
 * Contains \Drupal\taxonomy_access\Form\TaxonomyAccessRoleEnableForm.
 */

namespace Drupal\taxonomy_access\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\user\RoleInterface;
use Drupal\taxonomy_access\Controller;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;

use Drupal\taxonomy_access\TaxonomyAccessService;

class TaxonomyAccessRoleEnableForm extends \Drupal\Core\Form\FormBase {

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
 * Page callback: Enables a role if the proper token is provided.
 *
 * @param int $rid
 *   The role ID.
 */
function taxonomy_access_enable_role_validate($rid) {
  // If a valid token is not provided, return a 403.
  $uri = \Drupal::request()->getRequestUri();
  $fragments=UrlHelper::parse($uri);
  // If a valid token is not provided, return a 403.
  // Fix me, token validation skipped for now.
  // if (empty($query['token']) || !drupal_valid_token($query['token'], $rid)) {
  //   throw new AccessDeniedHttpException();
  // }
  // Return a 404 for the anonymous or authenticated roles.
  if (in_array($rid, [
    TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID,
    TaxonomyAccessService::TAXONOMY_ACCESS_AUTHENTICATED_RID,
  ])) {
    throw new NotFoundHttpException();
  }
  // Return a 404 for invalid role IDs.
  $roles = $this->taxonomyAccessService->_taxonomy_access_user_roles();
  if (empty($roles[$rid])) {
    throw new NotFoundHttpException();
  }

  // If the parameters pass validation, enable the role and complete redirect.
  if ($this->taxonomyAccessService->taxonomy_access_enable_role($rid)) {
    drupal_set_message(t('Role %name enabled successfully.', [
      '%name' => $rid,
      ]));
  }
  // redirect
  $urlParameters=array('rid' => $rid);
  $url=Url::fromRoute('taxonomy_access.admin_role_edit', $urlParameters);
  // Required for WebTestBase::clickLink() not to fail.
  $url->setAbsolute();
  $response = new \Symfony\Component\HttpFoundation\RedirectResponse($url->toString());
  // redirect
  return $response ;
}

  public function submitForm(array &$form, FormStateInterface $form_state) {
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_access_role_enable';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $rid = NULL) {
    return $this->taxonomy_access_enable_role_validate($rid);
  }
}

