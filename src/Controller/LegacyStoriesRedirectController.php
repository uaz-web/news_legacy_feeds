<?php

namespace Drupal\news_legacy_feeds\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class LegacyStoriesRedirectController.
 *
 * Handles redirection for legacy stories feed based on term ID mappings.
 */
class LegacyStoriesRedirectController extends ControllerBase {

  /**
   * The cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a LegacyStoriesRedirectController object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $this->cacheBackend = $cache_backend;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.default'),
      $container->get('module_handler')
    );
  }

  /**
   * Redirects the request based on term ID mappings.
   *
   * @param string $termIdsParam
   *   The old term IDs separated by " " ("+" in the raw request).
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response object.
   */
  public function redirectTids($termIdsParam) {
    // Plus characters in request parameters turn into spaces for some reason.
    $termIds = explode(' ', $termIdsParam);

    // Load new TID mappings from cache or JSON file.
    $cache = $this->cacheBackend->get('news_legacy_feeds.new_tid_mappings');
    if ($cache) {
      $newTidMappings = $cache->data;
    }
    else {
      $modulePath = $this->moduleHandler->getModule('news_legacy_feeds')->getPath();
      $json = file_get_contents($modulePath . '/mapping_data/new_tid_mappings.json');
      $newTidMappings = json_decode($json, TRUE);
      $this->cacheBackend->set('news_legacy_feeds.new_tid_mappings', $newTidMappings);
    }

    $newTermIds = [];
    foreach ($termIds as $termId) {
      // Replace TID if it's in the new mappings, otherwise preserve it as-is.
      $newTermIds[] = $newTidMappings[$termId] ?? $termId;
    }

    // Redirect to the new stories feed view with updated TIDs.
    $redirectUrl = Url::fromUri('internal:/feed/json/stories/id-updated/' . implode('+', $newTermIds));
    return new RedirectResponse($redirectUrl->toString());
  }

}
