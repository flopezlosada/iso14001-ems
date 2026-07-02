<?php

declare(strict_types=1);

namespace App\Tests\Help;

use App\Enum\Area;
use App\Help\HelpRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Guards the real shipped help catalogue (help/*.yaml), not a fixture: it must parse without error
 * and cover every functional module. This turns "someone added a module but forgot its help" or "a
 * topic file is malformed" into a failing test instead of a broken page in production.
 */
final class HelpCatalogueTest extends KernelTestCase
{
    private function registry(): HelpRegistry
    {
        self::bootKernel();

        return self::getContainer()->get(HelpRegistry::class);
    }

    public function testWholeCatalogueLoadsWithoutError(): void
    {
        // all() parses every file; a missing field or duplicate slug/route would throw here.
        $topics = $this->registry()->all();

        self::assertGreaterThanOrEqual(\count(Area::cases()), \count($topics));
    }

    public function testEveryModuleHasHelpForItsIndexRoute(): void
    {
        $registry = $this->registry();

        foreach (Area::cases() as $area) {
            self::assertNotNull(
                $registry->byRoute($area->indexRoute()),
                sprintf('El módulo "%s" (%s) no tiene tema de ayuda asociado.', $area->label(), $area->indexRoute()),
            );
        }
    }
}
