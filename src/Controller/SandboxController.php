<?php /**
 * @file
 * Contains \Drupal\taxonomy_access\Controller\SandboxController.
 */

namespace Drupal\taxonomy_access\Controller;

use Drupal\taxonomy_access\TaxonomyAccessService;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default controller for the taxonomy_access module.
 */
class SandboxController extends ControllerBase {


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
function checkPermission($user, $permission){
  $hasIt = $user->hasPermission($permission);
  $name=$user->id();
  drupal_set_message("User $name hasIt=$hasIt for $permission.");
}

function showRebuild(){
  $r=node_access_needs_rebuild();
  drupal_set_message("Needs rebuild=$r");
}

function rebuildPermissions() {
  $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
  $this->checkPermission($user, 'administer site configuration');
  $this->checkPermission($user, 'access adminstration pages');
  $this->showRebuild();
  node_access_needs_rebuild(TRUE);
  $this->showRebuild();
  //node_access_rebuild();
  $markup= '<p>Permissions rebuild.</p>';
  return array(
      '#markup' => $markup,
    );
}

function demo($parameter=NULL) {
  $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
//  dpm($user);
//  dpm($user->roles);
  $roles=user_roles();
//  dpm($roles, 'roles');
  foreach ($roles as $rid => $role){
    dpm($role->id(), 'role id');
    dpm($role->label(), 'role label');
    if ($user->hasRole($rid)){
      dpm($role, 'user has role ' . $rid);
    } else {
      dpm('user has not role ' . $rid);
    }
  }
  $message="Parameter=$parameter";
  $x=\Drupal::translation()->formatPlural($parameter, 'en', 'mange');

  $r=node_access_needs_rebuild();
  dpm($r, 'r');
  $markup= "<p>$message $x from the sandbox</p>";
  return array(
      '#markup' => $markup,
    );

}


function contents() {

  $r=node_access_needs_rebuild();
  dpm($r, 'r');
  $markup= '<p>Hello from your sandbox.</p>';
  return array(
      '#markup' => $markup,
    );

}

}

