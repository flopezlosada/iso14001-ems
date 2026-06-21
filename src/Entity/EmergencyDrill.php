<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmergencyDrillRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single emergency drill report (record RG-08.02.01 of procedure PG-08.02, "Preparación y
 * respuesta ante emergencias"): one record per drill carried out (e.g. fire, boiler fuel spill,
 * evacuation), with what was done and the conclusions drawn.
 *
 * Event-driven, like the waste register: a handful of drills are run per school year, so they are
 * listed chronologically rather than grouped by year.
 */
#[ORM\Entity(repositoryClass: EmergencyDrillRepository::class)]
#[ORM\Table(name: 'emergency_drill')]
#[ORM\Index(name: 'idx_emergency_drill_date', columns: ['drill_date'])]
#[ORM\HasLifecycleCallbacks]
class EmergencyDrill
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Date the drill was carried out ("Fecha").
     */
    #[ORM\Column(name: 'drill_date', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $drillDate;

    /**
     * Simulated emergency type ("Tipo de emergencia simulada"), free text because the real reports
     * word it differently (e.g. "Simulacro incendio", "Derrame combustible caldera", "Evacuación").
     */
    #[ORM\Column(name: 'emergency_type', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $emergencyType;

    /**
     * Where the drill took place ("Lugar").
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $location;

    /**
     * Who took part ("Participantes").
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $participants;

    /**
     * Steps followed during the drill ("Procedimiento de actuación").
     */
    #[ORM\Column(name: 'action_procedure', type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $actionProcedure;

    /**
     * Conclusions and observations ("Conclusiones y observaciones"), including the outcome.
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $conclusions;

    /**
     * Who wrote the report ("Informe realizado por", usually the SGMA manager). Optional.
     */
    #[ORM\Column(name: 'reported_by', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $reportedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDrillDate(): \DateTimeImmutable
    {
        return $this->drillDate;
    }

    public function setDrillDate(\DateTimeImmutable $drillDate): static
    {
        $this->drillDate = $drillDate;

        return $this;
    }

    public function getEmergencyType(): string
    {
        return $this->emergencyType;
    }

    public function setEmergencyType(string $emergencyType): static
    {
        $this->emergencyType = $emergencyType;

        return $this;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getParticipants(): string
    {
        return $this->participants;
    }

    public function setParticipants(string $participants): static
    {
        $this->participants = $participants;

        return $this;
    }

    public function getActionProcedure(): string
    {
        return $this->actionProcedure;
    }

    public function setActionProcedure(string $actionProcedure): static
    {
        $this->actionProcedure = $actionProcedure;

        return $this;
    }

    public function getConclusions(): string
    {
        return $this->conclusions;
    }

    public function setConclusions(string $conclusions): static
    {
        $this->conclusions = $conclusions;

        return $this;
    }

    public function getReportedBy(): ?string
    {
        return $this->reportedBy;
    }

    public function setReportedBy(?string $reportedBy): static
    {
        $this->reportedBy = $reportedBy;

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
