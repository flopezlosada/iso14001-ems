<?php

declare(strict_types=1);

namespace App\DataFixtures;

/**
 * Fixture group names, centralised so they cannot drift between fixtures.
 *
 * The split mirrors the working pattern used in the sibling project (csa-vega): a stable
 * "golden" backbone that is the seed of the eventual production database, and a volatile "demo"
 * layer of sample records meant to be created and modified freely while testing.
 *
 * GOLDEN is strictly the structural backbone that has NO ETL source: roles, test users, the
 * process/area map, the document registry and its review-alert cadences, and settings. Everything
 * the ETL imports (aspects, legal requirements, objectives, risks, operational-control items,
 * consumptions, waste, non-conformities, indicators, suppliers) is DEMO only — seeding it in
 * GOLDEN would duplicate it against the real data on a `--group=golden` + ETL load. The DAFO is
 * also DEMO (no ETL source; in production it starts empty and is filled from the UI).
 *
 * Dependencies follow the fixture: a fixture moved to DEMO takes its dependencies with it (e.g.
 * ObjectiveFixtures depends on EnvironmentalAspectFixtures, both DEMO). A GOLDEN fixture must never
 * depend on a DEMO one, or `--group=golden` would fail to resolve the missing reference.
 *
 * Loading rules (all synthetic, no real personal data — safe for git):
 * - `doctrine:fixtures:load`                → everything (GOLDEN ∪ DEMO).
 * - `doctrine:fixtures:load --group=demo`   → backbone + sample data (DEMO ⊇ GOLDEN).
 * - `doctrine:fixtures:load --group=golden` → only the production-baseline backbone.
 *
 * So a realistic local instance with real data = `--group=golden` + the ETL (app:import-real-data),
 * with no duplicates. The real centre data (PII/LOPD) is NEVER seeded here: it comes from the ETL
 * and lives under the git-ignored /fixtures/real/, loaded only on local machines.
 */
final class FixtureGroups
{
    /** Stable backbone that would seed production. Also part of {@see DEMO}. */
    public const string GOLDEN = 'golden';

    /** Sample transactional data for local testing. Includes the whole {@see GOLDEN} backbone. */
    public const string DEMO = 'demo';
}
