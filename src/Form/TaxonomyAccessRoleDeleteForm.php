<?php

/**
 * @file
 * Contains \Drupal\taxonomy_access\Form\TaxonomyAccessRoleDeleteForm.
 */

namespace Drupal\taxonomy_access\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;

/**
 * Defines a confirmation form for deleting mymodule data.
 */
class TaxonomyAccessRoleDeleteForm extends ConfirmFormBase {

  public function getFormId() {
    return 'taxonomy_access_role_delete';
  }

  /**
   * The ID of the item to delete.
   *
   * @var string
   */
  protected $id;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Do you want to delete %id?', array('%id' => $this->id));
  }

  /**
   * {@inheritdoc}
   */
    public function getCancelUrl() {
      return new Url('my_module.myroute');
  }

  /**
   * {@inheritdoc}
   */
    public function getDescription() {
    return t('Only do this if you are sure!');
  }

  /**
   * {@inheritdoc}
   */
    public function getConfirmText() {
    return t('Delete it!');
  }

  /**
   * {@inheritdoc}
   */
    public function getCancelText() {
    return t('Nevermind');
  }

  /**
   * {@inheritdoc}
   *
   * @param int $id
   *   (optional) The ID of the item to be deleted.
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $roleId = NULL) {
        $form['status'] = [
          '#markup' => '<p>TBD work for role delete</p>',
          ];
        return $form;
    $this->id = $roleId;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
