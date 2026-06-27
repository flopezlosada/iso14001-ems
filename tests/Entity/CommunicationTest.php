<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Communication;
use App\Entity\InterestedParty;
use App\Enum\CommunicationCategory;
use App\Enum\CommunicationChannel;
use App\Enum\CommunicationScope;
use PHPUnit\Framework\TestCase;

final class CommunicationTest extends TestCase
{
    public function testIsComplaintOnlyForTheComplaintCategory(): void
    {
        $complaint = (new Communication())->setCategory(CommunicationCategory::COMPLAINT);
        self::assertTrue($complaint->isComplaint());

        $query = (new Communication())->setCategory(CommunicationCategory::QUERY);
        self::assertFalse($query->isComplaint());
    }

    public function testTouchUpdatesTheUpdatedAtTimestamp(): void
    {
        $communication = new Communication();
        $createdAt = $communication->getCreatedAt();
        // Constructed equal; touch() (a PrePersist/PreUpdate callback) advances updatedAt.
        self::assertEquals($createdAt, $communication->getUpdatedAt());

        $communication->touch();

        self::assertGreaterThanOrEqual($createdAt, $communication->getUpdatedAt());
        // created_at is never moved by touch().
        self::assertSame($createdAt, $communication->getCreatedAt());
    }

    public function testInterestedPartyIsOptionalAndSettable(): void
    {
        $communication = new Communication();
        self::assertNull($communication->getInterestedParty());

        $party = (new InterestedParty())->setReviewYear(2026)->setName('Proveedores')->setNeedsAndExpectations('…');
        $communication->setInterestedParty($party);
        self::assertSame($party, $communication->getInterestedParty());

        // The link can be severed (mirrors the ON DELETE SET NULL on the column).
        $communication->setInterestedParty(null);
        self::assertNull($communication->getInterestedParty());
    }

    public function testStoresAllRegisterColumns(): void
    {
        $communication = (new Communication())
            ->setOccurredOn(new \DateTimeImmutable('2026-03-12'))
            ->setScope(CommunicationScope::EXTERNAL)
            ->setCategory(CommunicationCategory::COMPLAINT)
            ->setChannel(CommunicationChannel::EMAIL)
            ->setSubject('Queja por retraso')
            ->setDetails('Texto completo de la queja.')
            ->setSender('Gestores de residuos')
            ->setRecipient('Responsable SGA')
            ->setResponse(null);

        self::assertSame(CommunicationScope::EXTERNAL, $communication->getScope());
        self::assertSame(CommunicationChannel::EMAIL, $communication->getChannel());
        self::assertSame('Queja por retraso', $communication->getSubject());
        self::assertSame('Texto completo de la queja.', $communication->getDetails());
        self::assertSame('Gestores de residuos', $communication->getSender());
        self::assertSame('Responsable SGA', $communication->getRecipient());
        self::assertNull($communication->getResponse());
    }
}
