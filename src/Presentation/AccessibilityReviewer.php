<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Presentation;

/** Reports only non-decorative images that have no alternative text. */
final class AccessibilityReviewer
{
    /**
     * @param list<array{number: int, title: string, visibleText: string, images: list<array{filename: string, description: string, decorative: bool, groupId?: string|null}>}> $slides
     * @return array{summary: array{images: int, errors: int, warnings: int, passed: int, excluded: int}, slides: list<array{number: int, title: string, findings: list<array<string, mixed>>}>}
     */
    public function review(array $slides): array
    {
        $summary = ['images' => 0, 'errors' => 0, 'warnings' => 0, 'passed' => 0, 'excluded' => 0];
        $reviewedSlides = [];

        foreach ($slides as $slide) {
            $findings = [];
            $describedGroups = [];
            foreach ($slide['images'] as $image) {
                $groupId = $image['groupId'] ?? null;
                if ($groupId !== null && trim($image['description']) !== '') {
                    $describedGroups[$groupId] = true;
                }
            }

            foreach ($slide['images'] as $imageIndex => $image) {
                $summary['images']++;

                if ($image['decorative']) {
                    $summary['excluded']++;
                    continue;
                }

                $groupId = $image['groupId'] ?? null;
                if (trim($image['description']) !== '' || ($groupId !== null && isset($describedGroups[$groupId]))) {
                    $summary['passed']++;
                    continue;
                }

                $summary['errors']++;
                $findings[] = [
                    'imageNumber' => $imageIndex + 1,
                    'filename' => $image['filename'],
                    'description' => $image['description'],
                    'decorative' => false,
                    'code' => 'image.alt.missing',
                    'status' => 'missing',
                    'severity' => 'error',
                    'score' => null,
                    'message' => 'This image does not have alternative text.',
                    'recommendation' => 'Add a concise description of the image’s purpose or important information.',
                    'matchedTitleTerms' => [],
                    'matchedBodyTerms' => [],
                ];
            }

            $reviewedSlides[] = [
                'number' => $slide['number'],
                'title' => $slide['title'],
                'findings' => $findings,
            ];
        }

        return ['summary' => $summary, 'slides' => $reviewedSlides];
    }
}
