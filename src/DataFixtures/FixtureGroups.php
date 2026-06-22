<?php

declare(strict_types=1);

namespace App\DataFixtures;

/**
 * Fixture group names, centralised so they cannot drift between fixtures.
 *
 * The split mirrors the working pattern used in the sibling project (csa-vega): a stable
 * "golden" backbone that is the seed of the eventual production database (roles, the document
 * registry, the aspect/legal catalogs — the structural work the centre keeps by hand), and a
 * volatile "demo" layer of sample transactional records (consumptions, waste, non-conformities…)
 * meant to be created and modified freely while testing.
 *
 * Loading rules (all synthetic, no real personal data — safe for git):
 * - `doctrine:fixtures:load`                → everything (GOLDEN ∪ DEMO).
 * - `doctrine:fixtures:load --group=demo`   → backbone + sample data (DEMO ⊇ GOLDEN).
 * - `doctrine:fixtures:load --group=golden` → only the production-baseline backbone.
 *
 * The real centre data (PII/LOPD) is NEVER seeded here: it will come from the future
 * ETL and live under the git-ignored /fixtures/real/, loaded only on local machines.
 */
final class FixtureGroups
{
    /** Stable backbone that would seed production. Also part of {@see DEMO}. */
    public const string GOLDEN = 'golden';

    /** Sample transactional data for local testing. Includes the whole {@see GOLDEN} backbone. */
    public const string DEMO = 'demo';
}
