<?php /**
 * @file
 * Contains \Drupal\taxonomy_access\Controller\SandboxController.
 */

namespace Drupal\taxonomy_access\Controller;

use Drupal\taxonomy_access\TaxonomyAccessService;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default controller for the taxonomy_access module.
 */
class SandboxController extends ControllerBase {

    protected $taxonomyAccessService ;


/**
   * Class constructor.
   */
  public function __construct($taxonomyAccessService) {
    $this->taxonomyAccessService = $taxonomyAccessService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('taxonomy_access.taxonomy_access_service')
    );
  }
    
    function createVocab($machine_name) {
    $vocabulary = \Drupal\taxonomy\Entity\Vocabulary::create(
      array(
        'vid' => $machine_name,
        'machine_name' => $machine_name,
 //       'name' => 'Vocabulary '.$machine_name,
        'name' => $machine_name,
      ))->save();
    $vocabulary=\Drupal\taxonomy\Entity\Vocabulary::load($machine_name);
    return $vocabulary;
  }

  /**
   * Creates a new term in the specified vocabulary.
   *
   * @param string $machine_name
   *   A machine-safe name.
   * @param object $vocab
   *   A vocabulary object.
   * @param int|null $parent
   *   (optional) The tid of the parent term, if any.  Defaults to NULL.
   *
   * @return object
   *   The taxonomy term object.
   */
  function createTerm($taxonomyName, $vocab, $parent = 0) {
    $vid=$vocab->id();
    $term = \Drupal\taxonomy\Entity\Term::create(
        [ 'name' => $taxonomyName, 'vid' => $vid, 'parent'=>array($parent)]);
    $term-> save();
    $tid=$term->id();
    $term = \Drupal\taxonomy\Entity\Term::load($tid);
    return $term;
  }

  public /**
   * Creates a taxonomy field and adds it to the page content type.
   *
   * @param string $machine_name
   *   The machine name of the vocabulary to use.
   * @param string $widget
   *   (optional) The name of the widget to use.  Defaults to 'options_select'.
   * @param int $count
   *   (optional) The allowed number of values.  Defaults to unlimited.
   *
   * @return array
   *   Array of instance data.
   */
  function createField($vocabulary_name){
  $fieldStorage = [
       'field_name' => $vocabulary_name,
       'type' => 'entity_reference',
       'entity_type' => 'node',
       'cardinality' => \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
       'settings' => [
         'target_type' => 'taxonomy_term',
       ],
     ];
     \Drupal\field\Entity\FieldStorageConfig::create($fieldStorage)->save();
    $field=array(
        'entity_type' => 'node',
        'field_name' => $vocabulary_name,
        'bundle' => 'page',
        'settings' => array(
          'handler_settings' => array(
            'target_bundles' => array(
              $vocabulary_name => $vocabulary_name,
            ),
            'auto_create' => TRUE,
          ),
        ),
      );
    \Drupal\field\Entity\FieldConfig::create($field)->save();
    $field['bundle']='article';
//    \Drupal\field\Entity\FieldConfig::create($field)->save();
  }

  public /**
   * Creates an article with the specified terms.
   *
   * @param array $autocreate
   *   (optional) An array of term names to autocreate. Defaults to array().
   * @param array $existing
   *   (optional) An array of existing term IDs to add.
   *
   * @return object
   *   The node object.
   */
  function createArticle($name) {
    $values = [];
    $values[] = [
        'tid' => 'autocreate',
        'vid' => 'v1',
        'name' => $name,
//        'vocabulary_machine_name' => 'tags',
      ];

    // Bloody $langcodes.
//    $values = [\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED => $values];

    $settings = [
      'type' => 'article',
      'title' => $name,
//      'field_tags' => $values,
    ];
  dpm($settings, 'settings');
  $node = \Drupal\node\Entity\Node::create($settings);
  $node->save();
  return $node;
  }

  function createPage($name, $tid) {
    $v1 = array(array('tid' => $tid));
  
    // Bloody $langcodes.
    $values = [\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED => $v1];

    $node = \Drupal\node\Entity\Node::create(array(
    'type' => 'pt2',
    'title' => 'your title',
    'langcode' => 'en',
    'uid' => '1',
    'status' => 1,
    'v9zxc19' => array(31),
));

  $node->save();
  return $node;
  }


function demo($parameter=NULL) {
$vocName='v9zxc21';
  $v1=$this->createVocab($vocName);
  //$this->createField($vocName);
  //$v1t1=$this->createTerm($vocName.'1', $v1);
///  $this->createArticle('article1');
//  $this->createArticle(array('on_tag'));
  //$node=$this->createPage('page 2', 31);
//  dpm($node, 'node');
//  $this->createPage(array('on_tag'));
  $message='Hello';
  $markup= "<p>$message from the sandbox</p>";
  return array(
      '#markup' => $markup,
    );

}


function contents() {

  $markup= '<p>Hello from your sandbox.</p>';
  return array(
      '#markup' => $markup,
    );

}

}

