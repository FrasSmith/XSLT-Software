<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Tests\Http;

use Effortmark\PptxChecker\Http\TemplateRenderer;
use Effortmark\PptxChecker\Presentation\AnalysisResult;
use PHPUnit\Framework\TestCase;

final class ReportTemplateTest extends TestCase
{
    public function testUploadFormOffersOptionalPdfGeneration(): void
    {
        $html = (new TemplateRenderer(dirname(__DIR__, 2) . '/templates'))->render('index', [
            'csrfToken' => 'test-token',
            'basePath' => '/pptx-checker',
        ]);

        self::assertStringContainsString('type="checkbox"', $html);
        self::assertStringContainsString('name="generate_pdf"', $html);
        self::assertStringContainsString('name="include_notes"', $html);
        self::assertStringContainsString('name="include_notes" value="1" class="form-check-input" checked', $html);
        self::assertStringContainsString('Also generate a PDF report', $html);
        self::assertStringContainsString('action="/pptx-checker/report"', $html);
        self::assertStringContainsString('href="/pptx-checker/assets/main.css"', $html);
    }

    public function testItAutomaticallyDownloadsPdfWithoutShowingAButtonWhenRequested(): void
    {
        $html = $this->render(
            'report',
            $this->createResult(1, [['number' => 1, 'title' => '', 'findings' => []]]),
            '/report/pdf?token=test-token',
        );

        self::assertStringContainsString('class="automatic-pdf-download"', $html);
        self::assertStringContainsString('/report/pdf?token=test-token', $html);
        self::assertStringNotContainsString('Download PDF report', $html);
        self::assertStringNotContainsString('btn btn-primary', $html);
    }

    public function testItDoesNotRequestAPdfWhenItWasNotSelected(): void
    {
        $html = $this->render('report', $this->createResult(1, [['number' => 1, 'title' => '', 'findings' => []]]));

        self::assertStringNotContainsString('automatic-pdf-download', $html);
        self::assertStringNotContainsString('/report/pdf?', $html);
    }

    public function testItListsImagesWithMissingAltTextInAnErrorOverview(): void
    {
        $result = $this->createResult(2, [[
            'number' => 3,
            'title' => 'Example',
            'findings' => [[
                'code' => 'image.alt.missing',
                'imageNumber' => 2,
                'filename' => '../media/chart.png',
            ]],
        ]]);

        $html = $this->render('report', $result);

        self::assertStringContainsString('review-overview error', $html);
        self::assertStringContainsString('The following images have missing descriptions/alt text', $html);
        self::assertStringContainsString('Slide 3, image 2:', $html);
        self::assertStringContainsString('../media/chart.png', $html);
        self::assertStringNotContainsString('review-score-ranges', $html);
        self::assertStringNotContainsString('review-summary', $html);
    }

    public function testItOmitsTheReviewHeaderWhenImagesHaveNoMissingAltText(): void
    {
        $result = $this->createResult(2, [['number' => 1, 'title' => '', 'findings' => []]]);

        $html = $this->render('report', $result);
        $pdfHtml = $this->render('pdf-report', $result);

        self::assertStringNotContainsString('Presentation review', $html);
        self::assertStringNotContainsString('class="review-overview', $html);
        self::assertStringNotContainsString('No images have missing descriptions/alt text.', $html);
        self::assertStringNotContainsString('Presentation review', $pdfHtml);
        self::assertStringNotContainsString('class="review-overview', $pdfHtml);
    }

    public function testItShowsNeutralMessageWhenThereAreNoImages(): void
    {
        $result = $this->createResult(0, [['number' => 1, 'title' => '', 'findings' => []]]);

        $html = $this->render('report', $result);
        $pdfHtml = $this->render('pdf-report', $result);

        self::assertStringContainsString('review-overview neutral', $html);
        self::assertStringContainsString('No images were found in the presentation.', $html);
        self::assertStringContainsString('review-overview neutral', $pdfHtml);
        self::assertStringContainsString('No images were found in the presentation.', $pdfHtml);
    }

    /** @param list<array<string, mixed>> $slides */
    private function createResult(int $imageCount, array $slides): AnalysisResult
    {
        return new AnalysisResult(
            'example.pptx',
            1,
            '',
            [],
            [
                'summary' => ['images' => $imageCount, 'errors' => 0, 'warnings' => 0, 'passed' => $imageCount, 'excluded' => 0],
                'slides' => $slides,
            ],
        );
    }

    private function render(
        string $template,
        AnalysisResult $result,
        ?string $automaticPdfDownloadUrl = null,
    ): string {
        return (new TemplateRenderer(dirname(__DIR__, 2) . '/templates'))->render($template, [
            'result' => $result,
            'unifiedReportHtml' => '<div class="slide-item">Slide content</div>',
            'automaticPdfDownloadUrl' => $automaticPdfDownloadUrl,
            'basePath' => '/pptx-checker',
        ]);
    }
}
