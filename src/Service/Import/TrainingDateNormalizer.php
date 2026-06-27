<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Turns the free-text dates of the training plan (form F.03.0) into a calendar date, applying the
 * normalization convention agreed with the centre. The F.03.0 sheet records execution dates as
 * loose human text ("octubre 2023", "23 al 27/10/23", "A LA SEMANA DE SU INCORPORACIÓN"), so this
 * is where that text becomes a {@see \DateTimeImmutable} — or {@see null} when it cannot be
 * normalized, in which case the importer sends the row to quarantine rather than inventing a date.
 *
 * Recognized shapes (everything else yields null):
 *   - ISO date "2023-10-30" (already produced by the ETL from an Excel serial)
 *   - full date "30/10/2023", "30.10.2023", "30-10-23" (day/month/year, 2- or 4-digit year)
 *   - month name + year "octubre 2023" -> first day of that month
 *   - day range "23 al 27/10/23" -> start day of the range
 *
 * Two-digit years are read as 20YY (all real data lies in 2023-2026). Dates without an explicit
 * year ("oct.", "30 Abril") are deliberately NOT inferred from context: they return null and the
 * row goes to quarantine, so the centre reintroduces them by hand instead of the importer guessing.
 */
final class TrainingDateNormalizer
{
    /**
     * Spanish month names (and common variants/abbreviations) to their 1-based number.
     */
    private const MONTHS = [
        'enero' => 1, 'ene' => 1,
        'febrero' => 2, 'feb' => 2,
        'marzo' => 3, 'mar' => 3,
        'abril' => 4, 'abr' => 4,
        'mayo' => 5, 'may' => 5,
        'junio' => 6, 'jun' => 6,
        'julio' => 7, 'jul' => 7,
        'agosto' => 8, 'ago' => 8,
        'septiembre' => 9, 'setiembre' => 9, 'sept' => 9, 'sep' => 9,
        'octubre' => 10, 'oct' => 10,
        'noviembre' => 11, 'nov' => 11,
        'diciembre' => 12, 'dic' => 12,
    ];

    /**
     * Normalizes a raw date cell into a calendar date, or null when it cannot be normalized.
     *
     * @param string $raw the verbatim cell text emitted by the ETL
     *
     * @return \DateTimeImmutable|null the normalized date, or null for blank/non-normalizable text
     */
    public function normalize(string $raw): ?\DateTimeImmutable
    {
        $value = trim($raw);
        if ('' === $value) {
            return null;
        }

        return $this->fromIso($value)
            ?? $this->fromRange($value)
            ?? $this->fromNumericDate($value)
            ?? $this->fromMonthAndYear($value);
    }

    /**
     * Parses an ISO date "YYYY-MM-DD", the shape the ETL emits for Excel serials.
     */
    private function fromIso(string $value): ?\DateTimeImmutable
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return null;
        }

        return $this->build((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    /**
     * Parses a "d al D/M/YY" range, keeping the START day (e.g. "23 al 27/10/23" -> 2023-10-23).
     */
    private function fromRange(string $value): ?\DateTimeImmutable
    {
        if (!preg_match('#^(\d{1,2})\s*al\s*\d{1,2}[/.\-](\d{1,2})[/.\-](\d{2,4})$#i', $value, $m)) {
            return null;
        }

        return $this->build($this->fullYear((int) $m[3]), (int) $m[2], (int) $m[1]);
    }

    /**
     * Parses a numeric "D/M/Y" date with any of the "/", "." or "-" separators.
     */
    private function fromNumericDate(string $value): ?\DateTimeImmutable
    {
        if (!preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2,4})$#', $value, $m)) {
            return null;
        }

        return $this->build($this->fullYear((int) $m[3]), (int) $m[2], (int) $m[1]);
    }

    /**
     * Parses a "month-name year" cell to the first day of that month (e.g. "octubre 2023").
     */
    private function fromMonthAndYear(string $value): ?\DateTimeImmutable
    {
        if (!preg_match('/^([A-Za-zÁÉÍÓÚáéíóúñ]+)\.?\s+(\d{4})$/u', $value, $m)) {
            return null;
        }

        $month = self::MONTHS[$this->fold($m[1])] ?? null;
        if (null === $month) {
            return null;
        }

        return $this->build((int) $m[2], $month, 1);
    }

    /**
     * Builds a date from its parts, returning null when they do not form a real calendar date
     * (e.g. day 31 of a 30-day month), so a malformed cell never becomes a silently wrong date.
     */
    private function build(int $year, int $month, int $day): ?\DateTimeImmutable
    {
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return (new \DateTimeImmutable())
            ->setDate($year, $month, $day)
            ->setTime(0, 0);
    }

    /**
     * Expands a 2-digit year to 20YY, leaving a 4-digit year untouched.
     */
    private function fullYear(int $year): int
    {
        return $year < 100 ? 2000 + $year : $year;
    }

    /**
     * Lower-cases a month token and strips Spanish accents so "Octubre"/"octubre" both match.
     */
    private function fold(string $token): string
    {
        $lower = mb_strtolower($token, 'UTF-8');

        return strtr($lower, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
    }
}
