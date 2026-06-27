<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Communication;
use App\Entity\InterestedParty;
use App\Enum\CommunicationCategory;
use App\Enum\CommunicationChannel;
use App\Enum\CommunicationScope;
use Doctrine\Bundle\FixturesBundle\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * The environmental communications register (RG-07.04.00 "Comunicaciones externas e internas",
 * PC.04.0, ISO 14001:2015 §7.4). Sample DEMO data modelled on the kinds of message an education
 * centre records: internal information, an external query and a complaint linked to an interested
 * party. All values are generic (no real personal data, no centre name) — safe for git.
 *
 * Depends on {@see InterestedPartyFixtures} for the parties some communications relate to.
 */
final class CommunicationFixtures extends AbstractDemoFixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [InterestedPartyFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        // [date, scope, category, channel, subject, sender, recipient, related party name|null, response|null]
        $rows = [
            ['2025-10-15', CommunicationScope::INTERNAL, CommunicationCategory::INFORMATION, CommunicationChannel::MEETING,
                'Comunicación de la política ambiental al claustro', 'Responsable SGA', 'Personal del centro', null, null],
            ['2025-11-20', CommunicationScope::INTERNAL, CommunicationCategory::SUGGESTION, CommunicationChannel::EMAIL,
                'Propuesta de reducir el consumo de papel en secretaría', 'Personal de secretaría', 'Responsable SGA', null,
                'Aceptada; se implanta la digitalización de circulares.'],
            ['2026-02-03', CommunicationScope::EXTERNAL, CommunicationCategory::QUERY, CommunicationChannel::WEB,
                'Consulta sobre la gestión de residuos del centro', 'Administraciones Públicas', 'Dirección',
                'Administraciones Públicas', 'Respondida con el archivo cronológico de residuos.'],
            ['2026-03-12', CommunicationScope::EXTERNAL, CommunicationCategory::COMPLAINT, CommunicationChannel::EMAIL,
                'Queja por retraso en la retirada de residuos', 'Gestores de residuos', 'Responsable SGA',
                'Gestores de residuos', null],
        ];

        foreach ($rows as [$date, $scope, $category, $channel, $subject, $sender, $recipient, $partyName, $response]) {
            $communication = new Communication();
            $communication->setOccurredOn(new \DateTimeImmutable($date))
                ->setScope($scope)
                ->setCategory($category)
                ->setChannel($channel)
                ->setSubject($subject)
                ->setSender($sender)
                ->setRecipient($recipient)
                ->setResponse($response);

            if (null !== $partyName) {
                $communication->setInterestedParty(
                    $this->getReference(InterestedPartyFixtures::ref($partyName), InterestedParty::class),
                );
            }

            $manager->persist($communication);
        }

        $manager->flush();
    }
}
