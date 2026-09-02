<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Presentation;

/**
 * Immutable resource limits for processing untrusted compressed presentations.
 *
 * Upload size alone is insufficient because a small ZIP can expand dramatically;
 * entry count, expanded bytes, and individual XML size are limited independently.
 */
final class ProcessingLimits
{
    public function __construct(
        public readonly int $maximumUploadBytes = 52_428_800,
        public readonly int $maximumArchiveEntries = 5_000,
        public readonly int $maximumExpandedBytes = 524_288_000,
        public readonly int $maximumXmlEntryBytes = 52_428_800,
    ) {
    }
}
