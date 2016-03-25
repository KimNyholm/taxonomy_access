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

static function taxonomy_access_role_enabled($roleId) {
  return false ;
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
    $row = array(
      $role->label(),
      'Disabled',
      $link,
      );
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

  public function xtaxonomy_access_admin() {
    $roles = _taxonomy_access_user_roles();
    $active_rids = db_query('SELECT rid FROM {taxonomy_access_default} WHERE vid = :vid', [
      ':vid' => TAXONOMY_ACCESS_GLOBAL_DEFAULT
      ])->fetchCol();

    $header = [t('Role'), t('Status'), t('Operations')];
    $rows = [];

    foreach ($roles as $rid => $name) {
      $row = [];
      $row[] = $name;

      if (in_array($rid, $active_rids)) {
        // Add edit operation link for active roles.
        $row[] = [
          'data' => t('Enabled')
          ];

      }
      else {
        // Add enable link for unconfigured roles.
        $row[] = [
          'data' => t('Disabled')
          ];
      }
      // @FIXME
      // l() expects a Url object, created from a route name or external URI.
      // $row[] = array('data' => l(
      //       t("Configure"),
      //       TAXONOMY_ACCESS_CONFIG . "/role/$rid/edit",
      //       array('attributes' => array('class' => array('module-link', 'module-link-configure')))));

      $rows[] = $row;
    }

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
      throw new AccessDeniedHttpException();
    }
    // Return a 404 for the anonymous or authenticated roles.
    if (in_array($rid, [
      \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE,
      \Drupal\Core\Session\AccountInterface::AUTHENTICATED_RID,
    ])) {
      throw new NotFoundHttpException();
    }
    // Return a 404 for invalid role IDs.
    $roles = _taxonomy_access_user_roles();
    if (empty($roles[$rid])) {
      throw new NotFoundHttpException();
    }

    // If the parameters pass validation, enable the role and complete redirect.
    if (taxonomy_access_enable_role($rid)) {
      drupal_set_message(t('Role %name enabled successfully.', [
        '%name' => $roles[$rid]
        ]));
    }
    // TBD redirect to role edit.
    drupal_goto();
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
