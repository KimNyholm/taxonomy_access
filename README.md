# taxonomy_access
Drupal Taxonomy Access Control

Porting of Drupal 7 Taxonomy Access Control to Drupal 8

This is work in progress and the module is not yet ready for use.

Todo:
- Port required hooks etc in src/TaxonomyAccessService.php.
- port taxonomy_access.create.inc
- Delete unused hooks etc in src/TaxonomyAccessService.php.
- Select all checkbox for terms in vocabulary missing
- Role id to be replaced with role names in UI.
- Admin styling
- Simpletest class WeightTest cases.
- Simpletest class CallbackCleanupTest cases.
- Add visibility for class methods.
- Check for unused code.

Notes:
1 Until a replacement for node_access_acquire_grants() is found
  TAXONOMY_ACCESS_MAX_UPDATE is reduced to 0.
2 Until issue https://www.drupal.org/node/2703523 is solved
  node_access_rebuild() is followed by a call to drupal_flush_all_caches()
  in simple test.
