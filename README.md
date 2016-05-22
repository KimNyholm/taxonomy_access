# taxonomy_access
Drupal Taxonomy Access Control

Porting of Drupal 7 Taxonomy Access Control to Drupal 8

Configuration of taxonomy access control and node access functionality is working.

Neither admin styling nor taxonomy fields and widgets are supported.

Todo:
- Field and widgets to be supported, i.e.:
    - hook taxonomy_access_field_widget_form_alter
    - hook taxonomy_access_field_info_alter
    - hook taxonomy_access_field_attach_validate
    - hook taxonomy_access_field_widget_taxonomy_autocomplete_form_alter
    - hook taxonomy_access_disable
    - hook taxonomy_access_menu, entry autocomplete
    - port taxonomy_access.create.inc
    - Simpletest class TaxonomyAccessCallbackCleanupTest cases.
- Admin styling, i.e.
    - Select all checkbox for terms in vocabulary missing
    - 'Enable/disable role' to be replaced with 'Enable/disable @name' in TaxonomyAccessAdminRole.
    - hook taxonomy_access_theme
    - hook taxonomy_access_element_info
- Simpletest class TaxonomyAccessWeightTest cases.
- Token validation in taxonomy_access_enable_role_validate

Notes:
1 Until a replacement for node_access_acquire_grants() is found
  TAXONOMY_ACCESS_MAX_UPDATE is reduced to 0.
2 Because of issue https://www.drupal.org/node/2703523 node_access_rebuild() 
  is followed by a call to drupal_flush_all_caches() in simpletest.
