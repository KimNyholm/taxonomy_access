<?php /**
 * @file
 * Contains \Drupal\taxonomy_access\Controller\DefaultController.
 */

namespace Drupal\taxonomy_access\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Component\Utility\UrlHelper;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\user\RoleInterface;
use Drupal\taxonomy_access\TaxonomyAccessAdminRole;

use Drupal\Core\Config\Config;

/**
 * Default controller for the taxonomy_access module.
 */
class DefaultController extends ControllerBase {

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

  static public function taxonomy_access_role_enabled($rid) {
    $config = \Drupal::config('taxonomy_access.settings');
    $defaults=$config->get('taxonomy_access_default');
    return isset($defaults[$rid]) ? true : false ;
  }

  protected function getEditableConfigNames() {
    return [
      'taxonomy_access.settings',
    ];
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
  $urlParameters=array('roleId' => $roleId);
  $url=Url::fromRoute('taxonomy_access.admin_role_enable', $urlParameters);
  return $url->toString();
}

static function taxonomy_access_delete_role_url($roleId) {
  //  $query = drupal_get_destination();
  $urlParameters=array('roleId' => $roleId);
  $url=Url::fromRoute('taxonomy_access.admin_role_delete', $urlParameters);
  return $url->toString();
}

function UserRoleList(){
  $roles=$this->taxonomyAccessService->_taxonomy_access_user_roles();
  $rows=array();
  foreach ($roles as $rid => $role) {
    $urlParameters=array('roleId' => $rid);
    $url=Url::fromRoute('taxonomy_access.settings_role', $urlParameters);
    $link = \Drupal::l(t('Configure'), $url);
    $roleEnabled = $this->taxonomyAccessService->taxonomy_access_role_enabled($rid);
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

  public function taxonomy_access_disable_vocab_confirm_page($rid, $vocab) {
    $rid = intval($rid);

    // Return a 404 on invalid vid or rid.
    if (!$vocab->vid || !$rid) {
      return MENU_NOT_FOUND;
    }

    return \Drupal::formBuilder()->getForm('taxonomy_access_disable_vocab_confirm', $rid, $vocab);
  }

}
