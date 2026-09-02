<?php

declare(strict_types=1);

/**
 * Framework-free HTTP front controller.
 *
 * Browser and API routes share the same processor so validation, review rules, and
 * privacy guarantees cannot drift. Expected processing errors return 422; unknown
 * failures are logged server-side and presented as generic 500 responses.
 */

use Effortmark\PptxChecker\Http\TemplateRenderer;
use Effortmark\PptxChecker\Http\UnifiedReportRenderer;
use Effortmark\PptxChecker\Http\UploadedPresentation;
use Effortmark\PptxChecker\Presentation\PresentationProcessor;
use Effortmark\PptxChecker\Presentation\ProcessingException;
use Effortmark\PptxChecker\Presentation\ProcessingLimits;

require dirname(__DIR__) . '/vendor/autoload.php';

// The browser form uses a session-backed CSRF token; API clients authenticate
// independently at the deployment layer and therefore do not use browser sessions.
session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

$renderer = new TemplateRenderer(dirname(__DIR__) . '/templates');
$unifiedReportRenderer = new UnifiedReportRenderer();
// Environment overrides let shared-host deployments tune limits without editing code.
$limits = new ProcessingLimits(
    maximumUploadBytes: (int) (getenv('PPTX_MAX_UPLOAD_BYTES') ?: 52_428_800),
    maximumArchiveEntries: (int) (getenv('PPTX_MAX_ARCHIVE_ENTRIES') ?: 5_000),
    maximumExpandedBytes: (int) (getenv('PPTX_MAX_EXPANDED_BYTES') ?: 524_288_000),
    maximumXmlEntryBytes: (int) (getenv('PPTX_MAX_XML_ENTRY_BYTES') ?: 52_428_800),
);
$processor = new PresentationProcessor(dirname(__DIR__) . '/resources/pptx.xsl', $limits);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

/** Sends a complete HTML response and stops route processing. */
$sendHtml = static function (string $html, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
};

/** Sends machine-readable API data using exceptions for encoding failures. */
$sendJson = static function (array|JsonSerializable $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

try {
    // Route matching remains explicit to avoid a framework dependency.
    if ($method === 'GET' && $path === '/') {
        $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
        $sendHtml($renderer->render('index', ['csrfToken' => $_SESSION['csrf_token']]));
    }

    if ($method === 'POST' && $path === '/report') {
        $submittedToken = (string) ($_POST['csrf_token'] ?? '');
        $storedToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($storedToken === '' || !hash_equals($storedToken, $submittedToken)) {
            throw new ProcessingException('The form session expired. Please return to the upload page and try again.');
        }

        $upload = UploadedPresentation::fromFiles($_FILES);
        $result = $processor->process($upload->temporaryPath, $upload->originalFilename);
        $unifiedReportHtml = $unifiedReportRenderer->render($result->reportHtml, $result->review);
        $sendHtml($renderer->render('report', [
            'result' => $result,
            'unifiedReportHtml' => $unifiedReportHtml,
        ]));
    }

    if ($method === 'POST' && $path === '/api/v1/analyse') {
        $upload = UploadedPresentation::fromFiles($_FILES);
        $result = $processor->process($upload->temporaryPath, $upload->originalFilename);
        $sendJson($result);
    }

    if (str_starts_with($path, '/api/')) {
        $sendJson(['error' => 'Endpoint not found.'], 404);
    }

    $sendHtml($renderer->render('error', ['message' => 'Page not found.']), 404);
} catch (ProcessingException $exception) {
    if (str_starts_with($path, '/api/')) {
        $sendJson(['error' => $exception->getMessage()], 422);
    }
    $sendHtml($renderer->render('error', ['message' => $exception->getMessage()]), 422);
} catch (Throwable $exception) {
    error_log($exception->__toString());
    if (str_starts_with($path, '/api/')) {
        $sendJson(['error' => 'An unexpected error occurred.'], 500);
    }
    $sendHtml($renderer->render('error', ['message' => 'An unexpected error occurred.']), 500);
}
