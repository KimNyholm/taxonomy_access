<?php /**
 * @file
 * Contains \Drupal\taxonomy_access\Controller\DefaultController.
 */

namespace Drupal\taxonomy_access\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Component\Utility\UrlHelper;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\user\RoleInterface;
use Drupal\taxonomy_access\TaxonomyAccessAdminRole;

/**
 * Default controller for the taxonomy_access module.
 */
class DefaultController extends ControllerBase {

  /**
   * Enables access control for a given role.
   *
   * @param int $rid
   *   The role ID.
   *
   * @return bool
   *   TRUE on success, or FALSE on failure.
   *
   * @todo
   *   Should we default to the authenticated user global default?
   */
  static public function taxonomy_access_enable_role($rid) {
    return true ;
    $rid = intval($rid);

    // Take no action if the role is already enabled. All valid role IDs are > 0.
    if (!$rid || taxonomy_access_role_enabled($rid)) {
      return FALSE;
    }

    // If we are adding a role, no global default is set yet, so insert it now.
    // Assemble a $row object for Schema API.
    $row = new stdClass();
    $row->vid = TAXONOMY_ACCESS_GLOBAL_DEFAULT;
    $row->rid = $rid;

    // Insert the row with defaults for all grants.
    return drupal_write_record('taxonomy_access_default', $row);
  }

  /**
   * Indicates whether access control is enabled for a given role.
   *
   * @param int $rid
   *   The role ID.
   *
   * @return bool
   *   TRUE if access control is enabled for the role, or FALSE otherwise.
   */
  static public function taxonomy_access_role_enabled($rid) {
    $role_status = &drupal_static(__FUNCTION__, array());
    if (!isset($role_status[$rid])) {
      $role_status['administrator'] = 0 ;
      $role_status['authenticated'] = 1 ;
      $role_status['anonymous']     = 1 ;
    }
    return (bool) $role_status[$rid];
  }


  static public function taxonomy_accessRoleName($roleId){
    // Seems to be some bug in autoloader.
    // To fix, but how?
    // $role=\Drupal\User\Entity\Role::load($roleId);
    // $roleName=empty($role) ? "Unkownn role id '$roleId'" : $role->label();
    $roleName=$roleId;
    return $roleName;
  }

/**
 * Generates a URL to enable a role with a token for CSRF protection.
 *
 * @param int $rid
 *   The role ID.
 *
 * @return string
 *   The full URL for the request path.
 */
static function taxonomy_access_enable_role_url($roleId) {
  // Create a query array with a token to validate the sumbission.
//  $query = drupal_get_destination();
//  $query['token'] = drupal_get_token($rid);
  // Build and return the URL with the token and destination.
  // TBD add role id and token
  $urlParameters=array('roleId' => $roleId);
  $url=Url::fromRoute('taxonomy_access.admin_role_enable', $urlParameters);
  return $url->toString();
}


static function _taxonomy_access_user_roles($permission = NULL) {
  $roles = &drupal_static(__FUNCTION__, array());
  if (!isset($roles[$permission])) {
    $roles[$permission] = user_roles(FALSE, $permission);
  }
  return $roles[$permission];
}

static function UserRoleList(){
  $roles=DefaultController::_taxonomy_access_user_roles();
  $rows=array();
  foreach ($roles as $rid => $role) {
    $urlParameters=array('roleId' => $rid);
    $url=Url::fromRoute('taxonomy_access.settings_role', $urlParameters);
    $link = \Drupal::l(t('Configure'), $url);
    $roleEnabled = DefaultController::taxonomy_access_role_enabled($rid);
    $state = $roleEnabled ? t('Enabled') : t('Disabled') ;
    $row = array($role->label(), $state, $link,);
    $rows[]=$row;
  }
  return $rows  ;
}

  public function taxonomy_access_admin() {

    $header = [t('Role'), t('Status'), t('Operations')];
    $rows=$this->UserRoleList();

    $build['role_table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    return $build;
  }

  public function taxonomy_access_enable_role_validate($roleId=NULL) {
    drupal_set_message('taxonomy_access_enable_role_validate requires more work',  'error');
    // If a valid token is not provided, return a 403.
    $uri = \Drupal::request()->getRequestUri();
    $fragments=UrlHelper::parse($uri);
    // If a valid token is not provided, return a 403.
    if (empty($query['token']) || !drupal_valid_token($query['token'], $rid)) {
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
    if (DefaultController::taxonomy_access_enable_role($rid)) {
      drupal_set_message(t('Role %name enabled successfully.', [
        '%name' => $roles[$rid]
        ]));
    }
    // TBD redirect to role edit.
    //drupal_goto();
    //return $this->redirect('taxonomy_access.settings_role');
    $urlParameters=array('roleId' => $roleId);
    $url=Url::fromRoute('taxonomy_access.settings_role', $urlParameters);
    $response = new \Symfony\Component\HttpFoundation\RedirectResponse($url->toString());
    return $response ;
 }

  public function taxonomy_access_disable_vocab_confirm_page($rid, $vocab) {
    $rid = intval($rid);

    // Return a 404 on invalid vid or rid.
    if (!$vocab->vid || !$rid) {
      return MENU_NOT_FOUND;
    }

    return \Drupal::formBuilder()->getForm('taxonomy_access_disable_vocab_confirm', $rid, $vocab);
  }


}
