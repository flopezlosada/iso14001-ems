<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Backfills linked_area for the four obligations that have a data module but were seeded without it,
 * so the cockpit and the calendar deep-link them to their module ("Cumplimentar") instead of only
 * to their register entry. Mirrors the fixtures change; only fills rows still NULL (idempotent).
 */
final class Version20260628150000 extends AbstractMigration
{
    /** Obligation code => area value to set when linked_area is still NULL. */
    private const LINKS = [
        'F.04.0' => 'interested_party',
        'RG-07.04.00' => 'communication',
        'RG-09.03.01' => 'management_review',
        'F.15.0' => 'system_audit',
    ];

    public function getDescription(): string
    {
        return 'document: enlazar al módulo las obligaciones F.04.0, RG-07.04.00, RG-09.03.01 y F.15.0';
    }

    public function up(Schema $schema): void
    {
        // Values are fixed constants (no user input), so literal SQL is safe and avoids any
        // named-parameter ambiguity in addSql().
        foreach (self::LINKS as $code => $area) {
            $this->addSql(sprintf(
                "UPDATE document SET linked_area = '%s' WHERE code = '%s' AND linked_area IS NULL",
                $area,
                $code,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::LINKS as $code => $area) {
            $this->addSql(sprintf(
                "UPDATE document SET linked_area = NULL WHERE code = '%s' AND linked_area = '%s'",
                $code,
                $area,
            ));
        }
    }
}
