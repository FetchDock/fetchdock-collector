<?php

namespace App\Entity;

use App\Repository\ReportCommentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A comment an instance's admin added to an already forwarded report (UpstreamFailureReportComment).
 */
#[ORM\Entity(repositoryClass: ReportCommentRepository::class)]
class ReportComment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Report $report;

    #[ORM\Column(type: Types::TEXT)]
    private string $comment;

    /** When the comment was written on the instance */
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    public function __construct(Report $report, string $comment, \DateTimeImmutable $createdAt, ?\DateTimeImmutable $receivedAt = null)
    {
        $this->id = Uuid::v4();
        $this->report = $report;
        $this->comment = $comment;
        $this->createdAt = $createdAt;
        $this->receivedAt = $receivedAt ?? new \DateTimeImmutable();
        $report->addComment($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getReport(): Report
    {
        return $this->report;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }
}
