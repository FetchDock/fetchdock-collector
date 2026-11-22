<?php

namespace App\Mercure;

use Symfony\Component\Mercure\HubInterface;

/**
 * Mercure topics of the /admin/... resources, for maintainers.
 *
 * The API Platform admin subscribes to `new URL(record IRI, topicUrl)`, i.e. absolute URLs. Topics are therefore absolute
 * too, based on the origin of the hub's public URL: the PWA gets the same origin from the subscription token response
 * (hubUrl) and uses it as its topicUrl, so both sides agree even where no request is available (the worker).
 */
final readonly class AdminTopics
{
    public function __construct(private HubInterface $hub)
    {
    }

    /**
     * Scheme, host and (non-default) port of the hub's public URL, e.g. "https://localhost".
     */
    public function base(): string
    {
        $url = parse_url($this->hub->getPublicUrl());
        $scheme = strtolower($url['scheme'] ?? 'https');
        $port = $url['port'] ?? null;
        $defaultPort = ['http' => 80, 'https' => 443][$scheme] ?? null;

        return $scheme.'://'.strtolower($url['host'] ?? 'localhost').(null !== $port && $port !== $defaultPort ? ':'.$port : '');
    }

    /**
     * URI template matching every admin topic, for the "mercure.subscribe" claim of a maintainer's subscriber token.
     */
    public function subscribeSelector(): string
    {
        return $this->base().'/admin/{+path}';
    }

    /**
     * Topic of a report's create, update and delete events (the AdminReport resource's IRI, absolute).
     */
    public function report(string $id): string
    {
        return $this->base().'/admin/reports/'.$id;
    }
}
