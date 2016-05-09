# taxonomy_access
Drupal Taxonomy Access Control

Porting of Drupal 7 Taxonomy Access Control to Drupal 8

This is work in progress and the module is not yet ready for use.

Todo:
Hooks:
- taxonomy_access_init
- taxonomy_access_theme
- taxonomy_access_element_info
- taxonomy_access_menu
- taxonomy_access_user_role_delete
- taxonomy_access_taxonomy_vocabulary_delete
- taxonomy_access_taxonomy_term_delete
- taxonomy_access_field_info_alter
- taxonomy_access_field_attach_validate
- taxonomy_access_query_term_access_alter
- taxonomy_access_field_widget_taxonomy_autocomplete_form_alter
- taxonomy_access_field_widget_form_alter
- taxonomy_access_disable
- port taxonomy_access.create.inc
- Select all checkbox for terms in vocabulary missing
- Role id to be replaced with role names in UI.
- Admin styling
- Simpletest class WeightTest cases.
- Simpletest class CallbackCleanupTest cases.

Notes:
1 Until a replacement for node_access_acquire_grants() is found
  TAXONOMY_ACCESS_MAX_UPDATE is reduced to 0.
2 Until issue https://www.drupal.org/node/2703523 is solved
  node_access_rebuild() is followed by a call to drupal_flush_all_caches()
  in simple test.
