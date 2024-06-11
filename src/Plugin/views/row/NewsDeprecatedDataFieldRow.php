<?php

declare(strict_types = 1);

namespace Drupal\news_legacy_feeds\Plugin\views\row;

use Drupal\rest\Plugin\views\row\DataFieldRow;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\az_news_export\AZNewsDataEmpty;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Plugin which displays fields as raw data.
 *
 * This plugin is used to put each row of data into an array with a key of 'term' and
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
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The token service.
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
      $container->get('token')
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
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
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
    SerializerInterface $serializer,
    EntityTypeManagerInterface $entity_type_manager,
    Token $token
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
  protected function getTermData(Term $term) {
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
  protected function getNodeData(Node $node) {
    $image_id = $node->get('field_az_media_thumbnail_image')[0]->target_id;
    $imgData = $this->getImageData($image_id, $node);
    $output = [
      'uuid' => $node->uuid(),
      'title' => $node->label(),
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
    else {
      // Default image id to a placeholder image
      $output['img-fid'] = 3974;
    }

    $output['url-canonical'] = $node->toUrl()->setOption('absolute', TRUE)->toString();
    $output['date-of-publication'] = $this->formatDateOfPublication($node->get('field_az_published')[0]->value);

    // Convert terms to a comma-separated string
    $terms = $node->get('field_az_news_tags')->referencedEntities();
    $output['terms'] = $this->getTermsAsString($terms);

    $output['summary-med'] = $node->get('field_az_summary')[0]->value ?? "\n";
    $output['byline'] = $node->get('field_az_byline')[0]->value ?? "\n";
    // Adding byline-affiliation as it's required in your desired output
    $output['byline-affiliation'] = $node->get('field_az_byline')[0]->value ?? "\n";
    $output['body'] = $node->get('field_az_body')->value ?? "\n";

    return $output;
  }

  /**
   * Formats the date of publication.
   *
   * @param string $dateString
   *   The date string.
   *
   * @return string
   *   The formatted date.
   */
  protected function formatDateOfPublication($dateString) {
    try {
      $date = new \DateTime($dateString);
      return $date->format('Y-m-d\TH:i:sP');
    } catch (\Exception $e) {
      return "Error formatting date: " . $e->getMessage();
    }
  }

  /**
   * Converts terms to a comma-separated string.
   *
   * @param \Drupal\taxonomy\Entity\Term[] $terms
   *   An array of term entities.
   *
   * @return string
   *   The comma-separated terms.
   */
  protected function getTermsAsString(array $terms) {
    $termNames = [];
    foreach ($terms as $term) {
      $termNames[] = $term->getName();
    }
    return implode(', ', $termNames);
  }

  /**
   * Gets the image data.
   *
   * @param int $value
   *   The image ID.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array|\Drupal\az_news_export\AZNewsDataEmpty
   *   The image data or an empty data object.
   */
  protected function getImageData(int $value, $entity) {
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
            'thumbnail' => 'az_card_image',
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
