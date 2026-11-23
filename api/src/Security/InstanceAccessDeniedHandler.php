<?php

namespace App\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * 403 for blocked and retired instances on the ingest API, with the reason, so the instance's admin knows what to do.
 */
final readonly class InstanceAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(private Security $security)
    {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        $user = $this->security->getUser();
        $detail = match (true) {
            !$user instanceof InstanceUser => 'Access denied.',
            null !== $user->instance->getRetiredAt() => 'This instance is retired. Register again to forward reports.',
            $user->instance->isBlocked() => 'This instance is blocked by the collector maintainers.',
            default => 'Access denied.',
        };

        return new JsonResponse(
            ['type' => '/errors/403', 'title' => 'Forbidden', 'status' => 403, 'detail' => $detail],
            403,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
