<?php

declare(strict_types=1);

namespace App\Tests\Service\ManagementReview\Provider;

use App\Entity\Communication;
use App\Entity\InterestedParty;
use App\Enum\CommunicationCategory;
use App\Enum\CommunicationChannel;
use App\Enum\CommunicationScope;
use App\Repository\CommunicationRepository;
use App\Service\ManagementReview\Provider\InterestedPartyCommsSummaryProvider;
use PHPUnit\Framework\TestCase;

final class InterestedPartyCommsSummaryProviderTest extends TestCase
{
    private function communication(
        string $date,
        CommunicationCategory $category,
        string $subject,
        ?InterestedParty $party = null,
        ?string $response = null,
    ): Communication {
        $communication = (new Communication())
            ->setOccurredOn(new \DateTimeImmutable($date))
            ->setScope(CommunicationScope::EXTERNAL)
            ->setCategory($category)
            ->setChannel(CommunicationChannel::EMAIL)
            ->setSubject($subject)
            ->setResponse($response);

        if (null !== $party) {
            $communication->setInterestedParty($party);
        }

        return $communication;
    }

    private function party(string $name): InterestedParty
    {
        return (new InterestedParty())
            ->setReviewYear(2026)
            ->setName($name)
            ->setNeedsAndExpectations('…');
    }

    public function testSummarisesPartyCommsAndComplaintsOfTheCoursesClosingYear(): void
    {
        $repo = $this->createMock(CommunicationRepository::class);
        // The course "2025-2026" maps to the closing year 2026 (ExerciseYears::endYear).
        $repo->expects(self::once())->method('findForYear')->with(2026)->willReturn([
            // A complaint linked to a party, not yet answered.
            $this->communication('2026-03-12', CommunicationCategory::COMPLAINT, 'Retraso en la retirada', $this->party('Gestores de residuos')),
            // A query linked to a party (relevant because it relates to a party).
            $this->communication('2026-02-03', CommunicationCategory::QUERY, 'Consulta de residuos', $this->party('Administraciones Públicas')),
            // A complaint with no party but with a recorded response (still relevant: it is a complaint).
            $this->communication('2026-04-01', CommunicationCategory::COMPLAINT, 'Ruido en obras', null, 'Resuelta con el contratista.'),
            // An internal info with no party: NOT relevant to this section, must be excluded.
            $this->communication('2026-01-10', CommunicationCategory::INFORMATION, 'Comunicado interno', null),
        ]);

        $summary = (new InterestedPartyCommsSummaryProvider($repo))->summarize('2025-2026');

        // 3 relevant (the internal info is excluded), 2 of them complaints.
        self::assertStringContainsString('Comunicaciones pertinentes de partes interesadas en 2026: 3 (de ellas, 2 quejas).', $summary);
        self::assertStringNotContainsString('Comunicado interno', $summary);
        // The complaint without a response is flagged as pending and shows its related party.
        self::assertStringContainsString('- 12/03/2026 [Queja] Retraso en la retirada — Gestores de residuos (pendiente de respuesta)', $summary);
        // The complaint with a response is flagged as answered.
        self::assertStringContainsString('- 01/04/2026 [Queja] Ruido en obras (respondida)', $summary);
        // A non-complaint linked to a party carries no complaint suffix.
        self::assertStringContainsString('- 03/02/2026 [Consulta] Consulta de residuos — Administraciones Públicas'."\n", $summary."\n");
    }

    public function testComplaintWithWhitespaceOnlyResponseIsStillPending(): void
    {
        $repo = $this->createMock(CommunicationRepository::class);
        // A response of only blank space is not a real answer: the complaint stays pending.
        $repo->method('findForYear')->with(2026)->willReturn([
            $this->communication('2026-05-01', CommunicationCategory::COMPLAINT, 'Queja con respuesta en blanco', null, '   '),
        ]);

        $summary = (new InterestedPartyCommsSummaryProvider($repo))->summarize('2025-2026');

        self::assertStringContainsString('(pendiente de respuesta)', $summary);
        self::assertStringNotContainsString('(respondida)', $summary);
    }

    public function testSingularQuejaWording(): void
    {
        $repo = $this->createMock(CommunicationRepository::class);
        $repo->method('findForYear')->with(2026)->willReturn([
            $this->communication('2026-03-12', CommunicationCategory::COMPLAINT, 'Una queja', null),
        ]);

        $summary = (new InterestedPartyCommsSummaryProvider($repo))->summarize('2025-2026');

        self::assertStringContainsString('1 (de ellas, 1 queja).', $summary);
    }

    public function testReturnsEmptyWhenNothingRelevant(): void
    {
        $repo = $this->createMock(CommunicationRepository::class);
        // Only an internal info with no party: nothing relevant to report.
        $repo->method('findForYear')->with(2026)->willReturn([
            $this->communication('2026-01-10', CommunicationCategory::INFORMATION, 'Comunicado interno', null),
        ]);

        self::assertSame('', (new InterestedPartyCommsSummaryProvider($repo))->summarize('2025-2026'));
    }

    public function testReturnsEmptyWhenNoCommunicationsForTheYear(): void
    {
        $repo = $this->createMock(CommunicationRepository::class);
        $repo->method('findForYear')->with(2026)->willReturn([]);

        self::assertSame('', (new InterestedPartyCommsSummaryProvider($repo))->summarize('2025-2026'));
    }
}
