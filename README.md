# taxonomy_access
Drupal Taxonomy Access Control

Porting of Drupal 7 Taxonomy Access Control to Drupal 8

This is work in progress and the module is not yet ready for use.

Todo:
- Simpletest class ConfigTest cases.
- port taxonomy_access.create.inc
- Select all checkbox for terms in vocabulary missing
- Admin styling
- Port required hooks etc in src/TaxonomyAccessService.php.
- Simpletest class WeightTest cases.
- Simpletest class CallbackCleanupTest cases.
- Delete unused hooks etc in src/TaxonomyAccessService.php.
- Add visibility for class methods.
- Check for unused code.

Notes:
1 Until a replacement for node_access_acquire_grants() is found
  TAXONOMY_ACCESS_MAX_UPDATE is reduced to 0.
  Instead node_access_rebuild() is called directly.
2 Until issue https://www.drupal.org/node/2703523 is solved
  node_access_rebuild() is followed by a call to drupal_flush_all_caches().
