<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ConformityResult;
use App\Repository\OperationalControlAnswerRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * The outcome of one checklist item within a monthly inspection (PG-08.01). {@see $result} is null
 * until the item is assessed. There is at most one answer per (check, item).
 */
#[ORM\Entity(repositoryClass: OperationalControlAnswerRepository::class)]
#[ORM\Table(name: 'operational_control_answer')]
#[ORM\UniqueConstraint(name: 'uniq_opcontrol_answer', columns: ['check_id', 'item_id'])]
class OperationalControlAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: OperationalControlCheck::class, inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false)]
    private OperationalControlCheck $check;

    #[ORM\ManyToOne(targetEntity: OperationalControlItem::class)]
    #[ORM\JoinColumn(nullable: false)]
    private OperationalControlItem $item;

    #[ORM\Column(length: 20, nullable: true, enumType: ConformityResult::class)]
    private ?ConformityResult $result = null;

    /**
     * Whether this item was assessed as non-conform (drives the inspection's non-conform count).
     *
     * @return bool true when the recorded result is "No conforme"
     */
    public function isNonConform(): bool
    {
        return ConformityResult::NON_CONFORME === $this->result;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCheck(): OperationalControlCheck
    {
        return $this->check;
    }

    public function setCheck(OperationalControlCheck $check): static
    {
        $this->check = $check;

        return $this;
    }

    public function getItem(): OperationalControlItem
    {
        return $this->item;
    }

    public function setItem(OperationalControlItem $item): static
    {
        $this->item = $item;

        return $this;
    }

    public function getResult(): ?ConformityResult
    {
        return $this->result;
    }

    public function setResult(?ConformityResult $result): static
    {
        $this->result = $result;

        return $this;
    }
}
