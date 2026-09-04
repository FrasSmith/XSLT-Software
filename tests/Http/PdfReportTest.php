<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Tests\Http;

use Effortmark\PptxChecker\Http\PdfReportGenerator;
use Effortmark\PptxChecker\Http\TemporaryPdfStore;
use PHPUnit\Framework\TestCase;

final class PdfReportTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/pptx-pdf-test-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->temporaryDirectory, 0700));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->temporaryDirectory);
    }

    public function testItGeneratesAnA4PortraitPdf(): void
    {
        $pdf = (new PdfReportGenerator())->generate(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><h1>Writer’s report</h1></body></html>',
        );

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertMatchesRegularExpression('/\/MediaBox\s*\[0\.000 0\.000 595\.280 841\.890]/', $pdf);
    }

    public function testTemporaryReportsArePrivateAndRemovedWhenExpired(): void
    {
        $store = new TemporaryPdfStore($this->temporaryDirectory, 60);
        $download = $store->store('%PDF-test', 'Quarterly results.pptx');
        $downloads = ['token' => $download];

        self::assertFileExists($download['path']);
        self::assertSame('Quarterly-results-accessibility-report.pdf', $download['filename']);
        self::assertSame(0600, fileperms($download['path']) & 0777);

        $store->removeExpired($downloads, $download['expiresAt']);

        self::assertSame([], $downloads);
        self::assertFileDoesNotExist($download['path']);
    }
}
