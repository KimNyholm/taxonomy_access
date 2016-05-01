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
function demo($parameter=NULL) {

  $message='Hello';
  $markup= "<p>$message from the sandbox</p>";
  return array(
      '#markup' => $markup,
    );

}


function contents() {

  $markup= '<p>Hello from your sandbox.</p>';
  return array(
      '#markup' => $markup,
    );

}

}

