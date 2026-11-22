<?php

namespace App\Entity;

use App\Repository\InstanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A registered fetchdock-server installation (ADR 0014). It authenticates with a secret, of which only the SHA-256 hash
 * is stored. No IP addresses, no operator details.
 */
#[ORM\Entity(repositoryClass: InstanceRepository::class)]
class Instance
{
    /** Random (v4) UUID, never sequential */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    /** SHA-256 of the instance secret, hex; used for lookup */
    #[ORM\Column(length: 64, unique: true)]
    private string $secretHash;

    #[ORM\Column(length: 64)]
    private string $serverVersion;

    #[ORM\Column(length: 32)]
    private string $acceptedPolicyVersion;

    #[ORM\Column]
    private \DateTimeImmutable $registeredAt;

    /** Day granularity only */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $lastSeenOn;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $retiredAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $blocked = false;

    public function __construct(string $secretHash, string $serverVersion, string $acceptedPolicyVersion, ?\DateTimeImmutable $registeredAt = null)
    {
        $this->id = Uuid::v4();
        $this->secretHash = $secretHash;
        $this->serverVersion = $serverVersion;
        $this->acceptedPolicyVersion = $acceptedPolicyVersion;
        $this->registeredAt = $registeredAt ?? new \DateTimeImmutable();
        $this->lastSeenOn = $this->registeredAt->setTime(0, 0);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSecretHash(): string
    {
        return $this->secretHash;
    }

    public function getServerVersion(): string
    {
        return $this->serverVersion;
    }

    public function setServerVersion(string $serverVersion): static
    {
        $this->serverVersion = $serverVersion;

        return $this;
    }

    public function getAcceptedPolicyVersion(): string
    {
        return $this->acceptedPolicyVersion;
    }

    public function getRegisteredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function getLastSeenOn(): \DateTimeImmutable
    {
        return $this->lastSeenOn;
    }

    public function setLastSeenOn(\DateTimeImmutable $lastSeenOn): static
    {
        $this->lastSeenOn = $lastSeenOn->setTime(0, 0);

        return $this;
    }

    public function getRetiredAt(): ?\DateTimeImmutable
    {
        return $this->retiredAt;
    }

    public function setRetiredAt(?\DateTimeImmutable $retiredAt): static
    {
        $this->retiredAt = $retiredAt;

        return $this;
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function setBlocked(bool $blocked): static
    {
        $this->blocked = $blocked;

        return $this;
    }
}
