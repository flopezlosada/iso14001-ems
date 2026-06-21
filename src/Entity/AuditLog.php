<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An append-only record of a single application event (login, data change, approval, alert
 * sent, …). It is the system's tamper-evident activity trail (non-repudiation, ISO 14001 7.5).
 *
 * Entries are immutable: they are created once and never updated or deleted through the app.
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(name: 'idx_audit_occurred_at', columns: ['occurred_at'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    /**
     * Who performed the action (user identifier / e-mail), or null for system/anonymous events.
     */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $actor;

    /**
     * Machine-readable event name, e.g. "user.login", "consumption.created".
     */
    #[ORM\Column(length: 100)]
    private string $action;

    /**
     * Affected entity type and id, when the event concerns one (e.g. "ConsumptionReading", "42").
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $subjectType;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $subjectId;

    /**
     * Human-readable, Spanish summary shown in the activity view.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary;

    public function __construct(
        string $action,
        ?string $actor = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $summary = null,
    ) {
        $this->action = $action;
        $this->actor = $actor;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->summary = $summary;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getActor(): ?string
    {
        return $this->actor;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): ?string
    {
        return $this->subjectId;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }
}
