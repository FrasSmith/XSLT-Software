<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Tests\Presentation;

use Effortmark\PptxChecker\Presentation\AccessibilityReviewer;
use PHPUnit\Framework\TestCase;

final class AccessibilityReviewerTest extends TestCase
{
    public function testItReportsOnlyMissingAltTextAsAnError(): void
    {
        $review = (new AccessibilityReviewer())->review([[
            'number' => 1,
            'title' => 'Slide title',
            'visibleText' => 'Visible content',
            'images' => [
                ['filename' => 'missing.png', 'description' => '', 'decorative' => false],
                ['filename' => 'described.png', 'description' => 'A useful description', 'decorative' => false],
                ['filename' => 'generic.png', 'description' => 'generic.png', 'decorative' => false],
                ['filename' => 'decorative.png', 'description' => '', 'decorative' => true],
            ],
        ]]);

        self::assertSame(4, $review['summary']['images']);
        self::assertSame(1, $review['summary']['errors']);
        self::assertSame(0, $review['summary']['warnings']);
        self::assertSame(2, $review['summary']['passed']);
        self::assertSame(1, $review['summary']['excluded']);
        self::assertCount(1, $review['slides'][0]['findings']);
        self::assertSame('image.alt.missing', $review['slides'][0]['findings'][0]['code']);
        self::assertSame('missing.png', $review['slides'][0]['findings'][0]['filename']);
    }

    public function testItReturnsNoFindingsWhenAllImagesHaveDescriptions(): void
    {
        $review = (new AccessibilityReviewer())->review([[
            'number' => 1,
            'title' => '',
            'visibleText' => '',
            'images' => [
                ['filename' => 'image.png', 'description' => 'Description', 'decorative' => false],
            ],
        ]]);

        self::assertSame(1, $review['summary']['images']);
        self::assertSame(0, $review['summary']['errors']);
        self::assertSame([], $review['slides'][0]['findings']);
    }

    public function testItReportsAnEmptyPresentationWithoutFindings(): void
    {
        $review = (new AccessibilityReviewer())->review([[
            'number' => 1,
            'title' => '',
            'visibleText' => '',
            'images' => [],
        ]]);

        self::assertSame(0, $review['summary']['images']);
        self::assertSame(0, $review['summary']['errors']);
        self::assertSame([], $review['slides'][0]['findings']);
    }
}
