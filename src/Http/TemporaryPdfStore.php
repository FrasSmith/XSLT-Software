<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Http;

use RuntimeException;

/** Stores generated PDFs privately until they expire or are downloaded once. */
final class TemporaryPdfStore
{
    public function __construct(
        private readonly string $directory = '',
        private readonly int $lifetimeSeconds = 900,
    ) {
    }

    /** @return array{path: string, filename: string, expiresAt: int} */
    public function store(string $pdf, string $sourceFilename): array
    {
        $directory = $this->directory !== '' ? $this->directory : sys_get_temp_dir();
        $path = rtrim($directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'pptx-report-'
            . bin2hex(random_bytes(16))
            . '.pdf';

        if (file_put_contents($path, $pdf, LOCK_EX) === false || !chmod($path, 0600)) {
            @unlink($path);
            throw new RuntimeException('The temporary PDF report could not be stored.');
        }

        $base = pathinfo(basename($sourceFilename), PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?: 'presentation';

        return [
            'path' => $path,
            'filename' => trim($safeBase, '-') . '-accessibility-report.pdf',
            'expiresAt' => time() + $this->lifetimeSeconds,
        ];
    }

    /** @param array<string, array{path?: string, filename?: string, expiresAt?: int}> $downloads */
    public function removeExpired(array &$downloads, ?int $now = null): void
    {
        $now ??= time();
        foreach ($downloads as $token => $download) {
            if ((int) ($download['expiresAt'] ?? 0) > $now) {
                continue;
            }
            $this->removeFile((string) ($download['path'] ?? ''));
            unset($downloads[$token]);
        }
    }

    public function removeFile(string $path): void
    {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}
