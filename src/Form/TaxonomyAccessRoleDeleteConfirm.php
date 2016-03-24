<?php

/**
 * @file
 * Contains \Drupal\taxonomy_access\Form\TaxonomyAccessRoleDeleteConfirm.
 */

namespace Drupal\taxonomy_access\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class TaxonomyAccessRoleDeleteConfirm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_access_role_delete_confirm';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $rid = NULL) {
    $roles = _taxonomy_access_user_roles();
    if (taxonomy_access_role_enabled($rid)) {
      $form['rid'] = [
        '#type' => 'value',
        '#value' => $rid,
      ];
      return confirm_form($form, t("Are you sure you want to delete all taxonomy access rules for the role %role?", [
        '%role' => $roles[$rid]
        ]), TAXONOMY_ACCESS_CONFIG . '/role/%/edit', t('This action cannot be undone.'), t('Delete all'), t('Cancel'));
    }
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $roles = _taxonomy_access_user_roles();
    $rid = $form_state->getValue(['rid']);
    if (is_numeric($rid) && $rid != \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE && $rid != \Drupal\Core\Session\AccountInterface::AUTHENTICATED_RID) {
      if ($form_state->getValue(['confirm'])) {
        $form_state->set(['redirect'], TAXONOMY_ACCESS_CONFIG);
        taxonomy_access_delete_role_grants($rid);
        drupal_set_message(t('All taxonomy access rules deleted for role %role.', [
          '%role' => $roles[$rid]
          ]));
      }
    }
  }

}
