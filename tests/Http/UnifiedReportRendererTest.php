<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Tests\Http;

use Effortmark\PptxChecker\Http\UnifiedReportRenderer;
use PHPUnit\Framework\TestCase;

final class UnifiedReportRendererTest extends TestCase
{
    public function testItPlacesAReviewImmediatelyAfterItsImageAndEscapesValues(): void
    {
        $report = '<p class="lead">Total slides: 1</p><div class="slide-item">'
            . '<h1>Slide 1</h1><p>Visible content</p><div class="image-object"><p>'
            . '<strong>Image:</strong> ../media/image1.png<br><strong>Description/Alt-text:</strong> '
            . '&lt;unsafe description&gt;</p></div><p>Content after image</p></div>'; 
        $review = ['slides' => [[
            'findings' => [[
                'imageNumber' => 1,
                'filename' => '../media/image1.png',
                'description' => '<unsafe description>',
                'code' => 'image.alt.missing',
                'severity' => 'error',
                'status' => 'missing',
                'score' => 20,
                'message' => 'Review this image.',
                'recommendation' => 'Check the context.',
                'matchedTitleTerms' => [],
                'matchedBodyTerms' => [],
            ]],
        ]]];

        $html = (new UnifiedReportRenderer())->render($report, $review);

        self::assertSame(1, substr_count($html, '&lt;unsafe description&gt;'));
        self::assertStringNotContainsString('<unsafe description>', $html);
        self::assertStringNotContainsString('<strong>File:</strong>', $html);
        self::assertStringNotContainsString('<strong>Alt text:</strong>', $html);
        self::assertStringContainsString('review-finding error', $html);
        self::assertLessThan(
            strpos($html, 'Content after image'),
            strpos($html, 'Review this image.'),
        );
        self::assertLessThan(
            strpos($html, 'Review this image.'),
            strpos($html, 'Description/Alt-text:'),
        );
    }

    public function testItPreservesUtf8TextWhenReparsingTheReport(): void
    {
        $report = '<div class="slide-item"><h1>Writer’s “summary”</h1><p>Body text</p></div>';

        $html = (new UnifiedReportRenderer())->render($report, ['slides' => [['findings' => []]]]);

        self::assertStringContainsString('Writer’s “summary”', $html);
        self::assertStringNotContainsString('â', $html);
    }

    public function testItDoesNotAddBoxesToSlidesWithoutMissingAltText(): void
    {
        $report = '<div class="slide-item"><h1>Slide 1</h1><p>Text only</p></div>';

        $html = (new UnifiedReportRenderer())->render($report, ['slides' => [['findings' => []]]]);

        self::assertStringNotContainsString('review-finding', $html);
        self::assertStringNotContainsString('review-empty', $html);
    }
}
