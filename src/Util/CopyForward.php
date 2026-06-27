<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Shared building block for the "copy the previous period as an editable draft" feature of the
 * annual qualitative registers (interested parties F.04.0, objectives F.07.01…): given the previous
 * period's rows and the destination period's existing rows, it keeps only the candidates that are
 * not present yet, so cloning never duplicates and is safe to run with the destination empty or
 * half-filled.
 *
 * It captures only the dedup decision (the genuinely shared logic). Persisting, reference assignment
 * and auditing stay in each controller because they differ per register.
 */
final class CopyForward
{
    /**
     * Filters the candidates down to those whose key is not already among the existing rows. Keys are
     * compared with strict equality, so the caller's {@param $keyOf} should normalise them (e.g.
     * trim + lower-case) when the match must be case-insensitive.
     *
     * @template T
     *
     * @param iterable<T>          $candidates the previous period's rows, considered for copying
     * @param iterable<T>          $existing   the destination period's rows already present
     * @param callable(T): string $keyOf      derives the comparison key of a row
     *
     * @return list<T> the candidates missing from the destination, in their original order
     */
    public static function missing(iterable $candidates, iterable $existing, callable $keyOf): array
    {
        $existingKeys = [];
        foreach ($existing as $row) {
            $existingKeys[$keyOf($row)] = true;
        }

        $missing = [];
        foreach ($candidates as $candidate) {
            if (!isset($existingKeys[$keyOf($candidate)])) {
                $missing[] = $candidate;
            }
        }

        return $missing;
    }
}
