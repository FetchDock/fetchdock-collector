<?php

namespace App\State\Ingest;

use App\Entity\Instance;
use App\Security\InstanceUser;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The instance calling the ingest API. Access control (ROLE_INSTANCE) guarantees an active one on protected paths.
 */
final readonly class CurrentInstance
{
    public function __construct(private Security $security)
    {
    }

    public function get(): Instance
    {
        $user = $this->security->getUser();

        return $user instanceof InstanceUser ? $user->instance : throw new \LogicException('No authenticated instance.');
    }
}
