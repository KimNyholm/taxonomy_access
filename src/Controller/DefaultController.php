<?php /**
 * @file
 * Contains \Drupal\taxonomy_access\Controller\DefaultController.
 */

namespace Drupal\taxonomy_access\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

function _taxonomy_access_user_roles($permission = NULL) {
  $roles = &drupal_static(__FUNCTION__, array());
  if (!isset($roles[$permission])) {
    $roles[$permission] = user_roles(FALSE, $permission);
  }
  return $roles[$permission];
}

function UserRoleList(){
  $roles=_taxonomy_access_user_roles();
  $rows=array();
  foreach ($roles as $rid => $role) {
    $roleId=array('roleId' => $rid);
    $url=Url::fromRoute('taxonomy_access.settings_role', $roleId);
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

/**
 * Default controller for the taxonomy_access module.
 */
class DefaultController extends ControllerBase {

  public function taxonomy_access_admin() {

    $header = [t('Role'), t('Status'), t('Operations')];
    $rows=UserRoleList();

    $build['role_table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    return $build;
      return array(
              '#title' => 'Hello World!',
              '#markup' => 'Here is some new d8 content from taxonomy.',
          );
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

  public function taxonomy_access_enable_role_validate($rid) {
    $rid = intval($rid);
    // If a valid token is not provided, return a 403.
    $query = \Drupal\Component\Utility\UrlHelper::filterQueryParameters();
    if (empty($query['token']) || !drupal_valid_token($query['token'], $rid)) {
      return MENU_ACCESS_DENIED;
    }
    // Return a 404 for the anonymous or authenticated roles.
    if (in_array($rid, [
      \Drupal\Core\Session\AccountInterface::ANONYMOUS_ROLE,
      \Drupal\Core\Session\AccountInterface::AUTHENTICATED_RID,
    ])) {
      return MENU_NOT_FOUND;
    }
    // Return a 404 for invalid role IDs.
    $roles = _taxonomy_access_user_roles();
    if (empty($roles[$rid])) {
      return MENU_NOT_FOUND;
    }

    // If the parameters pass validation, enable the role and complete redirect.
    if (taxonomy_access_enable_role($rid)) {
      drupal_set_message(t('Role %name enabled successfully.', [
        '%name' => $roles[$rid]
        ]));
    }
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

  public function taxonomy_access_autocomplete($field_name, $tags_typed = '') {
    // Enforce that list grants do not filter the autocomplete.
    taxonomy_access_disable_list();

    $field = field_info_field($field_name);

    // The user enters a comma-separated list of tags. We only autocomplete the last tag.
    $tags_typed = drupal_explode_tags($tags_typed);
    $tag_last = \Drupal\Component\Utility\Unicode::strtolower(array_pop($tags_typed));

    $matches = [];
    if ($tag_last != '') {

      // Part of the criteria for the query come from the field's own settings.
      $vids = [];
      $vocabularies = taxonomy_vocabulary_get_names();
      foreach ($field['settings']['allowed_values'] as $tree) {
        $vids[] = $vocabularies[$tree['vocabulary']]->vid;
      }

      $query = db_select('taxonomy_term_data', 't');
      $query->addTag('translatable');
      $query->addTag('term_access');

      // Do not select already entered terms.
      if (!empty($tags_typed)) {
        $query->condition('t.name', $tags_typed, 'NOT IN');
      }
      // Select rows that match by term name.
      $tags_return = $query
        ->fields('t', ['tid', 'name'])
        ->condition('t.vid', $vids)
        ->condition('t.name', '%' . db_like($tag_last) . '%', 'LIKE')
        ->range(0, 10)
        ->execute()
        ->fetchAllKeyed();

      // Unset suggestions disallowed by create grants.
      $disallowed = taxonomy_access_create_disallowed(array_keys($tags_return));
      foreach ($disallowed as $tid) {
        unset($tags_return[$tid]);
      }

      $prefix = count($tags_typed) ? drupal_implode_tags($tags_typed) . ', ' : '';

      $term_matches = [];
      foreach ($tags_return as $tid => $name) {
        $n = $name;
        // Term names containing commas or quotes must be wrapped in quotes.
        if (strpos($name, ',') !== FALSE || strpos($name, '"') !== FALSE) {
          $n = '"' . str_replace('"', '""', $name) . '"';
        }
        $term_matches[$prefix . $n] = \Drupal\Component\Utility\Html::escape($name);
      }
    }

    drupal_json_output($term_matches);
  }

}
