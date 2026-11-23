<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The ingest API's responses are plain DTOs without a URL of their own: drops the generated
 * (/.well-known/genid/...) Location and Content-Location headers API Platform adds to them.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class IngestResponseListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !str_starts_with($event->getRequest()->getPathInfo(), '/v1/')) {
            return;
        }

        $headers = $event->getResponse()->headers;
        foreach (['Location', 'Content-Location'] as $name) {
            if (str_contains((string) $headers->get($name), '/.well-known/genid/')) {
                $headers->remove($name);
            }
        }
    }
}
