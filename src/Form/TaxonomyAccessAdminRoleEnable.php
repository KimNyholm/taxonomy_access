<?php

/**
 * @file
 * Contains \Drupal\taxonomy_access\Form\TaxonomyAccessAdminRoleEnable.
 */

namespace Drupal\taxonomy_access\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\user\RoleInterface;
use Drupal\taxonomy_access\Controller;
use Drupal\taxonomy_access\Controller\DefaultController;

class TaxonomyAccessAdminRoleEnable extends FormBase {

  protected function getEditableConfigNames() {
    return [
      'taxonomy_access.settings',
    ];
  }

  public function getTitle($roleId){
    $role=\Drupal\User\Entity\Role::load($roleId);
    $roleName=$role->label();
    return "Access rules for $roleName";
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_access_admin_role_enable';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $roleId = NULL) {
    $roles = DefaultController::_taxonomy_access_user_roles();
    $role=$roles[$roleId];
    $name=$role->label();
    dpm($role, $roleId);
      $form['status'] = [
        '#markup' => '<p>' . t('Access control for the %name role is disabled. <a href="@url">Enable @name</a>.', [
          '%name' => $name,
          '@name' => $name,
          '@url' => DefaultController::taxonomy_access_enable_role_url($rid),
        ]) . '</p>'
        ];
      return $form;
  }
}

