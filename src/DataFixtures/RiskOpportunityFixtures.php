<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ProcessArea;
use App\Entity\RiskAction;
use App\Entity\RiskAssessment;
use App\Entity\RiskOpportunity;
use App\Entity\User;
use App\Enum\RiskLevel;
use App\Enum\RiskOpportunityType;
use App\Service\RiskScoreCalculator;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * The risks and opportunities register and its valuations (PC.03.0 / F.08.0). Part of the GOLDEN
 * backbone.
 *
 * Synthetic but realistic for a school. The valuation score and category are NOT hand-written:
 * they are computed by the real {@see RiskScoreCalculator} (score = probability × impact, banded
 * into trivial/moderate/critical), so the seeded data satisfies the same invariant the app
 * enforces. Critical entries carry an action plan.
 */
final class RiskOpportunityFixtures extends AbstractGoldenFixture implements DependentFixtureInterface
{
    private const string EXERCISE = '2025/2026';

    public function __construct(private readonly RiskScoreCalculator $calculator)
    {
    }

    public function load(ObjectManager $manager): void
    {
        foreach ($this->definitions() as $def) {
            $item = new RiskOpportunity();
            $item->setType($def['type'])
                ->setDescription($def['description'])
                ->setProcessArea($this->getReference(ProcessAreaFixtures::ref($def['area']), ProcessArea::class));

            $assessment = new RiskAssessment();
            $assessment->setRiskOpportunity($item)
                ->setExercise(self::EXERCISE)
                ->setProbability($def['probability'])
                ->setImpact($def['impact'])
                ->setJustification($def['justification'])
                ->setRevisionNumber(1)
                ->setApprovedBy($this->getReference(UserFixtures::ref('direccion'), User::class))
                ->setApprovedAt(new \DateTimeImmutable('2025-09-15 09:00:00'));
            $this->calculator->apply($assessment); // fills score + category
            $item->addAssessment($assessment);

            if (null !== ($action = $def['action'] ?? null)) {
                $riskAction = new RiskAction();
                $riskAction->setAssessment($assessment)
                    ->setDescription($action['description'])
                    ->setResponsible($action['responsible'])
                    ->setDeadline($action['deadline']);
                $assessment->addAction($riskAction);
            }

            $manager->persist($item);
        }

        $manager->flush();
    }

    /**
     * @return list<array{type: RiskOpportunityType, description: string, area: string,
     *     probability: RiskLevel, impact: RiskLevel, justification: string,
     *     action?: array{description: string, responsible: string, deadline: string}}>
     */
    private function definitions(): array
    {
        $hi = RiskLevel::HIGH;
        $mid = RiskLevel::MEDIUM;
        $lo = RiskLevel::LOW;

        return [
            [
                'type' => RiskOpportunityType::RISK,
                'description' => 'Sanción administrativa por gestión incorrecta de residuos peligrosos.',
                'area' => 'ambiental',
                'probability' => $hi, 'impact' => $hi, // 9 → crítico
                'justification' => 'Histórico de una NC en auditoría y normativa exigente de residuos peligrosos.',
                'action' => [
                    'description' => 'Implantar un calendario de recogidas con gestor autorizado y revisar el archivo cronológico.',
                    'responsible' => 'Responsable del SGA', 'deadline' => 'Diciembre 2025',
                ],
            ],
            [
                'type' => RiskOpportunityType::RISK,
                'description' => 'Incumplimiento legal sobrevenido por cambios en la normativa ambiental.',
                'area' => 'direccion',
                'probability' => $mid, 'impact' => $hi, // 6 → crítico
                'justification' => 'La normativa ambiental cambia con frecuencia y afecta a varios vectores.',
                'action' => [
                    'description' => 'Revisión semestral del registro de requisitos legales con fuente actualizada.',
                    'responsible' => 'Secretaría', 'deadline' => 'Revisión semestral',
                ],
            ],
            [
                'type' => RiskOpportunityType::RISK,
                'description' => 'Interrupción de la calefacción en invierno por avería de la caldera.',
                'area' => 'mantenimiento',
                'probability' => $mid, 'impact' => $mid, // 4 → moderado
                'justification' => 'La caldera tiene cierta antigüedad; el impacto es alto en los meses fríos.',
            ],
            [
                'type' => RiskOpportunityType::RISK,
                'description' => 'Pequeños derrames de productos de limpieza en el almacén.',
                'area' => 'mantenimiento',
                'probability' => $lo, 'impact' => $mid, // 2 → trivial
                'justification' => 'Cantidades pequeñas y baja frecuencia; contención disponible.',
            ],
            [
                'type' => RiskOpportunityType::OPPORTUNITY,
                'description' => 'Reducción de costes y consumo por eficiencia energética (LED y fotovoltaica).',
                'area' => 'direccion',
                'probability' => $hi, 'impact' => $mid, // 6 → crítico (oportunidad prioritaria)
                'justification' => 'Existen subvenciones y el ahorro potencial es elevado.',
                'action' => [
                    'description' => 'Solicitar subvención para placas fotovoltaicas y completar el cambio a iluminación LED.',
                    'responsible' => 'Dirección', 'deadline' => 'Curso 2025/2026',
                ],
            ],
            [
                'type' => RiskOpportunityType::OPPORTUNITY,
                'description' => 'Mejora de la imagen del centro gracias a la certificación ISO 14001.',
                'area' => 'direccion',
                'probability' => $hi, 'impact' => $lo, // 3 → moderado
                'justification' => 'La certificación es un argumento de diferenciación frente a la comunidad educativa.',
            ],
        ];
    }

    public function getDependencies(): array
    {
        return [ProcessAreaFixtures::class, UserFixtures::class];
    }
}
