<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CommunicationCategory;
use App\Enum\CommunicationChannel;
use App\Enum\CommunicationScope;
use App\Repository\CommunicationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single environmental communication recorded in the register RG-07.04.00 "Comunicaciones
 * externas e internas" (procedure PC.04.0, ISO 14001:2015 §7.4): an internal or external message,
 * a query, a suggestion or a complaint, dated and attributed to a sender/recipient through a
 * channel.
 *
 * Unlike the annual registers (F.04.0…), this is a flat event log: each row is one dated
 * communication, listed newest first, not grouped by review year.
 *
 * A communication may relate to an {@see InterestedParty} ({@see $interestedParty}) when it comes
 * from or concerns a known party — typically a complaint. The link is optional and severed (set to
 * NULL) rather than cascaded if that party row is deleted, so the historical log is never lost.
 *
 * Complaints ({@see CommunicationCategory::COMPLAINT}) are the communications the management review
 * cares most about (§9.3 "comunicaciones pertinentes de las partes interesadas, incluidas quejas");
 * {@see $response} records how the centre answered/closed them.
 */
#[ORM\Entity(repositoryClass: CommunicationRepository::class)]
#[ORM\Table(name: 'communication')]
#[ORM\Index(name: 'idx_communication_occurred_on', columns: ['occurred_on'])]
#[ORM\HasLifecycleCallbacks]
class Communication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * The date the communication took place ("Fecha").
     */
    #[ORM\Column(name: 'occurred_on', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $occurredOn;

    /**
     * Internal vs external communication ("INTERNA / EXTERNA").
     */
    #[ORM\Column(length: 20, enumType: CommunicationScope::class)]
    private CommunicationScope $scope;

    /**
     * The nature of the communication ("TIPO DE COMUNICACIÓN"): complaint, query, information…
     */
    #[ORM\Column(length: 20, enumType: CommunicationCategory::class)]
    private CommunicationCategory $category;

    /**
     * The medium used ("CANAL"): meeting, e-mail, notice board, website…
     */
    #[ORM\Column(length: 20, enumType: CommunicationChannel::class)]
    private CommunicationChannel $channel;

    /**
     * Short subject/summary of the message ("MENSAJE").
     */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $subject;

    /**
     * The full message, optional ("MENSAJE" detail). Free text.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    /**
     * Who issued the communication ("EMISOR"), optional free text.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $sender = null;

    /**
     * Who received the communication ("RECEPTOR"), optional free text.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $recipient = null;

    /**
     * The interested party this communication relates to, when it comes from or concerns a known
     * party (ISO 14001:2015 §7.4 / §9.3). Optional: many internal communications relate to no
     * specific party. Severed to NULL (not cascaded) when the party row is deleted.
     */
    #[ORM\ManyToOne(targetEntity: InterestedParty::class)]
    #[ORM\JoinColumn(name: 'interested_party_id', nullable: true, onDelete: 'SET NULL')]
    private ?InterestedParty $interestedParty = null;

    /**
     * How the centre answered or closed the communication, optional ("respuesta"). Especially
     * relevant for complaints, which must be followed up; can be filled in later.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $response = null;

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

    /**
     * Whether this communication is a complaint, the category the management review reports on
     * specially (§9.3).
     *
     * @return bool true if its category is {@see CommunicationCategory::COMPLAINT}
     */
    public function isComplaint(): bool
    {
        return CommunicationCategory::COMPLAINT === $this->category;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOccurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function setOccurredOn(\DateTimeImmutable $occurredOn): static
    {
        $this->occurredOn = $occurredOn;

        return $this;
    }

    public function getScope(): CommunicationScope
    {
        return $this->scope;
    }

    public function setScope(CommunicationScope $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function getCategory(): CommunicationCategory
    {
        return $this->category;
    }

    public function setCategory(CommunicationCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getChannel(): CommunicationChannel
    {
        return $this->channel;
    }

    public function setChannel(CommunicationChannel $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): static
    {
        $this->details = $details;

        return $this;
    }

    public function getSender(): ?string
    {
        return $this->sender;
    }

    public function setSender(?string $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function getRecipient(): ?string
    {
        return $this->recipient;
    }

    public function setRecipient(?string $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getInterestedParty(): ?InterestedParty
    {
        return $this->interestedParty;
    }

    public function setInterestedParty(?InterestedParty $interestedParty): static
    {
        $this->interestedParty = $interestedParty;

        return $this;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): static
    {
        $this->response = $response;

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
