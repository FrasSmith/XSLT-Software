<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Tests\Presentation;

use Effortmark\PptxChecker\Presentation\ReportParser;
use PHPUnit\Framework\TestCase;

final class ReportParserTest extends TestCase
{
    public function testItBuildsStructuredSlidesFromTheHtmlReport(): void
    {
        $html = '<p class="lead">Total slides: 1</p>'
            . '<div class="slide-item"><h1>Slide 1</h1><p>Hello</p>'
            . '<div class="image-object" data-filename="../media/image1.png"><p><strong>Image:</strong><br>'
            . '<span><strong>Description/Alt-text:</strong> A useful chart</span></p></div></div>';

        $slides = (new ReportParser())->parseSlides($html);

        self::assertCount(1, $slides);
        self::assertSame(1, $slides[0]['number']);
        self::assertStringContainsString('Hello', $slides[0]['text']);
        self::assertSame('../media/image1.png', $slides[0]['images'][0]['filename']);
        self::assertSame('A useful chart', $slides[0]['images'][0]['description']);
    }

    public function testItPreservesUtf8QuotesAndSpacesAtHtmlBoundaries(): void
    {
        $html = '<div class="slide-item"><h1>Writer’s notes</h1>'
            . '<p>Slide With<br>Monalisa Photo</p>'
            . '<div class="image-object" data-filename="image.png"><p><strong>Image:</strong><br>'
            . '<span><strong>Description/Alt-text:</strong> “Revenue” increased sharply</span></p></div></div>';

        $slides = (new ReportParser())->parseSlides($html);

        self::assertSame(
            'Writer’s notes Slide With Monalisa Photo Image: Description/Alt-text: “Revenue” increased sharply',
            $slides[0]['text'],
        );
        self::assertSame('“Revenue” increased sharply', $slides[0]['images'][0]['description']);
    }
}
