<?php

namespace Drupal\news_legacy_feeds\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to the response event to wrap JSON responses.
 */
class ResponseWrapperSubscriber implements EventSubscriberInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs the ResponseWrapperSubscriber object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // The priority -10 ensures this runs after most default system operations.
    return [
      KernelEvents::RESPONSE => ['onResponse', -10],
    ];
  }

  /**
   * Wraps the JSON response with additional data.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event) {
    $route_name = $this->routeMatch->getRouteName();
    // Checking if the route belongs to a view and if the view is the one we're
    // interested in.
    if (
      $route_name === 'view.news_deprecated_feeds_stories.category' ||
      $route_name === 'view.news_deprecated_feeds_stories.website'
    ) {
      $response = $event->getResponse();

      if ($response->headers->get('Content-Type') === 'application/json') {
        $data = json_decode($response->getContent(), true);
        $wrappedData = ['stories' => $data];
        $response->setContent(json_encode($wrappedData));
        $event->setResponse($response);
      }
    }
  }

}
