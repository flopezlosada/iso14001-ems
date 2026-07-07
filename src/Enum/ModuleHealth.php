<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The at-a-glance health of a functional {@see Area} for the current period, shown on the system
 * overview so the user sees "what is done and what is missing" without opening each module.
 *
 * It follows the application's single semantic colour scale (neutral → attention → grave, plus ok):
 * a module with overdue work is {@see GRAVE} (red), one with work due soon or a pending workflow
 * step is {@see ATTENTION} (amber), one with everything under control is {@see OK} (green), and one
 * with nothing to track this period is {@see NEUTRAL} (muted).
 */
enum ModuleHealth: string
{
    case GRAVE = 'grave';
    case ATTENTION = 'attention';
    case OK = 'ok';
    case NEUTRAL = 'neutral';

    /**
     * Relative severity, so the worst of two signals (e.g. the obligation semaphore and the module's
     * workflow) wins when they disagree.
     *
     * @return int higher means worse (more in need of attention)
     */
    public function severity(): int
    {
        return match ($this) {
            self::GRAVE => 3,
            self::ATTENTION => 2,
            self::OK => 1,
            self::NEUTRAL => 0,
        };
    }

    /**
     * Whether this health is worse than (outranks) the given one.
     *
     * @param self $other the health to compare against
     *
     * @return bool true if this one demands more attention
     */
    public function isWorseThan(self $other): bool
    {
        return $this->severity() > $other->severity();
    }

    /**
     * CSS badge class on the shared semantic scale, mirroring the rest of the UI (danger/warning/
     * success), with a plain muted badge for the neutral "nothing to track" state.
     *
     * @return string the badge modifier class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::GRAVE => 'badge--danger',
            self::ATTENTION => 'badge--warning',
            self::OK => 'badge--success',
            self::NEUTRAL => 'badge--muted',
        };
    }
}
