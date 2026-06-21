<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

/**
 * Base class for sample transactional fixtures (consumptions, waste, non-conformities…) that are
 * meant to be created and modified freely while testing. They belong only to
 * {@see FixtureGroups::DEMO}; the production baseline ({@see FixtureGroups::GOLDEN}) excludes them.
 */
abstract class AbstractDemoFixture extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return [FixtureGroups::DEMO];
    }
}
