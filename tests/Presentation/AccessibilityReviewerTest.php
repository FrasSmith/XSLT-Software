<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Tests\Presentation;

use Effortmark\PptxChecker\Presentation\AccessibilityReviewer;
use PHPUnit\Framework\TestCase;

final class AccessibilityReviewerTest extends TestCase
{
    public function testItExcludesDecorativeImages(): void
    {
        $review = $this->reviewImage('', true);

        self::assertSame('excluded', $review['status']);
        self::assertSame('information', $review['severity']);
        self::assertNull($review['score']);
    }

    public function testItReportsMissingAltTextAsAnError(): void
    {
        $review = $this->reviewImage('');

        self::assertSame('missing', $review['status']);
        self::assertSame('error', $review['severity']);
    }

    public function testItReportsGenericAltTextAsAnError(): void
    {
        $review = $this->reviewImage('image1.png', false, '../media/image1.png');

        self::assertSame('inadequate', $review['status']);
        self::assertSame('error', $review['severity']);
        self::assertSame(0, $review['score']);
    }

    public function testItScoresRelevantTermsAndNumbers(): void
    {
        $review = $this->reviewImage(
            'Revenue increased by 18 percent',
            false,
            '../media/chart.png',
            'Quarterly revenue',
            'Quarterly revenue increased by 18 percent in Q2',
        );

        self::assertSame('possibly_relevant', $review['status']);
        self::assertGreaterThanOrEqual(70, $review['score']);
        self::assertContains('revenue', $review['matchedTitleTerms']);
    }

    public function testItConservativelyRequestsReviewForLowTextualEvidence(): void
    {
        $review = $this->reviewImage(
            'Child holding a red umbrella',
            false,
            '../media/photo.png',
            'Consumer confidence',
            'Survey results for the second quarter',
        );

        self::assertSame('review_recommended', $review['status']);
        self::assertSame('warning', $review['severity']);
        self::assertStringContainsString('does not necessarily mean', $review['recommendation']);
    }

    /** @return array<string, mixed> */
    private function reviewImage(
        string $description,
        bool $decorative = false,
        string $filename = '../media/image1.png',
        string $title = 'Slide title',
        string $visibleText = 'Visible slide content',
    ): array {
        $result = (new AccessibilityReviewer())->review([[
            'number' => 1,
            'title' => $title,
            'visibleText' => $visibleText,
            'images' => [[
                'filename' => $filename,
                'description' => $description,
                'decorative' => $decorative,
            ]],
        ]]);

        return $result['slides'][0]['findings'][0];
    }
}
