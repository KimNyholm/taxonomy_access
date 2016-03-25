<?php

/**
 * @file
 * Contains \Drupal\taxonomy_access\Form\PantsSettings.
 */

namespace Drupal\taxonomy_access\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class TaxonomyAccessSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'taxonomy_access.settings',
    ];
  }

  public function getFormId() {
    return 'taxonomy_access_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('taxonomy_access.settings');

    foreach (Element::children($form) as $variable) {
      $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
    }
    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);

    // @todo Decouple from form: http://drupal.org/node/2040135.
    Cache::invalidateTags(array('config:taxonomy_access.settings'));
  }

  public function buildForm(array $form, FormStateInterface $form_state, $roleId = 0) {


    $form['taxonomy_settings_wrapping_element'] = array(
      '#type' => 'select',
      '#title' => $this->t("Select wrapping element for role $roleId"),
      '#options' => $elements,
    );

    return parent::buildForm($form, $form_state);
  }
}
