<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\AssetVersionExtension;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see AssetVersionExtension::assetVer()}: it must append the file's mtime as a
 * version query (so a changed asset busts the browser cache) and degrade to the bare path when the
 * file is missing (never break the page). Runs against a throwaway public dir.
 */
final class AssetVersionExtensionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/asset-ver-'.uniqid('', true);
        mkdir($this->dir.'/js', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/js/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->dir.'/js');
        @rmdir($this->dir);
    }

    public function testAppendsMtimeForAnExistingFile(): void
    {
        $path = '/js/help.js';
        file_put_contents($this->dir.$path, '// hi');
        $mtime = filemtime($this->dir.$path);

        $result = (new AssetVersionExtension($this->dir))->assetVer($path);

        self::assertSame($path.'?v='.$mtime, $result);
    }

    public function testFallsBackToBarePathWhenFileIsMissing(): void
    {
        $result = (new AssetVersionExtension($this->dir))->assetVer('/js/does-not-exist.js');

        self::assertSame('/js/does-not-exist.js', $result);
    }

    public function testMemoisesSoTheResultIsStable(): void
    {
        $path = '/js/help.js';
        file_put_contents($this->dir.$path, '// hi');
        $ext = new AssetVersionExtension($this->dir);

        self::assertSame($ext->assetVer($path), $ext->assetVer($path));
    }
}
