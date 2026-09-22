<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Tests\Presentation;

use Effortmark\PptxChecker\Presentation\PresentationProcessor;
use Effortmark\PptxChecker\Presentation\ProcessingException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class PresentationProcessorTest extends TestCase
{
    private string $presentationPath;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pptx-test-');
        self::assertNotFalse($path);
        $this->presentationPath = $path;
        $this->createPresentation($path);
    }

    protected function tearDown(): void
    {
        if (isset($this->presentationPath) && is_file($this->presentationPath)) {
            unlink($this->presentationPath);
        }
    }

    public function testItGeneratesCompatibleHtmlAndStructuredJsonData(): void
    {
        $processor = new PresentationProcessor(dirname(__DIR__, 2) . '/resources/pptx.xsl');

        $result = $processor->process($this->presentationPath, 'example.pptx');

        self::assertSame('example.pptx', $result->filename);
        self::assertSame(1, $result->slideCount);
        self::assertStringContainsString('Total slides:', $result->reportHtml);
        self::assertStringContainsString('<div class="slide-title">', $result->reportHtml);
        self::assertStringContainsString('class="slide-number">Slide 1', $result->reportHtml);
        self::assertLessThan(
            strpos($result->reportHtml, 'class="slide-number">Slide 1'),
            strpos($result->reportHtml, 'Hello world'),
        );
        self::assertStringContainsString('Hello world', $result->reportHtml);
        self::assertStringContainsString('Hello world', $result->slides[0]['text']);
        self::assertStringContainsString('<ul><li>First item</li></ul>', $result->reportHtml);
        self::assertStringContainsString('<ul><li>Second item</li></ul>', $result->reportHtml);
        self::assertStringContainsString('First item Second item', $result->slides[0]['text']);
        self::assertStringContainsString('<td><strong>Question</strong></td>', $result->reportHtml);
        self::assertStringContainsString('<td>Answer</td>', $result->reportHtml);
        self::assertStringContainsString('class="grouped-images"', $result->reportHtml);
        self::assertStringContainsString('<strong>Grouped images</strong>', $result->reportHtml);
        self::assertSame(1, $result->review['summary']['images']);
        self::assertSame(1, $result->review['summary']['errors']);
        self::assertSame('image.alt.missing', $result->review['slides'][0]['findings'][0]['code']);
        self::assertSame('../media/image1.png', $result->slides[0]['images'][0]['filename']);
        self::assertSame('../media/image1.png', $result->review['slides'][0]['findings'][0]['filename']);
        self::assertJson(json_encode($result, JSON_THROW_ON_ERROR));
        self::assertFileExists($this->presentationPath);
    }

    public function testItPreservesTextBoundariesFromPowerPointLineBreaks(): void
    {
        $processor = new PresentationProcessor(dirname(__DIR__, 2) . '/resources/pptx.xsl');

        $result = $processor->process(dirname(__DIR__, 2) . '/sample-files/sample2.pptx', 'sample2.pptx');

        self::assertStringContainsString('Slide With<br', $result->reportHtml);
        self::assertStringContainsString('Slide With Monalisa Photo', $result->slides[1]['text']);
    }

    public function testItRejectsNonPptxFilenames(): void
    {
        $processor = new PresentationProcessor(dirname(__DIR__, 2) . '/resources/pptx.xsl');

        $this->expectException(ProcessingException::class);
        $this->expectExceptionMessage('Only .pptx files are supported.');
        $processor->process($this->presentationPath, 'example.zip');
    }

    private function createPresentation(string $path): void
    {
        $archive = new ZipArchive();
        self::assertTrue($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $archive->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $archive->addFromString('ppt/presentation.xml', '<?xml version="1.0"?><p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"/>');
        $archive->addFromString('ppt/slides/slide1.xml', '<?xml version="1.0" encoding="UTF-8"?><p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><p:cSld><p:spTree><p:sp><p:nvSpPr><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr><p:txBody><a:p><a:r><a:t>Hello world</a:t></a:r></a:p></p:txBody></p:sp><p:sp><p:nvSpPr><p:nvPr><p:ph type="body"/></p:nvPr></p:nvSpPr><p:txBody><a:p><a:pPr><a:buChar char="•"/></a:pPr><a:r><a:t>First item</a:t></a:r></a:p><a:p><a:pPr><a:buChar char="•"/></a:pPr><a:r><a:t>Second item</a:t></a:r></a:p></p:txBody></p:sp><p:graphicFrame><a:graphic><a:graphicData><a:tbl><a:tblPr/><a:tblGrid/><a:tr><a:tc><a:txBody><a:p><a:r><a:rPr b="1"/><a:t>Question</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:p><a:r><a:t>Answer</a:t></a:r></a:p></a:txBody></a:tc></a:tr></a:tbl></a:graphicData></a:graphic></p:graphicFrame><p:grpSp><p:nvGrpSpPr><p:cNvPr id="10" name="Image group"/></p:nvGrpSpPr><p:pic><p:nvPicPr><p:cNvPr id="2" name="Image 1"/></p:nvPicPr><p:blipFill><a:blip r:embed="rId1"/></p:blipFill></p:pic></p:grpSp></p:spTree></p:cSld></p:sld>');
        $archive->addFromString('ppt/slides/_rels/slide1.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="../media/image1.png" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"/></Relationships>');
        self::assertTrue($archive->close());
    }
}
