<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AuditStatus;
use App\Enum\AuditType;
use App\Repository\SystemAuditRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A management-system audit of the SGMA (PC.09.0, ISO 14001:2015 §9.2), internal or external. It
 * records the audit as an event — when it took place, who conducted it, its scope/objective and
 * its conclusions — plus the optional report file ("Informe de Auditoría").
 *
 * It deliberately has no findings table of its own: the non-conformities an audit raises live in
 * the non-conformity module (PC.10.0), each one linking back here through
 * {@see NonConformity::getAudit()}. This keeps a finding in a single place (no double entry) while
 * still making "the non-conformities of this audit" traceable.
 *
 * Audits are filed by the calendar {@see $year} they belong to (e.g. the "2025" internal audit
 * programme), matching how the centre archives them.
 */
#[ORM\Entity(repositoryClass: SystemAuditRepository::class)]
#[ORM\Table(name: 'system_audit')]
#[ORM\HasLifecycleCallbacks]
class SystemAudit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: AuditType::class)]
    private AuditType $type;

    /**
     * Calendar year the audit belongs to (its programme/cycle year), e.g. 2025.
     */
    #[ORM\Column(name: 'audit_year')]
    #[Assert\Range(min: 2000, max: 2100)]
    private int $year;

    /**
     * Date the audit was actually conducted; null while it is only planned.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $conductedOn = null;

    /**
     * Who carried out the audit ("Auditor"): the lead auditor or the certification body. Free text
     * because external auditors are not application users.
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $auditor;

    /**
     * Scope of the audit ("Alcance"): the units, processes and period covered. Free text, optional.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $scope = null;

    /**
     * Objective of the audit ("Objetivo"), e.g. "Identificar el grado de implantación del sistema".
     * Free text, optional.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $objective = null;

    /**
     * Conclusions drawn by the audit team ("Conclusiones"). Free text, optional (filled once the
     * audit is closed); conformities and opportunities for improvement are summarised here rather
     * than in a structured table.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conclusions = null;

    /**
     * Storage-relative path of the uploaded audit report ("Informe de Auditoría"), or null. Managed
     * through {@see \App\Service\FileUploader}; the original client name is kept in
     * {@see $reportOriginalName} for download.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reportPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reportOriginalName = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    /**
     * Refreshes the update timestamp on every persist/update.
     */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Whether the audit has a report file attached.
     *
     * @return bool true if a report is stored
     */
    public function hasReport(): bool
    {
        return null !== $this->reportPath;
    }

    /**
     * The audit's lifecycle state, derived from its own data: planned while it has no conduction
     * date, conducted once it does, and closed once its conclusions are written. Drives the
     * semantic-coloured status badge (there is no stored status column, see {@see AuditStatus}).
     *
     * @return AuditStatus the current state
     */
    public function status(): AuditStatus
    {
        if (null === $this->conductedOn) {
            return AuditStatus::PLANNED;
        }

        return null !== $this->conclusions && '' !== trim($this->conclusions)
            ? AuditStatus::CLOSED
            : AuditStatus::CONDUCTED;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): AuditType
    {
        return $this->type;
    }

    public function setType(AuditType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getConductedOn(): ?\DateTimeImmutable
    {
        return $this->conductedOn;
    }

    public function setConductedOn(?\DateTimeImmutable $conductedOn): static
    {
        $this->conductedOn = $conductedOn;

        return $this;
    }

    public function getAuditor(): string
    {
        return $this->auditor;
    }

    public function setAuditor(string $auditor): static
    {
        $this->auditor = $auditor;

        return $this;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function setScope(?string $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function getObjective(): ?string
    {
        return $this->objective;
    }

    public function setObjective(?string $objective): static
    {
        $this->objective = $objective;

        return $this;
    }

    public function getConclusions(): ?string
    {
        return $this->conclusions;
    }

    public function setConclusions(?string $conclusions): static
    {
        $this->conclusions = $conclusions;

        return $this;
    }

    public function getReportPath(): ?string
    {
        return $this->reportPath;
    }

    public function setReportPath(?string $reportPath): static
    {
        $this->reportPath = $reportPath;

        return $this;
    }

    public function getReportOriginalName(): ?string
    {
        return $this->reportOriginalName;
    }

    public function setReportOriginalName(?string $reportOriginalName): static
    {
        $this->reportOriginalName = $reportOriginalName;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
