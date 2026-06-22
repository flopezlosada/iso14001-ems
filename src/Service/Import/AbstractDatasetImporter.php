<?php

declare(strict_types=1);

namespace App\Service\Import;

use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Shared helpers for the dataset importers: turning the validator's violations into a single
 * rejection reason, and mapping an empty CSV cell to null (the shape nullable columns expect).
 *
 * Intentionally does NOT implement {@see DatasetImporter}: the tag that registers an importer in
 * the command lives on that interface, and a tagged abstract class would be picked up by the
 * tagged iterator and fail to instantiate. Concrete importers declare the interface themselves.
 */
abstract class AbstractDatasetImporter
{
    /**
     * Trims a value and maps the empty string to null.
     */
    protected function nullable(string $value): ?string
    {
        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * Joins the validator violations into a single, human-readable rejection reason.
     *
     * @param ConstraintViolationListInterface<int, ConstraintViolationInterface> $violations
     */
    protected function formatViolations(ConstraintViolationListInterface $violations): string
    {
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
        }

        return implode(' | ', $messages);
    }
}
