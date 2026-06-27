<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\FileUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Unit tests for {@see FileUploader}: safe storage, retrieval and removal, against a throwaway
 * temporary directory.
 */
final class FileUploaderTest extends TestCase
{
    private string $baseDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->baseDir = sys_get_temp_dir().'/file-uploader-test-'.uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->baseDir);
    }

    private function fakeUpload(string $name): UploadedFile
    {
        $source = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($source, "%PDF-1.4\ntest invoice\n%%EOF");

        // 5th arg ($test = true) bypasses the is_uploaded_file() check so it works in tests.
        return new UploadedFile($source, $name, 'application/pdf', null, true);
    }

    public function testUploadStoresFileUnderSubdirAndReturnsRelativePath(): void
    {
        $uploader = new FileUploader($this->baseDir);

        $relativePath = $uploader->upload($this->fakeUpload('mi factura.pdf'), 'consumption-invoices');

        self::assertStringStartsWith('consumption-invoices/', $relativePath);
        self::assertFileExists($uploader->absolutePath($relativePath));
        // The client filename never reaches the filesystem (random UUID name).
        self::assertStringNotContainsString('mi factura', $relativePath);
    }

    public function testStorePersistsRawContentsUnderSubdir(): void
    {
        $uploader = new FileUploader($this->baseDir);

        $relativePath = $uploader->store("%PDF-1.4\nhola\n%%EOF", 'document-pdfs', 'pdf');

        self::assertStringStartsWith('document-pdfs/', $relativePath);
        self::assertStringEndsWith('.pdf', $relativePath);
        $absolute = $uploader->absolutePath($relativePath);
        self::assertFileExists($absolute);
        self::assertStringStartsWith('%PDF', (string) file_get_contents($absolute));
    }

    public function testRemoveDeletesStoredFile(): void
    {
        $uploader = new FileUploader($this->baseDir);
        $relativePath = $uploader->upload($this->fakeUpload('factura.pdf'), 'consumption-invoices');
        $absolute = $uploader->absolutePath($relativePath);
        self::assertFileExists($absolute);

        $uploader->remove($relativePath);

        self::assertFileDoesNotExist($absolute);
    }
}
