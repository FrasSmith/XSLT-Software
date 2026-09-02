<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Http;

use Effortmark\PptxChecker\Presentation\ProcessingException;

/**
 * Validated view of the file metadata created by PHP's upload subsystem.
 *
 * This class verifies the temporary path with is_uploaded_file(); presentation
 * content and size validation remain the processor's responsibility.
 */
final class UploadedPresentation
{
    /** Holds the server path and display-only original filename. */
    public function __construct(
        public readonly string $temporaryPath,
        public readonly string $originalFilename,
    )
    {
    }

    /**
     * Converts the `file` multipart field into a trusted upload reference and maps
     * PHP transport failures to messages suitable for browser and API responses.
     *
     * @param array<string, mixed> $files
     */
    public static function fromFiles(array $files): self
    {
        $file = $files['file'] ?? null;
        if (!is_array($file)) {
            throw new ProcessingException('No presentation was uploaded.');
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new ProcessingException(self::uploadErrorMessage($error));
        }

        $path = (string) ($file['tmp_name'] ?? '');
        if ($path === '' || !is_uploaded_file($path)) {
            throw new ProcessingException('The presentation upload could not be verified.');
        }

        return new self($path, basename((string) ($file['name'] ?? 'presentation.pptx')));
    }

    /** Translates PHP upload error constants without exposing server details. */
    private static function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded presentation is too large.',
            UPLOAD_ERR_PARTIAL => 'The presentation upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'No presentation was uploaded.',
            default => 'The presentation could not be uploaded.',
        };
    }
}
