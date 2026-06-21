# iso14001-ems

Environmental management system (EMS) for **ISO 14001** compliance.

It replaces a folder-and-spreadsheet workflow with a single application that: sends review
reminders by e-mail to each document's owner, generates the official PDF documents from form
data (no manual rewriting), keeps a full version history and approval trail, and offers a
read-only audit view. The design follows ISO 14001:2015 clause 7.5 (control of documented
information).

> Any real operational documentation (which may contain personal data) is **not** part of
> this repository and must never be committed. This repository is public.

## Stack

- PHP 8.3 / Symfony 7.4
- Doctrine ORM + Migrations (MySQL 8)
- Twig (server-rendered UI)
- Local environment: DDEV (Docker)

Chosen to run on low-cost classic shared hosting with minimal operational burden.

## Local development

Requires [Docker](https://www.docker.com/) and [DDEV](https://ddev.readthedocs.io/).

```bash
ddev start
ddev composer install
ddev exec php bin/console doctrine:migrations:migrate --no-interaction
```

The app reads `DATABASE_URL` from `.env`, defaulting to DDEV's internal MySQL service.
Override it in `.env.local` (git-ignored) for any other environment.

## Test data (seeding)

Sample data lives in `src/DataFixtures` and is split in two groups:

- **golden** — the stable backbone that would seed the eventual production database: roles and
  permissions, the document registry (F.01), the environmental-aspect catalog and the
  risks/opportunities register (both scored by the real `AspectSignificanceCalculator` /
  `RiskScoreCalculator`, never by hand), the process-area map, legal requirements and objectives.
- **demo** — sample transactional records to exercise the features (consumptions across
  2024-2026, waste, suppliers, training, drills, indicators with measurements, non-conformities,
  alerts, audit trail). The demo group **includes** the golden backbone it depends on.

```bash
ddev exec php bin/console doctrine:fixtures:load                  # everything (golden + demo)
ddev exec php bin/console doctrine:fixtures:load --group=demo     # same as above
ddev exec php bin/console doctrine:fixtures:load --group=golden   # only the production baseline
```

Log in locally with the seeded admin (`tester@example.test`): request a magic link and open it
from Mailpit (`ddev launch -m`).

> **All fixtures are synthetic** — names, e-mails (`@example.test`) and figures are invented,
> modelled on the centre's real document structure but containing **no personal data**, so they
> are safe to commit. The real IES La Cabrera data (PII/LOPD) is never seeded here: it will come
> from a future ETL and live under the git-ignored `/fixtures/real/`, loaded only on local
> machines.

## Quality gates

```bash
ddev exec php bin/phpunit            # tests
ddev exec vendor/bin/phpstan analyse # static analysis (level 6)
```

Both run in CI on every push and PR (`.github/workflows/tests.yml`), alongside secret
scanning with gitleaks (`.github/workflows/gitleaks.yml`).

## Domain model (current core)

The stable core, independent of pending requirement decisions:

- **Document** — a logical document. Its internal id is the identity; the ISO code
  (`F.XX.Y`, `RG-...`) is a mutable attribute (with inherited aliases), because the real codes
  are inconsistent.
- **DocumentVersion** — a revision (numbering from 0); superseded revisions become `OBSOLETE`,
  never deleted.
- **ApprovalEvent** — a traceable approval of a specific version with an integrity hash
  (non-repudiation) and an optional attached signed PDF (FNMT/DNIe certificate).
- **ScheduledAlert** — a review reminder routed to the responsible role, with an optional
  escalation window.

Calculation modules (environmental aspects, risks, indicators) and the role/process catalogs
are intentionally not built yet: they depend on decisions still being confirmed with the
centre.
