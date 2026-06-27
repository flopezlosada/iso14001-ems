<?php

declare(strict_types=1);

namespace App\Tests\Service\Import;

use App\Entity\OperationalControlAnswer;
use App\Entity\OperationalControlCheck;
use App\Enum\ConformityResult;
use App\Enum\OperationalControlSection;
use App\Repository\OperationalControlCheckRepository;
use App\Repository\OperationalControlItemRepository;
use App\Service\Import\OperationalControlImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the operational-control importer over a real test database (rolled back per
 * test). Covers seeding the catalogue from the real labels, the monthly inspection upserted by
 * (year, month), answers upserted by (check, item), the per-item remarks folded into the inspection
 * note, idempotent re-import and rejection of invalid rows.
 */
final class OperationalControlImporterTest extends KernelTestCase
{
    private OperationalControlImporter $importer;
    private OperationalControlItemRepository $items;
    private OperationalControlCheckRepository $checks;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->importer = $container->get(OperationalControlImporter::class);
        $this->items = $container->get(OperationalControlItemRepository::class);
        $this->checks = $container->get(OperationalControlCheckRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    private function checkFor(int $year, int $month): ?OperationalControlCheck
    {
        return $this->checks->findOneBy(['periodYear' => $year, 'periodMonth' => $month]);
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'year' => '2025',
            'month' => '1',
            'performed_by' => 'Resp. SGMA',
            'section' => 'water',
            'item_label' => 'Buen estado de grifos y cisterna',
            'result' => 'conforme',
            'observation' => '',
        ], $overrides);
    }

    public function testCreatesInspectionWithItemsAndAnswers(): void
    {
        $report = $this->importer->import([
            $this->row(['section' => 'water', 'item_label' => 'Buen estado de grifos y cisterna', 'result' => 'conforme']),
            $this->row(['section' => 'energy', 'item_label' => 'La fotocopiadora está en modo ahorro', 'result' => 'non_conforme', 'observation' => 'SE HA QUEDADO ENCENDIDA']),
        ], false);
        $this->entityManager->clear();

        self::assertSame(2, $report->getCreated());
        self::assertSame([], $report->getRejected());

        $check = $this->checkFor(2025, 1);
        self::assertNotNull($check);
        self::assertSame('Resp. SGMA', $check->getPerformedBy());
        self::assertCount(2, $check->getAnswers());
        self::assertSame(1, $check->countNonConform());
    }

    public function testSeedsCatalogueWithTheRealLabel(): void
    {
        $this->importer->import([$this->row(['item_label' => 'Buen estado de grifos y cisterna'])], false);
        $this->entityManager->clear();

        $item = $this->items->findOneBy(['section' => OperationalControlSection::WATER, 'label' => 'Buen estado de grifos y cisterna']);
        self::assertNotNull($item, 'The catalogue is seeded from the real sheet wording.');
        self::assertTrue($item->isActive());
    }

    public function testTwoMonthsAreTwoInspectionsSharingOneCatalogueItem(): void
    {
        $report = $this->importer->import([
            $this->row(['month' => '1', 'result' => 'conforme']),
            $this->row(['month' => '2', 'result' => 'non_conforme']),
        ], false);
        $this->entityManager->clear();

        self::assertSame(2, $report->getCreated());
        self::assertSame(2, $this->checks->count([]), 'One inspection per month.');
        self::assertSame(1, $this->items->count([]), 'The same item is shared, not duplicated.');
        $february = $this->checkFor(2025, 2);
        self::assertNotNull($february);
        $answer = $february->getAnswers()->first();
        self::assertInstanceOf(OperationalControlAnswer::class, $answer);
        self::assertSame(ConformityResult::NON_CONFORME, $answer->getResult());
    }

    public function testReimportIsIdempotentAndUpdatesAnswerInPlace(): void
    {
        $this->importer->import([$this->row(['result' => 'conforme'])], false);
        $this->entityManager->clear();

        $report = $this->importer->import([$this->row(['result' => 'non_conforme'])], false);
        $this->entityManager->clear();

        self::assertSame(0, $report->getCreated());
        self::assertSame(1, $report->getUpdated());
        self::assertSame(1, $this->checks->count([]), 'The inspection is not duplicated.');
        self::assertSame(1, $this->items->count([]), 'The item is not duplicated.');

        $check = $this->checkFor(2025, 1);
        self::assertNotNull($check);
        self::assertCount(1, $check->getAnswers());
        $answer = $check->getAnswers()->first();
        self::assertInstanceOf(OperationalControlAnswer::class, $answer);
        self::assertSame(ConformityResult::NON_CONFORME, $answer->getResult(), 'The answer is updated in place.');
    }

    public function testPerItemRemarksAreFoldedAndRebuiltOnReimport(): void
    {
        $this->importer->import([
            $this->row(['item_label' => 'La fotocopiadora está en modo ahorro', 'section' => 'energy', 'result' => 'non_conforme', 'observation' => 'SE HA QUEDADO ENCENDIDA']),
        ], false);
        $this->entityManager->clear();

        $check = $this->checkFor(2025, 1);
        self::assertNotNull($check);
        self::assertSame('La fotocopiadora está en modo ahorro: SE HA QUEDADO ENCENDIDA', $check->getObservations());

        // Re-import the same item without a remark: the note is rebuilt from scratch, not appended.
        $this->importer->import([
            $this->row(['item_label' => 'La fotocopiadora está en modo ahorro', 'section' => 'energy', 'result' => 'conforme', 'observation' => '']),
        ], false);
        $this->entityManager->clear();

        self::assertNull($this->checkFor(2025, 1)?->getObservations(), 'A pass with no remarks clears the note.');
    }

    public function testRejectsUnknownSectionWithoutPersisting(): void
    {
        $report = $this->importer->import([$this->row(['section' => 'plutonium'])], false);

        self::assertCount(1, $report->getRejected());
        self::assertStringContainsString('Sección desconocida', $report->getRejected()[0]['reason']);
        self::assertSame(0, $this->items->count([]));
    }

    public function testRejectsRowWithoutPeriod(): void
    {
        $report = $this->importer->import([$this->row(['year' => '', 'month' => ''])], false);

        self::assertCount(1, $report->getRejected());
        self::assertSame(0, $this->checks->count([]));
    }
}
