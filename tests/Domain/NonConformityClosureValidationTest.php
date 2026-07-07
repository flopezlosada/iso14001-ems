<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Entity\CorrectiveAction;
use App\Entity\NonConformity;
use App\Enum\Efficacy;
use App\Enum\NonConformityOrigin;
use App\Enum\NonConformityStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Closure rule of {@see NonConformity} (PC.10.0 §4.3.4): a non-conformity can only be closed once no
 * corrective action is still pending review and at least one has been reviewed effective (efficacy
 * OK). A pending review blocks closure; a NO-OK review does not (it stays on record and is superseded
 * by a later effective action), but a non-conformity whose only reviewed actions are NO-OK cannot be
 * closed for want of an effective one. A non-conformity with no actions (immediate correction) stays
 * closeable.
 *
 * Exercised through the real Symfony validator, since the rule is a declarative Assert\Callback
 * (a mock would prove nothing).
 */
final class NonConformityClosureValidationTest extends KernelTestCase
{
    private function validator(): ValidatorInterface
    {
        self::bootKernel();

        return self::getContainer()->get(ValidatorInterface::class);
    }

    /**
     * A minimally-complete non-conformity in the given status, with one corrective action per
     * supplied efficacy (null = review still pending).
     *
     * @param NonConformityStatus $status     the lifecycle status to validate
     * @param list<Efficacy|null> $efficacies the efficacy of each attached corrective action
     */
    private function nonConformity(NonConformityStatus $status, array $efficacies = []): NonConformity
    {
        $nc = (new NonConformity())
            ->setReference('NC.AE.2026.01')
            ->setOrigin(NonConformityOrigin::INTERNAL)
            ->setYear(2026)
            ->setSequence(1)
            ->setDescription('Incumplimiento de prueba.')
            ->setOpenedAt(new \DateTimeImmutable('2026-01-10'))
            ->setStatus($status);

        foreach ($efficacies as $i => $efficacy) {
            $action = (new CorrectiveAction())
                ->setSequence($i + 1)
                ->setDescription('Acción de prueba.')
                ->setEfficacy($efficacy);
            $nc->addCorrectiveAction($action);
        }

        return $nc;
    }

    /**
     * Counts the violations reported against the status path (the closure rule).
     */
    private function closureViolations(NonConformity $nc): int
    {
        $count = 0;
        foreach ($this->validator()->validate($nc) as $violation) {
            if ('status' === $violation->getPropertyPath()) {
                ++$count;
            }
        }

        return $count;
    }

    public function testOpenNonConformityIsNotSubjectToTheClosureRule(): void
    {
        // A pending action does not block staying open/in treatment.
        self::assertSame(0, $this->closureViolations($this->nonConformity(NonConformityStatus::OPEN, [null])));
    }

    public function testClosingWithNoCorrectiveActionsIsAllowed(): void
    {
        // Minor non-conformity resolved by an immediate correction: still closeable.
        self::assertSame(0, $this->closureViolations($this->nonConformity(NonConformityStatus::CLOSED)));
    }

    public function testClosingWithAllActionsEffectiveIsAllowed(): void
    {
        self::assertSame(0, $this->closureViolations($this->nonConformity(NonConformityStatus::CLOSED, [Efficacy::OK])));
    }

    public function testClosingWithAPendingReviewIsRejected(): void
    {
        self::assertSame(1, $this->closureViolations($this->nonConformity(NonConformityStatus::CLOSED, [Efficacy::OK, null])));
    }

    public function testClosingWithOnlyANotOkReviewIsRejected(): void
    {
        // A single ineffective action leaves no effective one: closure needs at least one OK.
        self::assertSame(1, $this->closureViolations($this->nonConformity(NonConformityStatus::CLOSED, [Efficacy::NOT_OK])));
    }

    public function testClosingWithANotOkSupersededByAnEffectiveActionIsAllowed(): void
    {
        // The failed attempt stays on record; a later effective action unblocks closure.
        self::assertSame(0, $this->closureViolations($this->nonConformity(NonConformityStatus::CLOSED, [Efficacy::NOT_OK, Efficacy::OK])));
    }

    public function testClosingWithANotOkAndAPendingReviewIsRejected(): void
    {
        // Even with a failed attempt on record, an unreviewed action still blocks closure.
        self::assertSame(1, $this->closureViolations($this->nonConformity(NonConformityStatus::CLOSED, [Efficacy::NOT_OK, null])));
    }
}
