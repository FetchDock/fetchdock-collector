<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Rejects ingest API (/v1) request bodies above 32 KiB with 413, before routing and authentication. Always on.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 300)]
final class IngestBodySizeListener
{
    public const int MAX_BYTES = 32 * 1024;

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/v1/')) {
            return;
        }

        $declared = $request->headers->get('Content-Length');
        $tooLarge = null !== $declared && is_numeric($declared)
            ? (int) $declared > self::MAX_BYTES
            : \strlen($request->getContent()) > self::MAX_BYTES;
        if ($tooLarge) {
            $event->setResponse(new JsonResponse(
                ['type' => '/errors/413', 'title' => 'Content Too Large', 'status' => 413, 'detail' => \sprintf('The request body exceeds %d bytes.', self::MAX_BYTES)],
                413,
                ['Content-Type' => 'application/problem+json'],
            ));
        }
    }
}
