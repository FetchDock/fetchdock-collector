<?php

namespace App\Controller;

use App\Mercure\AdminTopics;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Issues short-lived Mercure subscriber tokens to maintainers: subscribe-only, for the admin topics (AdminTopics).
 * The PWA presents it to the hub as the "mercureAuthorization" cookie or an "Authorization: Bearer" header.
 */
final class SubscriptionTokenController extends AbstractController
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly AdminTopics $adminTopics,
        #[Autowire(param: 'app.mercure.subscription_token_ttl')]
        private readonly int $tokenTtl,
    ) {
    }

    #[Route('/users/me/subscription-token', name: 'app_users_me_subscription_token', methods: ['POST'])]
    #[IsGranted('ROLE_MAINTAINER')]
    public function __invoke(): JsonResponse
    {
        $factory = $this->hub->getFactory() ?? throw new \LogicException('The Mercure hub has no token factory configured.');
        $topics = [$this->adminTopics->subscribeSelector()];
        $expiresAt = new \DateTimeImmutable("+{$this->tokenTtl} seconds");

        // null for publish adds no publish claim at all
        $token = $factory->create($topics, null, ['exp' => $expiresAt]);

        $response = new JsonResponse([
            'hubUrl' => $this->hub->getPublicUrl(),
            'token' => $token,
            'topics' => $topics,
            'expiresAt' => $expiresAt->format(\DATE_ATOM),
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
