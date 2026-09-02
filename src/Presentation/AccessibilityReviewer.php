<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Presentation;

/**
 * Applies deterministic, explainable checks to image alternative text.
 *
 * Relevance is lexical evidence rather than a semantic judgement. Low scores ask
 * for human review and never claim that an otherwise meaningful description is
 * wrong. No AI model, external API, or presentation content leaves the server.
 */
final class AccessibilityReviewer
{
    /** @var list<list<string>> */
    private const SYNONYM_GROUPS = [
        ['revenue', 'sale', 'income', 'turnover'],
        ['increase', 'growth', 'rise', 'grew', 'higher'],
        ['decrease', 'decline', 'fall', 'lower', 'reduction'],
        ['customer', 'client', 'consumer', 'user'],
        ['employee', 'staff', 'worker', 'team'],
    ];

    /** Uses a replaceable normalizer to keep language policy outside scoring. */
    public function __construct(private readonly EnglishTextNormalizer $normalizer = new EnglishTextNormalizer())
    {
    }

    /**
     * Reviews every image and accumulates counts suitable for HTML and API clients.
     * Decorative images are recorded but excluded from error and relevance checks.
     *
     * @param list<array{number: int, title: string, visibleText: string, images: list<array{filename: string, description: string, decorative: bool}>}> $slides
     * @return array{summary: array{images: int, errors: int, warnings: int, passed: int, excluded: int}, slides: list<array{number: int, title: string, findings: list<array<string, mixed>>}>}
     */
    public function review(array $slides): array
    {
        $summary = ['images' => 0, 'errors' => 0, 'warnings' => 0, 'passed' => 0, 'excluded' => 0];
        $reviewedSlides = [];

        foreach ($slides as $slide) {
            $findings = [];
            foreach ($slide['images'] as $imageIndex => $image) {
                $summary['images']++;
                $finding = $this->reviewImage($slide, $image, $imageIndex + 1);
                $findings[] = $finding;
                match ($finding['severity']) {
                    'error' => $summary['errors']++,
                    'warning' => $summary['warnings']++,
                    'information' => $finding['status'] === 'excluded' ? $summary['excluded']++ : $summary['passed']++,
                    default => null,
                };
            }

            $reviewedSlides[] = [
                'number' => $slide['number'],
                'title' => $slide['title'],
                'findings' => $findings,
            ];
        }

        return ['summary' => $summary, 'slides' => $reviewedSlides];
    }

    /**
     * Applies rules from most objective to least objective: decorative, missing,
     * generic, and finally relevance. This prevents weak heuristic scores from
     * obscuring clear accessibility failures.
     *
     * @param array{number: int, title: string, visibleText: string, images: array} $slide
     * @param array{filename: string, description: string, decorative: bool} $image
     * @return array<string, mixed>
     */
    private function reviewImage(array $slide, array $image, int $imageNumber): array
    {
        $base = [
            'imageNumber' => $imageNumber,
            'filename' => $image['filename'],
            'description' => $image['description'],
            'decorative' => $image['decorative'],
        ];

        if ($image['decorative']) {
            return $base + [
                'code' => 'image.alt.decorative', 'status' => 'excluded', 'severity' => 'information', 'score' => null,
                'message' => 'Image is marked as decorative in PowerPoint.',
                'recommendation' => 'No alternative text review is required unless the image conveys information.',
                'matchedTitleTerms' => [], 'matchedBodyTerms' => [],
            ];
        }

        if (trim($image['description']) === '') {
            return $base + [
                'code' => 'image.alt.missing', 'status' => 'missing', 'severity' => 'error', 'score' => null,
                'message' => 'This image does not have alternative text.',
                'recommendation' => 'Add a concise description of the image’s purpose or important information.',
                'matchedTitleTerms' => [], 'matchedBodyTerms' => [],
            ];
        }

        if ($this->normalizer->isClearlyGeneric($image['description'], $image['filename'])) {
            return $base + [
                'code' => 'image.alt.inadequate', 'status' => 'inadequate', 'severity' => 'error', 'score' => 0,
                'message' => 'The alternative text is only a generic label or filename.',
                'recommendation' => 'Replace it with a description of the image’s purpose or important information.',
                'matchedTitleTerms' => [], 'matchedBodyTerms' => [],
            ];
        }

        return $base + $this->relevanceFinding($image['description'], $slide['title'], $slide['visibleText']);
    }

    /**
     * Scores overlap against the title first and body second. The denominator uses
     * the strongest possible title match, yielding a stable 0–100 score while the
     * returned matched terms make the result auditable.
     *
     * @return array<string, mixed>
     */
    private function relevanceFinding(string $description, string $title, string $visibleText): array
    {
        $altTokens = $this->normalizer->tokens($description, true);
        $titleTokens = $this->normalizer->tokens($title);
        $bodyTokens = $this->normalizer->tokens($visibleText);
        $matchedTitle = [];
        $matchedBody = [];
        $points = 0.0;

        foreach ($altTokens as $token) {
            if (in_array($token, $titleTokens, true)) {
                $points += 3.0;
                $matchedTitle[] = $token;
                continue;
            }
            if (in_array($token, $bodyTokens, true)) {
                $points += 2.0;
                $matchedBody[] = $token;
                continue;
            }
            if ($this->hasSynonymMatch($token, $titleTokens)) {
                $points += 1.5;
                $matchedTitle[] = $token . ' (related term)';
                continue;
            }
            if ($this->hasSynonymMatch($token, $bodyTokens)) {
                $points += 1.0;
                $matchedBody[] = $token . ' (related term)';
            }
        }

        $score = $altTokens === [] ? 0 : (int) round(min(100, ($points / (count($altTokens) * 3)) * 100));
        if ($score >= 70) {
            return [
                'code' => 'image.alt.relevance.strong', 'status' => 'possibly_relevant', 'severity' => 'information', 'score' => $score,
                'message' => 'The description has strong textual links to the visible slide content.',
                'recommendation' => 'Confirm manually that it communicates the image’s important information.',
                'matchedTitleTerms' => $matchedTitle, 'matchedBodyTerms' => $matchedBody,
            ];
        }
        if ($score >= 35) {
            return [
                'code' => 'image.alt.relevance.some', 'status' => 'possibly_relevant', 'severity' => 'information', 'score' => $score,
                'message' => 'The description has some textual links to the visible slide content.',
                'recommendation' => 'Confirm manually that it explains the image’s purpose in this slide.',
                'matchedTitleTerms' => $matchedTitle, 'matchedBodyTerms' => $matchedBody,
            ];
        }

        return [
            'code' => 'image.alt.relevance.review', 'status' => 'review_recommended', 'severity' => 'warning', 'score' => $score,
            'message' => 'Limited textual evidence connects this description to the visible slide content.',
            'recommendation' => 'Review it manually. A low score does not necessarily mean the description is incorrect.',
            'matchedTitleTerms' => $matchedTitle, 'matchedBodyTerms' => $matchedBody,
        ];
    }

    /**
     * Adds limited domain-independent vocabulary links without a probabilistic
     * model. Keeping this list small reduces accidental matches and false alarms.
     *
     * @param list<string> $candidateTokens
     */
    private function hasSynonymMatch(string $token, array $candidateTokens): bool
    {
        foreach (self::SYNONYM_GROUPS as $group) {
            if (in_array($token, $group, true) && array_intersect($group, $candidateTokens) !== []) {
                return true;
            }
        }
        return false;
    }
}
