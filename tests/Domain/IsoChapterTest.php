<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Enum\IsoChapter;
use App\Enum\PdcaPhase;
use PHPUnit\Framework\TestCase;

/**
 * The obligations supra-structure: every ISO chapter (4-10) derives a single PDCA phase, and every
 * phase maps to the centre's "00".."03" folder. The phase is derived, never stored apart, so the
 * two can never contradict each other.
 */
final class IsoChapterTest extends TestCase
{
    /**
     * @return iterable<string, array{IsoChapter, PdcaPhase, string}>
     */
    public static function chapterPhaseProvider(): iterable
    {
        yield 'Contexto → Plan (00)' => [IsoChapter::CONTEXT, PdcaPhase::PLAN, '00'];
        yield 'Liderazgo → Plan (00)' => [IsoChapter::LEADERSHIP, PdcaPhase::PLAN, '00'];
        yield 'Planificación → Plan (00)' => [IsoChapter::PLANNING, PdcaPhase::PLAN, '00'];
        yield 'Apoyo → Do (01)' => [IsoChapter::SUPPORT, PdcaPhase::DO, '01'];
        yield 'Operación → Do (01)' => [IsoChapter::OPERATION, PdcaPhase::DO, '01'];
        yield 'Evaluación → Check (02)' => [IsoChapter::PERFORMANCE_EVALUATION, PdcaPhase::CHECK, '02'];
        yield 'Mejora → Act (03)' => [IsoChapter::IMPROVEMENT, PdcaPhase::ACT, '03'];
    }

    /**
     * @dataProvider chapterPhaseProvider
     */
    public function testChapterDerivesPhaseAndFolder(IsoChapter $chapter, PdcaPhase $expectedPhase, string $expectedFolder): void
    {
        self::assertSame($expectedPhase, $chapter->phase());
        self::assertSame($expectedFolder, $chapter->phase()->folderCode());
    }

    public function testEveryChapterHasANonEmptyLabel(): void
    {
        foreach (IsoChapter::cases() as $chapter) {
            self::assertNotSame('', $chapter->label());
        }
    }

    public function testEveryPhaseHasADistinctFolderCode(): void
    {
        $codes = array_map(static fn (PdcaPhase $p) => $p->folderCode(), PdcaPhase::cases());

        self::assertSame($codes, array_unique($codes));
        self::assertSame(['00', '01', '02', '03'], $codes);
    }
}
