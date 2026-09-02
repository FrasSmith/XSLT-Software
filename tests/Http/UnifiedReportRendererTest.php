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
            . '<h1>Slide 1</h1><p>Visible content</p><div class="image-object"><p>Image details</p></div>'
            . '<p>Content after image</p></div>';
        $review = ['slides' => [[
            'findings' => [[
                'imageNumber' => 1,
                'filename' => '../media/image1.png',
                'description' => '<unsafe description>',
                'severity' => 'warning',
                'status' => 'review_recommended',
                'score' => 20,
                'message' => 'Review this image.',
                'recommendation' => 'Check the context.',
                'matchedTitleTerms' => [],
                'matchedBodyTerms' => [],
            ]],
        ]]];

        $html = (new UnifiedReportRenderer())->render($report, $review);

        self::assertStringContainsString('&lt;unsafe description&gt;', $html);
        self::assertStringNotContainsString('<unsafe description>', $html);
        self::assertStringContainsString('review-finding warning', $html);
        self::assertLessThan(
            strpos($html, 'Content after image'),
            strpos($html, 'Review this image.'),
        );
        self::assertLessThan(
            strpos($html, 'Review this image.'),
            strpos($html, 'Image details'),
        );
    }

    public function testItMarksSlidesThatHaveNoImages(): void
    {
        $report = '<div class="slide-item"><h1>Slide 1</h1><p>Text only</p></div>';

        $html = (new UnifiedReportRenderer())->render($report, ['slides' => [['findings' => []]]]);

        self::assertStringContainsString('No images to review.', $html);
        self::assertStringContainsString('review-empty', $html);
    }
}
