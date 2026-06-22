<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DirectAspectCategory;
use App\Repository\SettingsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Business configuration the directora can tune from the UI: the significance thresholds of the
 * environmental-aspect engine (PG-06.01) and the auto-intensity bounds. A single row (singleton);
 * {@see \App\Service\SettingsProvider} owns its loading and the defaults.
 *
 * These values used to live in code (config parameters and DirectAspectCategory); moving them here
 * lets them change without a deploy, which is the whole point of the settings screen.
 */
#[ORM\Entity(repositoryClass: SettingsRepository::class)]
#[ORM\Table(name: 'settings')]
#[ORM\HasLifecycleCallbacks]
class Settings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Per-category significance thresholds for direct aspects (PG-06.01 Anexo I): significant when
    // the score strictly exceeds the value. The score is the sum of three 2/4/6 criteria (max 18).
    #[ORM\Column]
    #[Assert\Range(min: 1, max: 18)]
    private int $consumptionThreshold = 12;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 18)]
    private int $emissionThreshold = 12;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 18)]
    private int $wasteThreshold = 10;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 18)]
    private int $dischargeThreshold = 8;

    /**
     * Threshold for abnormal aspects (Anexo III) and the fallback for a direct aspect with no category.
     */
    #[ORM\Column]
    #[Assert\Range(min: 1, max: 18)]
    private int $abnormalThreshold = 10;

    /**
     * Relative interannual rise above which a consumption aspect's auto-intensity is HIGH (0.10 = +10%).
     */
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 1, notInRangeMessage: 'El porcentaje debe estar entre 0 % y 100 %.')]
    private float $intensityRiseThreshold = 0.10;

    /**
     * Relative interannual drop below which a consumption aspect's auto-intensity is LOW (0.10 = -10%).
     */
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 1, notInRangeMessage: 'El porcentaje debe estar entre 0 % y 100 %.')]
    private float $intensityDropThreshold = 0.10;

    /**
     * Number of previous years averaged as the intensity baseline (1 = "vs last year").
     */
    #[ORM\Column]
    #[Assert\Range(min: 1, max: 10)]
    private int $intensityBaselineYears = 1;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Significance threshold for a direct-aspect category, the per-category value the engine compares
     * the score against (replaces the former hard-coded per-category values in DirectAspectCategory).
     *
     * @param DirectAspectCategory $category the direct-aspect category
     *
     * @return int the configured threshold for that category
     */
    public function thresholdFor(DirectAspectCategory $category): int
    {
        return match ($category) {
            DirectAspectCategory::CONSUMPTION => $this->consumptionThreshold,
            DirectAspectCategory::EMISSION => $this->emissionThreshold,
            DirectAspectCategory::WASTE => $this->wasteThreshold,
            DirectAspectCategory::DISCHARGE => $this->dischargeThreshold,
        };
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsumptionThreshold(): int
    {
        return $this->consumptionThreshold;
    }

    public function setConsumptionThreshold(int $consumptionThreshold): static
    {
        $this->consumptionThreshold = $consumptionThreshold;

        return $this;
    }

    public function getEmissionThreshold(): int
    {
        return $this->emissionThreshold;
    }

    public function setEmissionThreshold(int $emissionThreshold): static
    {
        $this->emissionThreshold = $emissionThreshold;

        return $this;
    }

    public function getWasteThreshold(): int
    {
        return $this->wasteThreshold;
    }

    public function setWasteThreshold(int $wasteThreshold): static
    {
        $this->wasteThreshold = $wasteThreshold;

        return $this;
    }

    public function getDischargeThreshold(): int
    {
        return $this->dischargeThreshold;
    }

    public function setDischargeThreshold(int $dischargeThreshold): static
    {
        $this->dischargeThreshold = $dischargeThreshold;

        return $this;
    }

    public function getAbnormalThreshold(): int
    {
        return $this->abnormalThreshold;
    }

    public function setAbnormalThreshold(int $abnormalThreshold): static
    {
        $this->abnormalThreshold = $abnormalThreshold;

        return $this;
    }

    public function getIntensityRiseThreshold(): float
    {
        return $this->intensityRiseThreshold;
    }

    public function setIntensityRiseThreshold(float $intensityRiseThreshold): static
    {
        $this->intensityRiseThreshold = $intensityRiseThreshold;

        return $this;
    }

    public function getIntensityDropThreshold(): float
    {
        return $this->intensityDropThreshold;
    }

    public function setIntensityDropThreshold(float $intensityDropThreshold): static
    {
        $this->intensityDropThreshold = $intensityDropThreshold;

        return $this;
    }

    public function getIntensityBaselineYears(): int
    {
        return $this->intensityBaselineYears;
    }

    public function setIntensityBaselineYears(int $intensityBaselineYears): static
    {
        $this->intensityBaselineYears = $intensityBaselineYears;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
