<?php /**
 * @file
 * Contains \Drupal\taxonomy_access\Controller\TaxonomyAccessController.
 */

namespace Drupal\taxonomy_access\Controller;

use Drupal\taxonomy_access\TaxonomyAccessService;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default controller for the taxonomy_access module.
 */
class TaxonomyAccessController extends ControllerBase {

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


function taxonomy_access_admin() {
  $roles = $this->taxonomyAccessService->_taxonomy_access_user_roles();
  $active_rids = db_query(
    'SELECT rid FROM {taxonomy_access_default} WHERE vid = :vid',
    array(':vid' => TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT)
  )->fetchCol();
  $header = array(t('Role'), t('Status'), t('Operations'));
  $rows = array();

  foreach ($roles as $rid => $role) {
    $row = array();
    $row[] = $role->label();

    if (in_array($rid, $active_rids)) {
      // Add edit operation link for active roles.
      $row[] = array('data' => t('Enabled'));

    }
    else {
      // Add enable link for unconfigured roles.
      $row[] = array('data' => t('Disabled'));
    }
    $urlParameters=array('rid' => $rid);
    $url=Url::fromRoute('taxonomy_access.admin_role_edit', $urlParameters);
// FIX ME, configure missing settings symbol.
    $row[]=\Drupal::l(t('Configure'), $url);
/*    $row[] = array('data' => l(
      t("Configure"),
      TAXONOMY_ACCESS_CONFIG . "/role/$rid/edit",
      array('attributes' => array('class' => array('module-link', 'module-link-configure')))));
*/
    $rows[] = $row;
  }

  $build['role_table'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
  );

  return $build;
}

}
