<?php

namespace Drupal\news_legacy_feeds\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class LegacyCategoriesController extends ControllerBase {

  protected $cacheBackend;
  protected $entityTypeManager;
  protected $moduleHandler;

  public function __construct(CacheBackendInterface $cache_backend, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    $this->cacheBackend = $cache_backend;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.default'),
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
  }

  public function getTaxonomyTerms($vocabulariesParam) {
    $supportedVocabularies = ['az_news_tags', 'az_event_categories'];

    if ($vocabulariesParam === 'all') {
      $vocabularies = $supportedVocabularies;
    }
    else {
      $vocabularies = explode('+', $vocabulariesParam);
      if (array_diff($vocabularies, $supportedVocabularies)) {
        return new JsonResponse(['error' => 'Unsupported vocabulary requested'], 400);
      }
    }

    // Load old TID mappings from cache or JSON file.
    $cache = $this->cacheBackend->get('news_legacy_feeds.old_tid_mappings');
    if ($cache) {
      $oldTidMappings = $cache->data;
    }
    else {
      $modulePath = $this->moduleHandler->getModule('news_legacy_feeds')->getPath();
      $json = file_get_contents($modulePath . '/mapping_data/old_tid_mappings.json');
      $oldTidMappings = json_decode($json, TRUE);
      $this->cacheBackend->set('news_legacy_feeds.old_tid_mappings', $oldTidMappings);
    }

    $data = [];
    foreach ($vocabularies as $vocabulary) {
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vocabulary, 0, NULL, TRUE);

      foreach ($terms as $term) {
        $tid = $term->id();
        // Replace TID if it's in the old mappings.
        if (isset($oldTidMappings[$tid])) {
          $tid = $oldTidMappings[$tid];
        }

        $url = Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $tid]);
        $data[] = [
          'term' => [
            'name' => $term->getName(),
            'uuid' => $term->uuid(),
            'tid' => $tid,
            'vocabulary' => $vocabulary,
            'path-canonical' => $url->toString(TRUE)->getGeneratedUrl(),
          ]
        ];
      }
    }

    return new JsonResponse(['terms' => $data]);
  }

}
