<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class WellKnownController extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'app.version')]
        private readonly string $appVersion,
    ) {
    }

    /**
     * Public: the running version, checked by the deploy workflow after a redeploy.
     */
    #[Route('/.well-known/fetchdock-collector', name: 'app_well_known', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json(['version' => $this->appVersion]);
    }
}
