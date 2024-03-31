<?php

namespace Drupal\news_legacy_feeds\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class LegacyStoriesRedirectController.
 *
 * Handles redirection for legacy stories feed based on term ID mappings.
 */
class LegacyStoriesRedirectController extends ControllerBase {

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * LegacyStoriesRedirectController constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(CacheBackendInterface $cache, ModuleHandlerInterface $module_handler) {
    $this->cache = $cache;
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
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function redirectTid(Request $request) {
    $tid = $request->attributes->get('tid');

    $cache = $this->cache->get('news_legacy_feeds.new_tid_mappings');
    if ($cache) {
      $new_tid_mappings = $cache->data;
    }
    else {
      $module_path = $this->moduleHandler->getModule('news_legacy_feeds')->getPath();
      $mapping_file_path = "$module_path/mapping_data/new_tid_mappings.json";

      $new_tid_mappings = json_decode(file_get_contents($mapping_file_path), TRUE);
      $this->cache->set('news_legacy_feeds.new_tid_mappings', $new_tid_mappings);
    }

    $new_tid = isset($new_tid_mappings[$tid]) ? $new_tid_mappings[$tid] : $tid;
    $new_url = Url::fromUri('/feed/json/stories-updated/' . $new_tid);
    return new RedirectResponse($new_url);
  }

}
