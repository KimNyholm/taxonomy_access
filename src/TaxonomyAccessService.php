<?php

/**
 * @file
 * Contains Drupal\taxonomy_access\TaxonomyAccessService.
 */

namespace Drupal\taxonomy_access;

class TaxonomyAccessService {

/**
 * Maximum number of nodes for which to update node access within the module.
 *
 * If the number of affected nodes is greater, then node_access_needs_rebuild()
 * will be set instead.
 */
// In Drupal 8 reduced from 500 to 0 until an implementatin for 
//  node_access_acquire_grants() is found
  const TAXONOMY_ACCESS_MAX_UPDATE = 0 ;

/**
 * Base path for module administration pages.
 */
const TAXONOMY_ACCESS_CONFIG = 'admin/config/people/taxonomy_access' ;

/**
 * Global default ('synthetic vocabulary')
 */
const TAXONOMY_ACCESS_GLOBAL_DEFAULT = 'tac_gd___' ;

/**
 * Vocabulary default (synthetic term id').
 */
//const TAXONOMY_ACCESS_VOCABULARY_DEFAULT = 'tac_gd___' ;

const TAXONOMY_ACCESS_VOCABULARY_DEFAULT = 0 ;

/**
 * 'Allow' grant value for nodes.
 */
const TAXONOMY_ACCESS_NODE_ALLOW = 1 ;

/**
 * 'Ignore' grant value for nodes.
 */
const TAXONOMY_ACCESS_NODE_IGNORE = 0 ;

/**
 * 'Deny' grant value for nodes.
 */
const TAXONOMY_ACCESS_NODE_DENY = 2 ;

/**
 * 'Allow' grant value for terms.
 */
const TAXONOMY_ACCESS_TERM_ALLOW = 1 ;

/**
 * 'Deny' grant value for terms.
 */
const TAXONOMY_ACCESS_TERM_DENY = 0 ;

const TAXONOMY_ACCESS_ANONYMOUS_RID = 1 ;

const TAXONOMY_ACCESS_AUTHENTICATED_RID = 2;

  // Taxonomy access uses role ids as grant ids, which are numerical.
  // Taxonomy access maintains its own translation list from known user role
  // names to corresponding role numbers. The non numerical role id is only
  // required during configuration. Therefore the taxonomy access tables stores
  // the numerical role id.
  // TBD: Add logic to cleanup unused role ids.
  public function roleIdToNumber($ridMachineName){
    $config = \Drupal::service('config.factory')->getEditable('taxonomy_access.settings');
    $roleNumber=$config->get('roleNumber');
    if (!isset($roleNumber[$ridMachineName])){
      $roleNumber[$ridMachineName]=count($roleNumber)+1;
      $config->set('roleNumber', $roleNumber)
        ->save();
    }
    return $roleNumber[$ridMachineName];
  }

  public function roleNumberToRid($roleNumber){
    $roles=$this->_taxonomy_access_user_roles();
    $name='';
    if (isset($roles[$roleNumber])){
      $role=$roles[$roleNumber];
      $rid=$role->id();
    }
    return $rid;
  }

  public function roleNumberToName($roleNumber){
    $roles=$this->_taxonomy_access_user_roles();
    $name='';
    if (isset($roles[$roleNumber])){
      $role=$roles[$roleNumber];
      $name=$role->label();
    }
    return $name;
  }

  protected function drupal_write_record($table, $row)
  {
    $key=array('rid' => $row->rid);
    if (isset($row->tid)){
      $key['tid']=$row->tid;
    }
    if (isset($row->vid)){
      $key['vid']=$row->vid;
    }
    $fields=(array)$row;
    \Drupal::database()->merge($table)
      ->key($key)
      ->fields($fields)
      ->execute();
  }
/**
 * Caches a list of all roles.
 *
 * @param string|null $permission
 *   (optional) A string containing a permission.  If set, only roles
 *   containing that permission are returned.  Defaults to NULL.
 *
 * @return array
 *   An array of roles from user_roles().
 *
 * @todo
 *   Replace this function once http://drupal.org/node/6463 is backported.
 */
function _taxonomy_access_user_roles($permission = NULL) {
  $roleListByName=user_roles(FALSE, $permission);
  $roleListByNumber=array();
  foreach($roleListByName as $role){
    $roleNumber=$this->roleIdToNumber($role->id());
    $roleListByNumber[$roleNumber]=$role;
  }
  return $roleListByNumber;
}

/**
 * Title callback: Returns the title for the role edit form.
 */
function taxonomy_access_role_edit_title($rid) {
  $roles = _taxonomy_access_user_roles();
  return t('Access rules for @role', array('@role' => $roles[$rid]));
}

/**
 * Access callback: Determines whether the admin form can be accessed.
 */
function taxonomy_access_role_edit_access($rid) {
  // Allow access only if the user may administer permissions.
  if (!user_access('administer permissions')) {
    return FALSE;
  }

  // Do not render the form for invalid role IDs.
  $roles = _taxonomy_access_user_roles();
  if (empty($roles[$rid])) {
    return FALSE;
  }

  // If the conditions above are met, grant access.
  return TRUE;
}


/**
 * Access callback for role deletion form.
 */
function taxonomy_access_role_delete_access($rid) {
  if (!user_access('administer permissions')) {
    return FALSE;
  }
  if (($rid == DRUPAL_ANONYMOUS_RID) || ($rid == DRUPAL_AUTHENTICATED_RID)) {
    return FALSE;
  }

  $roles = _taxonomy_access_user_roles();
  if (empty($roles[$rid])) {
    return FALSE;
  }

  return TRUE;
}


/**
 * Gives access to taxonomies based on the taxonomy_access table.
 */
function taxonomy_access_node_grants(\Drupal\Core\Session\AccountInterface $user, $op) {
  $roles = $user->getRoles();
  $roleGrants=array();
  foreach($roles as $role){
    $roleGrants[]= $this->roleIdToNumber($role);
  }
  return array('taxonomy_access_role' => $roleGrants);
}

/**
 * Enables access control for a given role.
 *
 * @param int $rid
 *   The role ID.
 *
 * @return bool
 *   TRUE on success, or FALSE on failure.
 *
 * @todo
 *   Should we default to the authenticated user global default?
 */
function taxonomy_access_enable_role($rid) {
  // Take no action if the role is already enabled. All valid role IDs are > 0.
  if (empty($rid) || $this->taxonomy_access_role_enabled($rid)) {
    return FALSE;
  }

  // If we are adding a role, no global default is set yet, so insert it now.
  // Assemble a $row object for Schema API.
  $row = new \stdClass();
  $row->vid = TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT;
  $row->rid = $rid;

  // Insert the row with defaults for all grants.
  return $this->drupal_write_record('taxonomy_access_default', $row);
}

/**
 * Indicates whether access control is enabled for a given role.
 *
 * @param int $rid
 *   The role ID.
 *
 * @return bool
 *   TRUE if access control is enabled for the role, or FALSE otherwise.
 */
function taxonomy_access_role_enabled($rid) {
  $role_status = &drupal_static(__FUNCTION__, array());
  if (!isset($role_status[$rid])) {
    $role_status[$rid] =
      db_query(
        'SELECT 1
         FROM {taxonomy_access_default}
         WHERE rid = :rid AND vid = :vid',
        array(':rid' => $rid, ':vid' => TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT))
      ->fetchField();
  }
  return (bool) $role_status[$rid];
}

/**
 * Enables a vocabulary for the given role.
 *
 * @param int $vid
 *   The vocabulary ID to enable.
 * @param int $rid
 *   The role ID.
 *
 * @return bool
 *   TRUE on success, or FALSE on failure.
 *
 * @see taxnomomy_access_enable_role()
 */
function taxonomy_access_enable_vocab($vid, $rid) {
  // All valid role IDs are > 0, and we do not enable the global default here.
  if (empty($rid) || empty($vid)) {
    return FALSE;
  }
  // Take no action if the vocabulary is already enabled for the role.
  $vocab_status =
    db_query(
      'SELECT 1
       FROM {taxonomy_access_default}
       WHERE rid = :rid AND vid = :vid',
      array(':rid' => $rid, ':vid' => $vid))
    ->fetchField();
  if ($vocab_status) {
    return FALSE;
  }
  // Otherwise, initialize the vocabulary default with the global default.
  // Use our API functions so that node access gets updated as needed.
  $global_default =
    db_query(
      'SELECT grant_view, grant_update, grant_delete, grant_create, grant_list
       FROM {taxonomy_access_default}
       WHERE vid = :vid AND rid = :rid',
       array(':rid' => $rid, ':vid' => TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT))
    ->fetchAssoc();
  $record = $this->_taxonomy_access_format_grant_record($vid, $rid, $global_default, TRUE);
  return $this->taxonomy_access_set_default_grants(array($vid => $record));
}

/**
 * Disables a vocabulary for the given role.
 *
 * @param int $vid
 *   The vocabulary ID to enable.
 * @param int $rid
 *   The role ID.
 *
 * @return bool
 *   TRUE on success, or FALSE on failure.
 *
 * @see taxonomy_access_delete_role_grants()
 */
function taxonomy_access_disable_vocab($vid, $rid) {

  // Do not allow the global default to be deleted this way.
  // Deleting the global default would disable the role.
  if (empty($vid) || empty($rid)) {
    return FALSE;
  }

  // Delete the vocabulary default.
  $this->taxonomy_access_delete_default_grants($vid, $rid);

  // Delete the role's term access rules for the vocabulary.
  // First check which term records are enabled so we can update node access.
  $tids =
    db_query(
      "SELECT ta.tid
       FROM {taxonomy_access_term} ta
       INNER JOIN {taxonomy_term_data} td ON ta.tid = td.tid
       WHERE td.vid = :vid AND ta.rid = :rid",
      array(':vid' => $vid, ':rid' => $rid))
    ->fetchCol();
  $this->taxonomy_access_delete_term_grants($tids, $rid);

  return TRUE;
}


/**
 * @defgroup tac_affected_nodes Taxonomy Access Control: Node access update mechanism
 * @{
 * Update node access on shutdown in response to other changes.
 */


/**
 * Flags node access for rebuild with a message for administrators.
 */
function _taxonomy_access_flag_rebuild() {
  drupal_set_message(t("Taxonomy Access Control is updating node access... If you see a message that content access permissions need to be rebuilt, you may wait until after you have completed your configuration changes."), 'status');
  node_access_needs_rebuild(TRUE);
}


/**
 * Updates node access grants for a set of nodes.
 *
 * @param array $nids
 *   An array of node IDs for which to acquire access permissions.
 *
 * @todo
 *   Unset rebuild message when we set the flag to false?
 */
function _taxonomy_access_node_access_update(array $nids) {
  // Proceed only if node_access_needs_rebuild() is not already flagged.
  if (!node_access_needs_rebuild()) {
    // Set node_access_needs_rebuild() until we succeed below.
    $this->_taxonomy_access_flag_rebuild();


    // Remove any duplicate nids from the array.
    $nids = array_unique($nids);

    // If the number of nodes is small enough, update node access for each.
    if (sizeof($nids) < TaxonomyAccessService::TAXONOMY_ACCESS_MAX_UPDATE) {
      foreach ($nids as $node) {
        $loaded_node = node_load($node, NULL, TRUE);
        if (!empty($loaded_node)) {
          node_access_acquire_grants($loaded_node);
        }
      }
      // If we make it here our update was successful; unflag rebuild.
      node_access_needs_rebuild(FALSE);
    }
  }
  return TRUE;
}

/**
 * Caches and retrieves nodes affected by a taxonomy change.
 *
 * @param array $affected_nodes
 *   (optional) If we are caching, the list of nids to cache.
 *   Defaults to NULL.
 * @param bool $reset
 *   (optional) Flag to manually reset the list.  Defaults to FALSE.
 *
 * @return
 *   The cached list of nodes.
 */
function taxonomy_access_affected_nodes(array $affected_nodes = NULL, $reset = FALSE) {
  static $nodes = array();

  // If node_access_needs_rebuild or $reset are set, reset list and return.
  if (!empty($nodes)) {
    if (node_access_needs_rebuild() || $reset) {
      $nodes = array();
      return $nodes;
    }
  }
  // If we were passed a list of nodes, cache.
  if (isset($affected_nodes)) {
    $nodes = array_unique(array_merge($nodes, $affected_nodes));

    // Stop caching if there are more nodes than the limit.
    if (sizeof($nodes) >= TaxonomyAccessService::TAXONOMY_ACCESS_MAX_UPDATE) {
      $this->_taxonomy_access_flag_rebuild();
      $nodes=array();
    }
  }
  return $nodes;
}

/**
 * Gets node IDs with controlled terms or vocabs for any of the given roles.
 *
 * @param int $rid
 *    A single role ID.
 *
 * @return array
 *    An array of node IDs associated with terms or vocabularies that are
 *    controlled for the role.
 */
function _taxonomy_access_get_controlled_nodes_for_role($rid) {
  $query = db_select('taxonomy_index', 'ti')
    ->fields('ti', array('nid'))
    ->addTag('taxonomy_access_node');
  $query->leftJoin('taxonomy_term_data', 'td', 'ti.tid = td.tid');
  $query->leftJoin('taxonomy_access_term', 'ta', 'ti.tid = ta.tid');
  $query->leftJoin('taxonomy_access_default', 'tad', 'tad.vid = td.vid');

  // The query builder will automatically use = or IN() as appropriate.
  $query->condition(
    db_or()
    ->condition('ta.rid', $rid)
    ->condition('tad.rid', $rid)
  );

  $nids = $query->execute()->fetchCol();
  return $nids;
}

/**
 * Gets node IDs associated with the roles' global defaults.
 *
 * @param int $rid
 *   A single role ID.
 *
 * @return array
 *    An array of node IDs associated with the global default.
 */
function _taxonomy_access_get_nodes_for_global_default($rid) {
  // Two kinds of nodes are governed by the global default:
  // 1. Nodes with terms controlled neither directly nor by vocab. defaults,
  // 2. Nodes with no terms.

  // Get a list of all terms controlled for the role, either directly or
  // by a vocabulary default.
  $tids = $this->_taxonomy_access_global_controlled_terms($rid);

  $query =
    db_select('node', 'n')
    ->fields('n', array('nid'))
    ->addTag('taxonomy_access_node')
    ;

  // With a left join, the term ID for untagged nodes will be NULL.
  if (!empty($tids)) {
    $query->leftJoin('taxonomy_index', 'ti', 'ti.nid = n.nid');
    $query->condition(
      db_or()
      ->condition('ti.tid', $tids, 'NOT IN')
      ->isNull('ti.tid')
    );
  }

  $nids = $query->execute()->fetchCol();

  return $nids;
}

/**
 * Gets node IDs associated with a given vocabulary.
 *
 * @param int|array $vocab_ids
 *    A single vocabulary ID or an array of IDs.
 * @param int $rid.
 *    (optional) A single role ID.
 *    This argument has the effect of filtering out nodes in terms that
 *    are already controlled invidually for the role.  Defaults to NULL.
 *
 * @return array
 *    An array of node IDs associated with the given vocabulary.
 */
function _taxonomy_access_get_nodes_for_defaults($vocab_ids, $rid = NULL) {
  // Accept either a single vocabulary ID or an array thereof.
  if (!is_array($vocab_ids)) {
    $vocab_ids = array($vocab_ids);
  }
  if (empty($vocab_ids)) {
    return FALSE;
  }

  // If a role was passed, get terms controlled for that role.
  if (!empty($rid)) {
    $tids = $this->_taxonomy_access_vocab_controlled_terms($vocab_ids, $rid);
  }

  $query =
    db_select('taxonomy_index', 'ti')
    ->condition('td.vid', $vocab_ids, 'IN')
    ->fields('ti', array('nid'))
    ->addTag('taxonomy_access_node');
    ;
  $query->join('taxonomy_term_data', 'td', 'td.tid = ti.tid');

  // Exclude records with controlled terms from the results.
  if (!empty($tids)) {
    $query->condition('ti.tid', $tids, 'NOT IN');
  }

  $nids = $query->execute()->fetchCol();
  unset($tids);
  unset($query);

  // If the global default is in the list, fetch those nodes as well.
  if (in_array(TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT, $vocab_ids)) {
    $nids =
      array_merge($nids, $this->_taxonomy_access_get_nodes_for_global_default($rid));
  }

  return $nids;
}

/**
 * Retrieves a list of terms controlled by the global default for a role.
 *
 * @param int $rid
 *   The role ID.
 *
 * @return array
 *   A list of term IDs.
 */
function _taxonomy_access_global_controlled_terms($rid) {
  $tids =
    db_query(
      "SELECT td.tid
       FROM {taxonomy_term_data} td
       LEFT JOIN {taxonomy_access_term} ta ON td.tid = ta.tid
       LEFT JOIN {taxonomy_access_default} tad ON td.vid = tad.vid
       WHERE ta.rid = :rid OR tad.rid = :rid",
      array(':rid' => $rid)
    )
    ->fetchCol();

  return $tids;
}

/**
 * Retrieves a list of terms controlled by the global default for a role.
 *
 * @param int $rid
 *   The role ID.
 *
 * @return array
 *   A list of term IDs.
 */
function _taxonomy_access_vocab_controlled_terms($vids, $rid) {
  // Accept either a single vocabulary ID or an array thereof.
  if (!is_array($vids)) {
    $vids = array((string)$vids);
  }

  $tids =
    db_query(
      "SELECT td.tid
       FROM {taxonomy_term_data} td
       INNER JOIN {taxonomy_access_term} ta ON td.tid = ta.tid
       WHERE ta.rid = :rid
       AND td.vid IN (:vids[])",
      array(':rid' => $rid, ':vids[]' => $vids)
    )
    ->fetchCol();

  return $tids;
}

/**
 * Gets node IDs associated with a given term.
 *
 * @param int|array $term_ids
 *   A single term ID or an array of term IDs.
 *
 * @return array
 *    An array of node IDs associated with the given terms.
 */
function _taxonomy_access_get_nodes_for_terms($term_ids) {
  if (empty($term_ids)) {
    return FALSE;
  }

  $operator=is_array($term_ids) ? 'IN' : '=';
  $nids =
    db_select('taxonomy_index', 'ti')
    ->condition('ti.tid', $term_ids, $operator)
    ->fields('ti', array('nid'))
    ->addTag('taxonomy_access_node')
    ->execute()
    ->fetchCol();

  unset($term_ids);

  return $nids;
}

/**
 * Gets term IDs for all descendants of the given term.
 *
 * @param int $tid
 *    The term ID for which to fetch children.
 *
 * @return array
 *    An array of the IDs of the term's descendants.
 */
function _taxonomy_access_get_descendants($tid) {
  $descendants = &drupal_static(__FUNCTION__, array());

  if (!isset($descendants[$tid])) {
    // Preserve the original state of the list flag.
    $flag_state = $this->taxonomy_access_list_enabled();

    // Enforce that list grants do not filter the results.
    $this->taxonomy_access_disable_list();

    $descendants[$tid] = array();
    $term = taxonomy_term_load($tid);
    $tree = \Drupal::entityManager()->getStorage("taxonomy_term")->loadTree($term->getVocabularyId(), $tid);
    foreach ($tree as $term) {
      $descendants[$tid][] = $term->tid;
    }

    // Restore list flag to previous state.
    if ($flag_state) {
      $this->taxonomy_access_enable_list();
    }

    unset($term);
    unset($tree);
  }

  return $descendants[$tid];
}

/**
 * Gets term IDs for all terms in the vocabulary
 *
 * @param int $vocab_id
 *    The vocabulary ID for which to fetch children.
 *
 * @return array
 *    An array of the IDs of the terms in in the vocabulary.
 */
function _taxonomy_access_get_vocabulary_terms($vocab_id) {
  static $descendants = array();

  if (!isset($descendants[$vocab_id])) {
    // Preserve the original state of the list flag.
    $flag_state = taxonomy_access_list_enabled();

    // Enforce that list grants do not filter the results.
    taxonomy_access_disable_list();

    $descendants[$vocab_id] = array();
    $tree = taxonomy_get_tree($vocab_id);

    foreach ($tree as $term) {
      $descendants[$vocab_id][] = $term->tid;
    }

    // Restore list flag to previous state.
    if ($flag_state) {
      taxonomy_access_enable_list();
    }

    unset($term);
    unset($tree);
  }

  return $descendants[$vocab_id];
}

/**
 * End of "defgroup tac_affected_nodes".
 * @}
 */


/**
 * @defgroup tac_grant_api Taxonomy Access Control: Grant record API
 * @{
 * Store, retrieve, and delete module access rules for terms and vocabularies.
 */


/**
 * Deletes module configurations for the given role IDs.
 *
 * @param int $rid
 *   A single role ID.
 * @param bool $update_nodes
 *   (optional) A flag to determine whether nodes should be queued for update.
 *   Defaults to TRUE.
 *
 * @return bool
 *   TRUE on success, or FALSE on failure.
 */
function taxonomy_access_delete_role_grants($rid, $update_nodes = TRUE) {
  if (empty($rid)) {
    return FALSE;
  }
  if (in_array($rid, [
      TaxonomyAccessService::TAXONOMY_ACCESS_ANONYMOUS_RID,
      TaxonomyAccessService::TAXONOMY_ACCESS_AUTHENTICATED_RID,
    ])) {
    return FALSE;
  }

  if ($update_nodes) {
    // Cache the list of nodes that will be affected by this change.

    // Affected nodes will be those tied to configurations that are more
    // permissive than those from the authenticated user role.

    // If any global defaults are more permissive, we need to update all nodes.
    // Fetch global defaults.
    $global_defaults = $this->taxonomy_access_global_defaults();
    $gd_records = array();
    foreach ($global_defaults as $row) {
      $gd_records[] = $this->_taxonomy_access_format_node_access_record($row);
    }

    // Find the ones we need.
    foreach ($gd_records as $gd) {
      if ($gd['gid'] == $this->roleIdToNumber(\Drupal\user\RoleInterface::AUTHENTICATED_ID)) {
        $auth_gd = $gd;
      }
      elseif ($gd['gid'] == $rid) {
        $role_gd = $gd;
      }
    }

    // Check node grants for the global default.
    // If any is more permissive, flag that we need to update all nodes.
    $all_nodes = FALSE;
    foreach (array('grant_view', 'grant_update', 'grant_delete') as $op) {
      switch ($auth_gd[$op]) {
        // If the authenticated user has a Deny grant, then either Allow or
        // Ignore for the role is more permissive.
        case TaxonomyAccessService::TAXONOMY_ACCESS_NODE_DENY:
          if (($role_gd[$op] == TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE) || ($role_gd[$op] == TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW)){
            $all_nodes = TRUE;
          }
          break 2;

        // If the authenticated user has Ignore, Allow is more permissive.
        case TaxonomyAccessService::TAXONOMY_ACCESS_NODE_IGNORE:
          if ($role_gd[$op] == TaxonomyAccessService::TAXONOMY_ACCESS_NODE_ALLOW) {
            $all_nodes = TRUE;
          }
          break 2;
      }
    }

    // If flagged, add all nodes to the affected nodes cache.
    if ($all_nodes) {
      $affected_nodes = db_query('SELECT nid FROM {node}')->fetchCol();
    }

    // Otherwise, just get nodes controlled by specific configurations.
    else {
      $affected_nodes =
        $this->_taxonomy_access_get_controlled_nodes_for_role($rid);
    }
    $this->taxonomy_access_affected_nodes($affected_nodes);

    unset($affected_nodes);
  }

  db_delete('taxonomy_access_term')
    ->condition('rid', $rid)
    ->execute();

  db_delete('taxonomy_access_default')
    ->condition('rid', $rid)
    ->execute();

  return TRUE;
}

/**
 * Deletes module configurations for the given vocabulary IDs.
 *
 * @param int|array $vocab_ids
 *   A single vocabulary ID or an array of vocabulary IDs.
 * @param int|null $rid
 *   (optional) A single role ID.  Defaults to NULL.
 * @param bool $update_nodes
 *   (optional) A flag to determine whether nodes should be queued for update.
 *   Defaults to TRUE.
 *
 * @return bool
 *   TRUE on success, or FALSE on failure.
 */
function taxonomy_access_delete_default_grants($vocab_ids, $rid = NULL, $update_nodes = TRUE) {
  // Accept either a single vocabulary ID or an array thereof.
  if ($vocab_ids !== TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT && empty($vocab_ids)) {
    return FALSE;
  }

  if ($update_nodes) {
    // Cache the list of nodes that will be affected by this change.
    $affected_nodes = $this->_taxonomy_access_get_nodes_for_defaults($vocab_ids, $rid);
    $this->taxonomy_access_affected_nodes($affected_nodes);
    unset($affected_nodes);
  }

  $operator=is_array($vocab_ids) ? 'IN' : '=';
  $query =
    db_delete('taxonomy_access_default')
    ->condition('vid', $vocab_ids, $operator);

  if (!empty($rid)) {
    $query->condition('rid', $rid);
  }

  $query->execute();
  unset($query);
  return TRUE;
}

/**
 * Deletes module configurations for the given term IDs.
 *
 * @param int|array $term_ids
 *   A single term ID or an array of term IDs.
 * @param int|null $rid
 *   (optional) A single role ID.  Defaults to NULL.
 * @param bool $update_nodes
 *   (optional) A flag to determine whether nodes should be queued for update.
 *   Defaults to TRUE.
 *
 * @return bool
 *   TRUE on success, or FALSE on failure.
 */
function taxonomy_access_delete_term_grants($term_ids, $rid = NULL, $update_nodes = TRUE) {
  // Accept either a single term ID or an array thereof.
  if (!is_array($term_ids)) {
    $term_ids = array($term_ids);
  }

  if (empty($term_ids)) {
    return FALSE;
  }

  if ($update_nodes) {
    // Cache the list of nodes that will be affected by this change.
    $affected_nodes = $this->_taxonomy_access_get_nodes_for_terms($term_ids);
    $this->taxonomy_access_affected_nodes($affected_nodes);
    unset($affected_nodes);
  }

  $query =
    db_delete('taxonomy_access_term')
    ->condition('tid', $term_ids, 'IN');

  if (!empty($rid)) {
    $query->condition('rid', $rid);
  }

  $query->execute();
  unset($term_ids);
  unset($query);
  return TRUE;
}

/**
 * Formats a record to be written to the module's configuration tables.
 *
 * @param int $id
 *   The term or vocabulary ID.
 * @param int $rid
 *   The role ID.
 * @param array $grants
 *   An array of grants to write, in the format grant_name => value.
 *   Allowed keys:
 *   - 'view' or 'grant_view'
 *   - 'update' or 'grant_update'
 *   - 'delete' or 'grant_delete'
 *   - 'create' or 'grant_create'
 *   - 'list' or 'grant_list'
 * @param bool $default
 *   (optional) Whether this is a term record (FALSE) or default record (TRUE).
 *   Defaults to FALSE.
 *
 * @return object
 *   A grant row object formatted for Schema API.
 */
function _taxonomy_access_format_grant_record($id, $rid, array $grants, $default = FALSE) {
  $row = new \stdClass();
  if ($default) {
    $row->vid = $id;
  }
  else {
    $row->tid = $id;
  }
  $row->rid = $rid;
  foreach ($grants as $op => $value) {
    if (is_numeric($value)) {
      $grant_name = 0===strpos($op, 'grant_') ? $op : "grant_$op";
      $row->$grant_name = $value;
    }
  }

  return $row;
}

/**
 * Updates term grants for a role.
 *
 * @param array $grant_rows
 *   An array of grant row objects formatted for Schema API, keyed by term ID.
 * @param bool $update_nodes
 *   (optional) A flag indicating whether to update node access.
 *   Defaults to TRUE.
 *
 * @return bool
 *   TRUE on success, or FALSE on failure.
 *
 * @see _taxonomy_access_format_grant_record()
 */
function taxonomy_access_set_term_grants(array $grant_rows, $update_nodes = TRUE) {
  // Collect lists of term and role IDs in the list.
  $terms_for_roles = array();
  foreach ($grant_rows as $grant_row) {
    $terms_for_roles[$grant_row->rid][] = $grant_row->tid;
  }

  // Delete existing records for the roles and terms.
  // This will also cache a list of the affected nodes.
  foreach ($terms_for_roles as $rid => $tids) {
    $this->taxonomy_access_delete_term_grants($tids, $rid, $update_nodes);
  }

  // Insert new entries.
  foreach ($grant_rows as $row) {
    $this->drupal_write_record('taxonomy_access_term', $row);
  }

  // Later we will refactor; for now return TRUE when this is called.
  return TRUE;
}

/**
 * Updates vocabulary default grants for a role.
 *
 * @param $rid
 *   The role ID to add the permission for.
 * @param (array) $grant_rows
 *   An array of grant rows formatted for Schema API, keyed by vocabulary ID.
 * @param $update_nodes
 *   (optional) A flag indicating whether to update node access.
 *   Defaults to TRUE.
 *
 * @return bool
 *   TRUE on success, or FALSE on failure.
 *
 * @see _taxonomy_access_format_grant_record()
 */
function taxonomy_access_set_default_grants(array $grant_rows, $update_nodes = TRUE) {
  // Collect lists of term and role IDs in the list.
  $vocabs_for_roles = array();
  foreach ($grant_rows as $grant_row) {
    $vocabs_for_roles[$grant_row->rid][] = $grant_row->vid;
  }

  // Delete existing records for the roles and vocabularies.
  // This will also cache a list of the affected nodes.
  foreach ($vocabs_for_roles as $rid => $vids) {
    $this->taxonomy_access_delete_default_grants($vids, $rid, $update_nodes);
  }

  // Insert new entries.
  foreach ($grant_rows as $row) {
    $this->drupal_write_record('taxonomy_access_default', $row);
  }

  // Later we will refactor; for now return TRUE when this is called.
  return TRUE;
}

/**
 * End of "defgroup tac_grant_api".
 * @}
 */

/**
 * @defgroup tac_node_access Taxonomy Access Control: Node access implementation
 * @{
 * Functions to set node access based on configured access rules.
 */

/**
 * Builds a base query object for the specified TAC grants.
 *
 * Callers should add conditions, groupings, and optionally fields.
 *
 * This query should work on D7's supported versions of MySQL and PostgreSQL;
 * patches may be needed for other databases. We add query tags to allow
 * other systems to manipulate the query as needed.
 *
 * @param array $grants
 *   Grants to select.
 *   Allowed values: 'view', 'update', 'delete', 'create', 'list'
 * @param bool $default
 *   (optional) Flag to select default grants only.  Defaults to FALSE.
 *
 * @return object
 *    Query object.
 */
function _taxonomy_access_grant_query(array $grants, $default = FALSE) {
  $table = $default ? 'taxonomy_vocabulary' : 'taxonomy_term_data';
  $query =
    db_select($table, 'td')
    ->addTag('taxonomy_access')
    ->addTag('taxonomy_access_grants')
    ;

  $query->join(
    'taxonomy_access_default', 'tadg',
    'tadg.vid = :vid',
    array(':vid' => TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT)
  );
  $query->leftJoin(
    'taxonomy_access_default', 'tad',
    'tad.vid = td.vid AND tad.rid = tadg.rid'
  );
  if (!$default) {
    $query->leftJoin(
      'taxonomy_access_term', 'ta',
      'ta.tid = td.tid AND ta.rid = tadg.rid'
    );
  }

  // We add grant fields this way to reduce the risk of future vulnerabilities.
  $grant_fields = array(
    'view' => 'grant_view',
    'update' => 'grant_update',
    'delete' => 'grant_delete',
    'create' => 'grant_create',
    'list' => 'grant_list',
  );

  foreach ($grant_fields as $name => $grant) {
    if (in_array($name, $grants)) {
      if ($default) {
        $query->addExpression(
          'BIT_OR(COALESCE('
          . 'tad.' . db_escape_table($grant) . ', '
          . 'tadg.' . db_escape_table($grant)
          . '))',
          $grant
        );
      }
      else {
        $query->addExpression(
          'BIT_OR(COALESCE('
          . 'ta.' . db_escape_table($grant) . ', '
          . 'tad.' . db_escape_table($grant) . ', '
          . 'tadg.' . db_escape_table($grant)
          . '))',
          $grant
        );
      }
    }
  }

  return $query;
}

/**
 * Calculates node access grants by role for the given node ID.
 *
 * @param $node_nid
 *   The node ID for which to calculate grants.
 * @param $reset
 *   (optional) Whether to recalculate the cached values.  Defaults to FALSE.
 *
 * @return
 *    Array formatted for hook_node_access_records().
 *
 * @ingroup tac_node_access
 */
function _taxonomy_access_node_access_records($node_nid, $reset = FALSE) {
  // Build the base node grant query.
  $query = $this->_taxonomy_access_grant_query(array('view', 'update', 'delete'));

  // Select grants for this node only and group by role.
  $query->join(
    'taxonomy_index', 'ti',
    'td.tid = ti.tid'
  );
  $query
    ->fields('tadg', array('rid'))
    ->condition('ti.nid', $node_nid)
    ->groupBy('tadg.rid')
    ->addTag('taxonomy_access_node_access')
    ->addTag('taxonomy_access_node')
    ;

  // Fetch and format all grant records for the node.
  $grants = array();
  $records = $query->execute()->fetchAll();
  // The node grant query returns no rows if the node has no tags.
  // In that scenario, use the global default.
  if (sizeof($records) == 0) {
    $records = $this->taxonomy_access_global_defaults($reset);
  }
  foreach ($records as $record) {
    $grants[] = $this->_taxonomy_access_format_node_access_record($record);
  }
  return $grants;
}

/**
 * Returns an array of global default grants for all roles.
 *
 * @param bool $reset
 *   (optional) Whether to recalculate the cached values.  Defaults to FALSE.
 *
 * @return array
 *   An array of global defaults for each role.
 */
function taxonomy_access_global_defaults($reset = FALSE) {
  $global_grants = &drupal_static(__FUNCTION__, array());
  if (empty($global_grants) || $reset) {
    $global_grants =
      db_query(
        'SELECT rid, grant_view, grant_update, grant_delete, grant_create,
           grant_list
         FROM {taxonomy_access_default}
         WHERE vid = :vid',
         array(':vid' => TaxonomyAccessService::TAXONOMY_ACCESS_GLOBAL_DEFAULT))
      ->fetchAllAssoc('rid');
  }
  return $global_grants;
}

/**
 * Formats a row for hook_node_access_records.
 *
 * @param stdClass $record
 *   The term record object from a TAC query to format.
 *
 * @return array
 *   An array formatted for hook_node_access_records().
 *
 * @todo
 *   Make priority configurable?
 */
function _taxonomy_access_format_node_access_record(\stdClass $record) {

   // TAXONOMY_ACCESS_NODE_IGNORE => 0, TAXONOMY_ACCESS_NODE_ALLOW => 1,
   // TAXONOMY_ACCESS_NODE_DENY => 2 ('10' in binary).
   // Only a value of 1 is considered an 'Allow';
   // with an 'Allow' and no 'Deny', the value from the BIT_OR will be 1.
   // If a 'Deny' is present, the value will then be 3 ('11' in binary).
  return array(
    'realm' => 'taxonomy_access_role',
    'gid' => $record->rid,
    'grant_view' => ($record->grant_view == 1) ? 1 : 0,
    'grant_update' => ($record->grant_update == 1) ? 1 : 0,
    'grant_delete' => ($record->grant_delete == 1) ? 1 : 0,
    'priority' => 0,
  );
}

/**
 * End of "defgroup tac_node_access".
 * @}
 */


/**
 * @defgroup tac_list Taxonomy Access Control: View tag (list) permission
 * @{
 * Alter queries to control the display of taxonomy terms on nodes and listings.
 */


/**
 * Flag to disable list grant filtering (e.g., on node edit forms).
 *
 * @param bool $set_flag
 *   (optional) When passed, sets the the flag.  Pass either TRUE or FALSE.
 *   Defaults to NULL.
 */
function _taxonomy_access_list_state($set_flag = NULL) {
  static $flag = TRUE;
  // If no flag was passed, return the current state of the flag.
  if (is_null($set_flag)) {
    return $flag;
  }
  // If we were passed anything but null, set the flag.
  $flag = $set_flag ? TRUE : FALSE;
}

/**
 * Wrapper for taxonomy_access_list_state() to enable list grant filtering.
 *
 * @see _taxonomy_access_list_state()
 */
function taxonomy_access_enable_list() {
  $this->_taxonomy_access_list_state(TRUE);
}

/**
 * Wrapper for taxonomy_access_list_state() to disable list grant filtering.
 *
 * @see _taxonomy_access_list_state()
 */
function taxonomy_access_disable_list() {
  $this->_taxonomy_access_list_state(FALSE);
}

/**
 * Wrapper for taxonomy_access_list_state() to check list grant filtering.
 *
 * @see _taxonomy_access_list_state()
 */
function taxonomy_access_list_enabled() {
  return $this->_taxonomy_access_list_state();
}

/**
 * Retrieve terms that the current user may list.
 *
 * @return array|true
 *   An array of term IDs, or TRUE if the user may list all terms.
 *
 * @see _taxonomy_access_user_term_grants()
 */
function taxonomy_access_user_list_terms() {
  // Cache the terms the current user can list.
  static $terms = NULL;
  if (is_null($terms)) {
    $terms = $this->_taxonomy_access_user_term_grants(FALSE);
  }
  return $terms;
}

/**
 * Retrieve terms that the current user may create or list.
 *
 * @param bool $create
 *   (optional) Whether to fetch grants for create (TRUE) or list (FALSE).
 *   Defaults to FALSE.
 * @param array $vids
 *   (optional) An array of vids to limit the query.  Defaults to array().
 * @param object|null $account
 *   (optional) The account for which to retrieve grants.  If no account is
 *   passed, the current user will be used.  Defaults to NULL.
 *
 * @return array|true
 *   An array of term IDs, or TRUE if the user has the grant for all terms.
 */
function _taxonomy_access_user_term_grants($create = FALSE, array $vids = array(), $account = NULL) {
  $grant_type = $create ? 'create' : 'list';
  $grant_field_name = 'grant_' . $grant_type;

  // If no account was passed, default to current user.
  if (is_null($account)) {
    $user = \Drupal::currentUser();
    $account = $user;
  }

  // If the user can administer taxonomy, return TRUE for a global grant.

// Check for permission
;  
  if ($account->hasPermission('administer taxonomy')) {
    return TRUE;
  }

  // Build a term grant query.
  $query = $this->_taxonomy_access_grant_query(array($grant_type));

  // Select term grants for the user's roles.
  $query
    ->fields('td', array('tid'))
    ->groupBy('td.tid')
    ->condition('tadg.rid', array_keys($account->roles), 'IN')
    ;

  // Filter by the indicated vids, if any.
  if (!empty($vids)) {
    $query
      ->fields('td', array('vid'))
      ->condition('td.vid', $vids, 'IN')
      ;
  }

  // Fetch term IDs.
  $r = $query->execute()->fetchAll();
  $tids = array();

  // If there are results, initialize a flag to test whether the user
  // has the grant for all terms.
  $grants_for_all_terms = empty($r) ? FALSE : TRUE;

  foreach ($r as $record) {
    // If the user has the grant, add the term to the array.
    if ($record->$grant_field_name) {
      $tids[] = $record->tid;
    }
    // Otherwise, flag that the user does not have the grant for all terms.
    else {
      $grants_for_all_terms = FALSE;
    }
  }

  // If the user has the grant for all terms, return TRUE for a global grant.
  if ($grants_for_all_terms) {
    return TRUE;
  }

  return $tids;
}

/**
 * Field options callback to generate options unfiltered by list grants.
 *
 * @param object $field
 *   The field object.
 *
 * @return array
 *   Allowed terms from taxonomy_allowed_values().
 *
 * @see taxonomy_allowed_values()
 */
function _taxonomy_access_term_options($field) {
  // Preserve the original state of the list flag.
  $flag_state = taxonomy_access_list_enabled();

  // Enforce that list grants do not filter the options list.
  taxonomy_access_disable_list();

  // Use taxonomy.module to generate the list of options.
  $options = taxonomy_allowed_values($field);

  // Restore list flag to previous state.
  if ($flag_state) {
    taxonomy_access_enable_list();
  }

  return $options;
}

/**
 * End of "defgroup tac_list".
 * @}
 */

/**
 * Form element validation handler for taxonomy autocomplete fields.
 *
 * @see taxonomy_access_autocomplete()
 * @see taxonomy_access_field_widget_taxonomy_autocomplete_form_alter()
 */
function taxonomy_access_autocomplete_validate($element, &$form_state) {
  // Enforce that list grants do not filter this or subsequent validation.
  taxonomy_access_disable_list();

  // Add create grant handling.
  module_load_include('inc', 'taxonomy_access', 'taxonomy_access.create');
  _taxonomy_access_autocomplete_validate($element, $form_state);

}

/**
 * Form element validation handler for taxonomy options fields.
 *
 * @see taxonomy_access_field_widget_form_alter()
 */
function taxonomy_access_options_validate($element, &$form_state) {
  // Enforce that list grants do not filter this or subsequent validation.
  taxonomy_access_disable_list();

  // Add create grant handling.
  module_load_include('inc', 'taxonomy_access', 'taxonomy_access.create');
  _taxonomy_access_options_validate($element, $form_state);
}


}