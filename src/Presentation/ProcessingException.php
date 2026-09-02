<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Presentation;

use RuntimeException;

/**
 * Represents an expected, user-actionable upload or presentation failure.
 *
 * The HTTP layer exposes these messages with status 422 while hiding unexpected
 * internal exceptions behind a generic 500 response.
 */
final class ProcessingException extends RuntimeException
{
}
