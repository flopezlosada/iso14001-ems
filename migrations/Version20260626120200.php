<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Corrects the discharge (vertidos) significance threshold from the former 8 to 12, per the certified
 * register RG-06.01.01 Rev 02 (which also added the intensity criterion to discharges). The entity
 * default already changed, but that only affects fresh installs; an already-persisted settings row
 * keeps its old 8 and, with the now-inclusive ">=" rule, would misclassify a discharge scoring 8 as
 * significant. The guard makes this idempotent and leaves any value the directora set by hand untouched.
 */
final class Version20260626120200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bump persisted settings.discharge_threshold from the old 8 to the certified 12';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE settings SET discharge_threshold = 12 WHERE discharge_threshold = 8');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE settings SET discharge_threshold = 8 WHERE discharge_threshold = 12');
    }
}
