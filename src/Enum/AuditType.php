<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Kind of management-system audit (PC.09.0): carried out by the centre itself or by an external
 * certification body. It mirrors the audit-related origins of a non-conformity
 * ({@see NonConformityOrigin::INTERNAL_AUDIT}, {@see NonConformityOrigin::EXTERNAL_AUDIT}).
 */
enum AuditType: string
{
    case INTERNAL = 'internal';
    case EXTERNAL = 'external';

    /**
     * Human-facing type name (Spanish, the application's UI language).
     *
     * @return string the display name
     */
    public function label(): string
    {
        return match ($this) {
            self::INTERNAL => 'Interna',
            self::EXTERNAL => 'Externa',
        };
    }
}
