<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Settings;
use App\Repository\SettingsRepository;

/**
 * Single entry point for the business configuration ({@see Settings}). Returns the saved row, or a
 * transient instance with the defaults when none exists yet — so the calculators always have values
 * to work with and reading the settings never has a side effect (the row is only persisted when the
 * directora saves the form). Cached per request.
 */
final class SettingsProvider
{
    private ?Settings $cached = null;

    public function __construct(private readonly SettingsRepository $repository)
    {
    }

    /**
     * The current settings: the saved row, or a defaults-only instance if nothing is saved yet.
     *
     * @return Settings the effective settings
     */
    public function get(): Settings
    {
        return $this->cached ??= ($this->repository->findSettings() ?? new Settings());
    }

    /**
     * Drops the per-request cache so the next {@see get()} reloads from the database. Call it after
     * saving the settings within the same process (e.g. the admin form) so readers see the change.
     */
    public function invalidate(): void
    {
        $this->cached = null;
    }
}
