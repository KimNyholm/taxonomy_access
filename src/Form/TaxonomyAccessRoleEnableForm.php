<?php

/**
 * @file
 * Contains \Drupal\taxonomy_access\Form\TaxonomyAccessRoleEnableForm.
 */

namespace Drupal\taxonomy_access\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\user\RoleInterface;
use Drupal\taxonomy_access\Controller;
use Drupal\taxonomy_access\Controller\DefaultController;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TaxonomyAccessRoleEnableForm extends ConfigFormBase {

  protected function taxonomy_access_enable_role($rid) {
    // Take no action if the role is already enabled. All valid role IDs are > 0.
    if (!$rid || DefaultController::taxonomy_access_role_enabled($rid)) {
      return FALSE;
    }

    $config = $this->config('taxonomy_access.settings');
    $roles=$config->get('roles');
    $roles[$rid]= 1 ;
    $config
      ->set('roles', $roles)
      ->save();
    return true ;
    // If we are adding a role, no global default is set yet, so insert it now.
    // Assemble a $row object for Schema API.
    $row = new stdClass();
    $row->vid = TAXONOMY_ACCESS_GLOBAL_DEFAULT;
    $row->rid = $rid;
    // Insert the row with defaults for all grants.
    return drupal_write_record('taxonomy_access_default', $row);
  }

  protected function taxonomy_access_enable_role_validate($roleId) {
    // If a valid token is not provided, return a 403.
    $uri = \Drupal::request()->getRequestUri();
    $fragments=UrlHelper::parse($uri);
    // If a valid token is not provided, return a 403.
    if (empty($query['token']) || !drupal_valid_token($query['token'], $rid)) {
      drupal_set_message('taxonomy_access_enable_role_validate needs more validation',  'error');
// TBD
//      throw new AccessDeniedHttpException();
    }
    // Return a 404 for the anonymous or authenticated roles.
    if (in_array($rid, [
      \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE,
      \Drupal\Core\Session\AccountInterface::AUTHENTICATED_ROLE,
    ])) {
      throw new NotFoundHttpException();
    }
    // Return a 404 for invalid role IDs.
    $roles = DefaultController::_taxonomy_access_user_roles();
    if (empty($roles[$roleId])) {
      throw new NotFoundHttpException();
    }

    // If the parameters pass validation, enable the role and complete redirect.
    if ($this->taxonomy_access_enable_role($roleId)) {
      drupal_set_message(t('Role %name enabled successfully.', [
        '%name' => $roleId,
        ]));
    }

    // redirect
    $urlParameters=array('roleId' => $roleId);
    $url=Url::fromRoute('taxonomy_access.settings_role', $urlParameters);
    $response = new \Symfony\Component\HttpFoundation\RedirectResponse($url->toString());
    $config = $this->config('taxonomy_access.settings')
       ->set('roleid', $roleId)
       ->save();
    return $response ;
 }

  protected function getEditableConfigNames() {
    return [
      'taxonomy_access.settings',
    ];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_access_role_enable';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $roleId = NULL) {
    return $this->taxonomy_access_enable_role_validate($roleId);
  }
}

