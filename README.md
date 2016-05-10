# taxonomy_access
Drupal Taxonomy Access Control

Porting of Drupal 7 Taxonomy Access Control to Drupal 8

This is work in progress and the module is not yet ready for use.

Todo:
- Role id to be replaced with role names in UI.
- hook taxonomy_access_menu, entry autocomplete
- hook taxonomy_access_field_info_alter
- hook taxonomy_access_field_attach_validate
- hook taxonomy_access_field_widget_taxonomy_autocomplete_form_alter
- hook taxonomy_access_field_widget_form_alter
- hook taxonomy_access_disable
- port taxonomy_access.create.inc
- Select all checkbox for terms in vocabulary missing
- Admin styling
- hook taxonomy_access_theme
- hook taxonomy_access_element_info
- Simpletest class TaxonomyAccessWeightTest cases.
- Simpletest class TaxonomyAccessCallbackCleanupTest cases.

Notes:
1 Until a replacement for node_access_acquire_grants() is found
  TAXONOMY_ACCESS_MAX_UPDATE is reduced to 0.
2 Until issue https://www.drupal.org/node/2703523 is solved
  node_access_rebuild() is followed by a call to drupal_flush_all_caches()
  in simpletest.
