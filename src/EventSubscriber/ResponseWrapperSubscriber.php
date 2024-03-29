<?php

namespace Drupal\news_legacy_feeds\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ResponseWrapperSubscriber implements EventSubscriberInterface {

public static function getSubscribedEvents() {
  // Corrected: Removed the empty return statement.
  // The priority -10 ensures this runs after most default system operations.
  return [
    KernelEvents::RESPONSE => ['onResponse', -10],
  ];
}

public function onResponse(ResponseEvent $event) {
  $router = \Drupal::service('router');
  $route = $router->match($event->getRequest()->getPathInfo());
  $route_name = $route['_route'];
  // Checking if the route belongs to a view and if the view is the one we're
  // interested in.
  if ($route_name === 'view.news_deprecated_feeds_terms.categories') {
  // if ($request->attributes->get('_view_id') === 'news_deprecated_feeds_terms') {
    $response = $event->getResponse();

    if ($response->headers->get('Content-Type') === 'application/json') {
      $data = json_decode($response->getContent(), true);
      $wrappedData = ['terms' => $data];
      $response->setContent(json_encode($wrappedData));
      $event->setResponse($response);
    }
  }
  if ($route_name === 'view.news_deprecated_feeds_stories.category') {
  // if ($request->attributes->get('_view_id') === 'news_deprecated_feeds_terms') {
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
