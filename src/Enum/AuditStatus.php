<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Lifecycle state of a management-system audit (PC.09.0, ISO 14001:2015 §9.2), derived from the
 * {@see \App\Entity\SystemAudit}'s own data rather than stored: an audit is first only planned,
 * then conducted, and finally closed once its conclusions are written.
 *
 * It is deliberately not a stored field with its own transitions: the audit's dates and conclusions
 * already tell the whole story, so a separate status column could only drift from them.
 */
enum AuditStatus: string
{
    case PLANNED = 'planned';
    case CONDUCTED = 'conducted';
    case CLOSED = 'closed';

    /**
     * Human-facing label (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Planificada',
            self::CONDUCTED => 'Realizada',
            self::CLOSED => 'Cerrada',
        };
    }

    /**
     * CSS badge class on the shared semantic scale: a merely planned audit is neutral (grey), one
     * conducted but without conclusions is work in progress (amber), a closed one is done (green).
     *
     * @return string the badge modifier class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::PLANNED => 'badge--draft',
            self::CONDUCTED => 'badge--warning',
            self::CLOSED => 'badge--success',
        };
    }
}
