<?php /**
 * @file
 * Contains \Drupal\taxonomy_access\EventSubscriber\InitSubscriber.
 */

namespace Drupal\taxonomy_access\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InitSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::REQUEST => ['onEvent', 0]];
  }

  public function onEvent() {
    $path = drupal_get_path('module', 'taxonomy_access');
    // @FIXME
    // The Assets API has totally changed. CSS, JavaScript, and libraries are now
    // attached directly to render arrays using the #attached property.
    // 
    // 
    // @see https://www.drupal.org/node/2169605
    // @see https://www.drupal.org/node/2408597
    // drupal_add_css($path . '/taxonomy_access.css');


    // Register our shutdown function.
    drupal_register_shutdown_function('taxonomy_access_shutdown');
  }

}
