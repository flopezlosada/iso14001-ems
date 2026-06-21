<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AlertFrequency;
use App\Repository\ScheduledAlertRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A scheduled review reminder for a {@see Document}. A cron-driven console command scans due
 * alerts and e-mails the responsible role with a deep link to the data that must be updated.
 *
 * This is the backbone of the system's value: making sure no review slips past an audit.
 */
#[ORM\Entity(repositoryClass: ScheduledAlertRepository::class)]
class ScheduledAlert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'alerts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    #[ORM\Column(enumType: AlertFrequency::class)]
    private AlertFrequency $frequency;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $nextDueDate;

    /**
     * Roles that receive this alert (routed per document, possibly several recipients, e.g.
     * maintenance and the secretary). Not always the director.
     *
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: 'scheduled_alert_recipient_role')]
    private Collection $recipientRoles;

    /**
     * Days after the due date before escalating (e.g. also notifying the director). Null
     * disables escalation. Configurable value, pending the director's decision (question P1).
     */
    #[ORM\Column(nullable: true)]
    private ?int $escalationDays = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastNotifiedAt = null;

    public function __construct()
    {
        $this->recipientRoles = new ArrayCollection();
    }

    /**
     * Whether the alert is due for notification on the given date.
     *
     * @param \DateTimeImmutable $on reference date (today)
     *
     * @return bool true if the next due date is on or before the reference date
     */
    public function isDue(\DateTimeImmutable $on): bool
    {
        return $this->nextDueDate <= $on;
    }

    /**
     * Whether this alert should trigger a notification on the given date.
     *
     * Event-driven alerts have no scheduled reminder (they fire when the event happens, not on a
     * date). A fixed-cadence alert needs notifying once it is due and has not yet been notified in
     * the current cycle — i.e. it was never notified, or last notified before the current due date.
     *
     * @param \DateTimeImmutable $on reference date (today)
     *
     * @return bool true if a reminder e-mail is owed
     */
    public function needsNotification(\DateTimeImmutable $on): bool
    {
        if (AlertFrequency::ON_EVENT === $this->frequency) {
            return false;
        }
        if (!$this->isDue($on)) {
            return false;
        }

        return null === $this->lastNotifiedAt || $this->lastNotifiedAt < $this->nextDueDate;
    }

    /**
     * Whether the alert is overdue and past its escalation window on the given date.
     *
     * @param \DateTimeImmutable $on reference date (today)
     *
     * @return bool true if escalation is enabled and the escalation window has elapsed
     */
    public function shouldEscalate(\DateTimeImmutable $on): bool
    {
        if (null === $this->escalationDays) {
            return false;
        }

        return $on >= $this->nextDueDate->modify(sprintf('+%d days', $this->escalationDays));
    }

    /**
     * Advances the next due date by one cadence interval. No-op for event-driven alerts, which
     * have no fixed cadence.
     */
    public function rollToNextCycle(): void
    {
        $months = $this->frequency->intervalMonths();
        if (null === $months) {
            return;
        }

        $this->nextDueDate = $this->nextDueDate->modify(sprintf('+%d months', $months));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function setDocument(Document $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getFrequency(): AlertFrequency
    {
        return $this->frequency;
    }

    public function setFrequency(AlertFrequency $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getNextDueDate(): \DateTimeImmutable
    {
        return $this->nextDueDate;
    }

    public function setNextDueDate(\DateTimeImmutable $nextDueDate): static
    {
        $this->nextDueDate = $nextDueDate;

        return $this;
    }

    /**
     * @return Collection<int, Role>
     */
    public function getRecipientRoles(): Collection
    {
        return $this->recipientRoles;
    }

    public function addRecipientRole(Role $role): static
    {
        if (!$this->recipientRoles->contains($role)) {
            $this->recipientRoles->add($role);
        }

        return $this;
    }

    public function removeRecipientRole(Role $role): static
    {
        $this->recipientRoles->removeElement($role);

        return $this;
    }

    public function getEscalationDays(): ?int
    {
        return $this->escalationDays;
    }

    public function setEscalationDays(?int $escalationDays): static
    {
        $this->escalationDays = $escalationDays;

        return $this;
    }

    public function getLastNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->lastNotifiedAt;
    }

    public function setLastNotifiedAt(?\DateTimeImmutable $lastNotifiedAt): static
    {
        $this->lastNotifiedAt = $lastNotifiedAt;

        return $this;
    }
}
