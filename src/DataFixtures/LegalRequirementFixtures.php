<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\LegalRequirement;
use App\Enum\ComplianceStatus;
use App\Enum\EvaluationFrequency;
use App\Enum\LegalScope;
use Doctrine\Persistence\ObjectManager;

/**
 * The register of legal and other requirements (PC-06.03). DEMO layer only: the real register is
 * loaded by the ETL ('legal-requirements' importer), so seeding it here as part of the GOLDEN
 * backbone would duplicate it against the real data.
 *
 * Real Spanish/EU environmental provisions applicable to a school, with synthetic compliance
 * evidence and review dates so the compliance and next-review views have something to show.
 */
final class LegalRequirementFixtures extends AbstractDemoFixture
{
    public function load(ObjectManager $manager): void
    {
        // [reference, provision, scope, vector, requirement, status, evaluation frequency]
        $requirements = [
            ['RL.01', 'Ley 7/2022 de residuos y suelos contaminados para una economía circular', LegalScope::NATIONAL, 'Residuos', 'Gestión de los residuos peligrosos a través de gestor autorizado y archivo cronológico.', ComplianceStatus::COMPLIANT, EvaluationFrequency::ANNUAL],
            ['RL.02', 'RD 110/2015 sobre residuos de aparatos eléctricos y electrónicos (RAEE)', LegalScope::NATIONAL, 'Residuos', 'Entrega de fluorescentes y equipos electrónicos a gestor autorizado de RAEE.', ComplianceStatus::COMPLIANT, EvaluationFrequency::ANNUAL],
            ['RL.03', 'Reglamento (UE) 517/2014 sobre gases fluorados de efecto invernadero', LegalScope::EUROPEAN, 'Emisiones', 'Control y registro de los equipos de climatización con gases fluorados.', ComplianceStatus::PENDING, EvaluationFrequency::ANNUAL],
            ['RL.04', 'RD 102/2011 relativo a la mejora de la calidad del aire', LegalScope::NATIONAL, 'Emisiones', 'Mantenimiento e inspección periódica de la caldera de calefacción.', ComplianceStatus::COMPLIANT, EvaluationFrequency::BIANNUAL],
            ['RL.05', 'Ordenanza municipal de vertidos a la red de saneamiento', LegalScope::LOCAL, 'Vertidos', 'Cumplimiento de los límites de vertido establecidos por el municipio.', ComplianceStatus::NON_COMPLIANT, EvaluationFrequency::ANNUAL],
        ];

        $sequence = 1;
        foreach ($requirements as [$reference, $provision, $scope, $vector, $requirement, $status, $frequency]) {
            $legal = new LegalRequirement();
            $legal->setReference($reference)
                ->setSequence($sequence++)
                ->setLegalProvision($provision)
                ->setScope($scope)
                ->setEnvironmentalVector($vector)
                ->setSpecificRequirement($requirement)
                ->setComplianceStatus($status)
                ->setEvaluationFrequency($frequency)
                ->setComplianceEvidence(ComplianceStatus::COMPLIANT === $status ? 'Evidencia archivada en la carpeta del SGA.' : null)
                ->setLastReviewedOn(new \DateTimeImmutable('2025-09-01'))
                ->setNextReviewOn(new \DateTimeImmutable('2026-09-01'));
            $manager->persist($legal);
        }

        $manager->flush();
    }
}
