<?php

namespace Drupal\news_legacy_feeds\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class LegacyStoriesRedirectController extends ControllerBase {

  protected $cache;

  public function __construct(CacheBackendInterface $cache) {
    $this->cache = $cache;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.default')
    );
  }

  public function redirectTid(Request $request) {
    $tid = $request->attributes->get('tid');

    $cache = $this->cache->get('news_legacy_feeds.new_tid_mappings');
    if ($cache) {
      $new_tid_mappings = $cache->data;
    }
    else {
      $module_path = drupal_get_path('module', 'news_legacy_feeds');
      $mapping_file_path = "$module_path/new_tid_mappings.json";

      $new_tid_mappings = json_decode(file_get_contents($mapping_file_path), TRUE);
      $this->cache->set('news_legacy_feeds.new_tid_mappings', $new_tid_mappings);
    }

    $new_tid = isset($new_tid_mappings[$tid]) ? $new_tid_mappings[$tid] : $tid;
    $new_url = Url::fromRoute('view.news_deprecated_feeds_stories.category', ['term_node_tid_depth' => $new_tid])->toString();
    return new RedirectResponse($new_url);
  }

}
