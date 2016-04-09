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



  function randomName() {
    static $nameNo=1;
    $nameNo += 1;
    return 'nameno' . $nameNo;
  }

  function createArticle($autocreate = [], $existing = []) {
    $values = [];
    foreach ($autocreate as $name) {
      $values[] = [
        'tid' => 'autocreate',
        'vid' => 1,
        'name' => $name,
        'vocabulary_machine_name' => 'tags',
      ];
    }
    foreach ($existing as $tid) {
      $values[] = [
        'tid' => $tid,
        'vid' => 1,
        'vocabulary_machine_name' => 'tags',
      ];
    }

    // Bloody $langcodes.
    $values = [\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED => $values];

    $settings = [
      'type' => 'article',
      'field_tags' => $values,
    ];

    return $this->___drupalCreateNode($settings);
  }
function createArticleWithTitle($title){
  return $this->createNode('article', $title, array('tac_tax_field' => 4));
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
    dpm($vocabulary_name, 'createField');
  $fieldStorage = [
       'field_name' => $vocabulary_name.'3',
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
       'field_name' => $vocabulary_name.'3',
       'bundle' => 'page',
       'settings' => array(
          'handler' => 'default:taxonomy_term',
          'handler_settings' => array(
            'target_bundles' => array($vocabulary_name => $vocabulary_name),
          ),
        )
      );

    \Drupal\field\Entity\FieldConfig::create($field)->save();
    $field['bundle']='article';
    \Drupal\field\Entity\FieldConfig::create($field)->save();
  }

  protected $articles = array();
  protected $pages = array();
  protected $vocabs = array();
  protected $terms = array();

  function createTerm1($name){
    $term=new \StdClass();
    $term->label = name ;
  }

  public function setUp() {
    // Add two taxonomy fields to pages.
    foreach (array('v1', 'v2') as $vocab) {
//      $this->vocabs[$vocab] = $this->createVocab($vocab);
//      $this->createField($vocab);
      $this->terms[$vocab . 't1'] =
        $this->createTerm($vocab . 't1', $vocab);
      $this->terms[$vocab . 't2'] =
        $this->createTerm($vocab . 't2', $vocab);
    }
    // Set up a variety of nodes with different term combinations.
    $this->articles['no_tags'] = $this->createArticle();
    $this->articles['one_tag'] =
      $this->createArticle(array($this->randomName()));
    $this->articles['two_tags'] =
      $this->createArticle(array($this->randomName(), $this->randomName()));

    $this->pages['no_tags'] = $this->createPage();
    foreach ($this->terms as $t1) {
      $this->pages[$t1->label()] = $this->createPage(array($t1->label()));
      foreach ($this->terms as $t2) {
        $this->pages[$t1->label() . '_' . $t2->label()] =
          $this->createPage(array($t1->label(), $t2->label()));
      }
    }
  }

  function createNode($type, $title, $tags=array()){
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $settings=array(
            'type' => $type,
            'title' => $title,
            'langcode' => $language,
            'uid' => 1,
            'status' => 1,
            'body' => array("The body text for title $title"),
      );
    foreach($tags as $fieldName => $tid){
      $settings[$fieldName] = array($tid);
    }
    $node = \Drupal\node\Entity\Node::create($settings);
    $node->save();
    return $node ;
  }

  function ___drupalCreateNode($settings){
    dpm($settings, 'settings');
  }

  /**
   * Creates a page with the specified terms.
   *
   * @param array $terms
   *   (optional) An array of term names to tag the page.  Defaults to array().
   *
   * @return object
   *   The node object.
   */
  function createPage($tags = array()) {
    $v1 = array();
    $v2 = array();

    foreach ($tags as $name) {
/*
      switch ($this->terms[$name]->id()) {
        case ($this->vocabs['v1']->id()):
          $v1[] = array('tid' => $this->terms[$name]->id());
          break;

        case ($this->vocabs['v2']->id()):
          $v2[] = array('tid' => $this->terms[$name]->id());
          break;
      }
*/
    }

    // Bloody $langcodes.
    $v1 = array(\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED => $v1);
    $v2 = array(\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED => $v2);

    $settings = array(
      'type' => 'page',
      'v1' => $v1,
      'v2' => $v2,
    );

    return $this->___drupalCreateNode($settings);
  }


 // see "modules/taxonomy/src/Tests/Migrate/d7/MigrateNodeTaxonomyTest.php

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
  function createTerm($taxonomyName, $vid, $parent = 0) {
    dpm($taxonomyTerm, "createTerm in vid=".$vid);
    $term = \Drupal\taxonomy\Entity\Term::create(
        [ 'name' => $taxonomyName, 'vid' => $vid, 'parent'=>array($parent)]);
    $term-> save();
    $tid=$term->id();
    $term = \Drupal\taxonomy\Entity\Term::load($tid);
    return $term;
  }

  public /**
   * Creates a vocabulary with a certain name.
   *
   * @param string $machine_name
   *   A machine-safe name.
   *
   * @return object
   *   The vocabulary object.
   */
  function createVocab($machine_name) {
    dpm($machine_name, "createVocab");
    $vocabulary = \Drupal\taxonomy\Entity\Vocabulary::create(
      array(
        'vid' => $machine_name,
        'machine_name' => $machine_name,
        'name' => $machine_name,
      ))->save();
    $vocabulary=\Drupal\taxonomy\Entity\Vocabulary::load($machine_name);
    return $vocabulary;
  }


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
function contents() {
  dpm('contents');
//  $vocab = $this->createVocab('my_vocab');
//  dpm($vocab, 'vocab');
//  $term=$this->createTerm('myterm 3', 'my_vocab');
//  dpm($term, 'term');
//  $node=$this->createArticleWithTitle('My article 11');
//  $tags = array('my_tax_field4' => 4);
//  $node=$this->createNode('page', 'My page 11', $tags);
//  dpm($node, 'node');
//  $this->createField('my_tax_field4', 'my_vocab');
  $this->setup();
  $header = array(t('Role'), t('Status'), t('Operations'));
  $rows = array();


  $build['role_table'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
  );

  return $build;
}

}

