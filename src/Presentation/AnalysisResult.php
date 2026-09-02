<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Presentation;

use JsonSerializable;

/**
 * Immutable value returned by the processor and serialized by the JSON endpoint.
 *
 * It carries both legacy report fields and the additive accessibility review so
 * existing integrations can continue consuming the original response shape.
 */
final class AnalysisResult implements JsonSerializable
{
    /**
     * @param list<array{number: int, text: string, images: list<array{filename: string, description: string}>}> $slides
     * @param array<string, mixed> $review
     */
    public function __construct(
        public readonly string $filename,
        public readonly int $slideCount,
        public readonly string $reportHtml,
        public readonly array $slides,
        public readonly array $review,
    ) {
    }

    /**
     * Exposes a predictable API representation without leaking implementation state.
     *
     * @return array{filename: string, slideCount: int, reportHtml: string, slides: array, review: array} */
    public function jsonSerialize(): array
    {
        return [
            'filename' => $this->filename,
            'slideCount' => $this->slideCount,
            'reportHtml' => $this->reportHtml,
            'slides' => $this->slides,
            'review' => $this->review,
        ];
    }
}
