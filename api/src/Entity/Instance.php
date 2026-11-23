<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Repository\InstanceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;

/**
 * A registered fetchdock-server installation (ADR 0014). It authenticates with a secret, of which only the SHA-256 hash
 * is stored. No IP addresses, no operator details.
 */
#[ORM\Entity(repositoryClass: InstanceRepository::class)]
#[ApiResource(
    shortName: 'AdminInstance',
    operations: [
        new GetCollection(uriTemplate: '/admin/instances', description: 'ROLE_ADMIN. Registered instances, newest first.'),
        new Get(uriTemplate: '/admin/instances/{id}', description: 'ROLE_ADMIN.'),
        new Patch(uriTemplate: '/admin/instances/{id}', description: 'ROLE_ADMIN. Block an abusive instance (its ingest calls get 403) or unblock it.'),
    ],
    normalizationContext: ['groups' => ['admin_instance:read'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['admin_instance:write']],
    order: ['registeredAt' => 'DESC'],
    security: "is_granted('ROLE_ADMIN')",
)]
#[ApiFilter(BooleanFilter::class, properties: ['blocked'])]
#[ApiFilter(OrderFilter::class, properties: ['registeredAt', 'lastSeenOn'])]
class Instance
{
    /** Random (v4) UUID, never sequential */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['admin_instance:read'])]
    private Uuid $id;

    /** SHA-256 of the instance secret, hex; used for lookup */
    #[ORM\Column(length: 64, unique: true)]
    private string $secretHash;

    #[ORM\Column(length: 64)]
    #[Groups(['admin_instance:read'])]
    private string $serverVersion;

    #[ORM\Column(length: 32)]
    #[Groups(['admin_instance:read'])]
    private string $acceptedPolicyVersion;

    #[ORM\Column]
    #[Groups(['admin_instance:read'])]
    private \DateTimeImmutable $registeredAt;

    /** Day granularity only */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['admin_instance:read'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private \DateTimeImmutable $lastSeenOn;

    #[ORM\Column(nullable: true)]
    #[Groups(['admin_instance:read'])]
    private ?\DateTimeImmutable $retiredAt = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['admin_instance:read', 'admin_instance:write'])]
    private bool $blocked = false;

    /** @var Collection<int, Report> counted only (extra lazy), never serialized */
    #[ORM\OneToMany(targetEntity: Report::class, mappedBy: 'instance', fetch: 'EXTRA_LAZY')]
    private Collection $reports;

    public function __construct(string $secretHash, string $serverVersion, string $acceptedPolicyVersion, ?\DateTimeImmutable $registeredAt = null)
    {
        $this->id = Uuid::v4();
        $this->secretHash = $secretHash;
        $this->serverVersion = $serverVersion;
        $this->acceptedPolicyVersion = $acceptedPolicyVersion;
        $this->registeredAt = $registeredAt ?? new \DateTimeImmutable();
        $this->lastSeenOn = $this->registeredAt->setTime(0, 0);
        $this->reports = new ArrayCollection();
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

    #[Groups(['admin_instance:read'])]
    public function getReportCount(): int
    {
        return $this->reports->count();
    }
}
