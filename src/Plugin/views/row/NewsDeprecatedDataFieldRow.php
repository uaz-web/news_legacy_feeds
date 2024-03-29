<?php

declare(strict_types = 1);

namespace Drupal\news_legacy_feeds\Plugin\views\row;

use Drupal\rest\Plugin\views\row\DataFieldRow;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\az_news_export\AZNewsDataEmpty;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin which displays fields as raw data, this plugin is only necessary in
 * order to change the shape of the data outputted by Drupal. Specifically, this
 * plugin is used to put each row of data into an array with a key of 'term' and
 * wrap that whole thing in a key of terms.
 *
 * @ingroup views_row_plugins
 *
 * @ViewsRow(
 *   id = "news_deprecated_data_field_row",
 *   title = @Translation("Deprecated News Data Feeds Row (DO NOT USE)"),
 *   help = @Translation("Use News fields as row data."),
 *   display_types = {"data"}
 * )
 */
class NewsDeprecatedDataFieldRow extends DataFieldRow {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Utility\Token definition.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('serializer'),
      $container->get('entity_type.manager'),
      $container->get('token'),

    );

  }

  /**
   * Constructs a new NewsDeprecatedDataFieldRow object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Serializer\SerializerInterface $serializer
   *   The serializer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    $serializer,
    $entity_type_manager,
    $token
    ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->serializer = $serializer;
    $this->entityTypeManager = $entity_type_manager;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public function render($row) {
    $entity = $row->_entity;

    if ($entity instanceof Term) {
      return ['term' => $this->getTermData($entity)];
    } elseif ($entity instanceof Node) {
      return ['story' => $this->getNodeData($entity)];
    }

    return [];
  }

  /**
   * Get the data for a term.
   *
   * @param \Drupal\taxonomy\Entity\Term $term
   *   The term entity.
   *
   * @return array
   *   The term data.
   */
  protected function getTermData($term) {
    return [
      'name' => $term->getName(),
      'uuid' => $term->uuid(),
      'tid' => $term->id(),
      'vocabulary' => $term->bundle(),
      'path-canonical' => $term->toUrl()->toString(),
    ];
  }

  /**
   * Get the data for a node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node entity.
   *
   * @return array
   *   The node data.
   */
protected function getNodeData($story) {
  $image_id = $story->get('field_az_media_thumbnail_image')[0]->target_id;
  $imgData = $this->getImageData($image_id, $story);
  $output = [
    'uuid' => $story->uuid(),
    'title' => $story->label(),
  ];

  if (!($imgData instanceof AZNewsDataEmpty)) {
    $output['img-fid'] = $imgData['fid'];
    $output['img-large'] = [
      'src' => $imgData['original'],
      'alt' => $imgData['alt'],
    ];
    $output['img-thumb'] = [
      'src' => $imgData['thumbnail'],
      'alt' => $imgData['alt'],
    ];
  }

  $output['url-canonical'] = $story->toUrl()->toString();
  $output['date-of-publication'] = $this->formatDateOfPublication($story->get('field_az_published')[0]->value);

  // Convert terms to a comma-separated string
  $terms = $story->get('field_az_news_tags')->referencedEntities();
  $output['terms'] = $this->getTermsAsString($terms);

  $output['summary-med'] = $story->get('field_az_summary')[0]->value ?? "\n";
  $output['byline'] = $story->get('field_az_byline')[0]->value ?? "\n";
  // Adding byline-affiliation as it's required in your desired output
  $output['byline-affiliation'] = $story->get('field_az_byline')[0]->value ?? "\n";
  $output['body'] = $story->get('field_az_body')->value ?? "\n";

  return $output;
}

protected function formatDateOfPublication($dateString) {
  // Assuming your desired date format is somewhat specific,
  // you might need a custom formatting approach
  $date = new \DateTime($dateString);
  // Adjust the format as per your requirements
  return $date->format('l midnight');
}
protected function getTermsAsString($terms) {
  $termNames = [];
  foreach ($terms as $term) {
    $termNames[] = $term->getName();
  }
  return implode(', ', $termNames);
}


  protected function getImageData($value, $entity) {

      $item = [];
      if (!empty($value)) {
        $media = $this->entityTypeManager->getStorage('media')->load($value);
        if (!empty($media) && $media->access('view') && $media->hasField('field_media_az_image')) {
          if (!empty($media->field_media_az_image->entity)) {
            /** @var \Drupal\file\FileInterface $image */
            $image = $media->field_media_az_image->entity;
            $item['fid'] = $image->id();
            $item['uuid'] = $image->uuid();
            $item['original'] = $image->createFileUrl(FALSE);
            $uri = $image->getFileUri();
            $styles = [
              'thumbnail' => 'az_enterprise_thumbnail',
              'thumbnail_small' => 'az_enterprise_thumbnail_small',
            ];
            foreach ($styles as $key => $style_id) {
              $image_style = ImageStyle::load($style_id);
              if (!empty($image_style)) {
                $item[$key] = $image_style->buildUrl($uri);
              }
            }
            if (!empty($media->field_media_az_image->alt)) {
              $item['alt'] = $media->field_media_az_image->alt;
            }
          }
        }
      }
      // Avoid returning an empty array.
      if (empty($item)) {
        $item = new AZNewsDataEmpty();
      }
      return $item;
  }

}
