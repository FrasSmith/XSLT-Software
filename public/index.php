<?php

declare(strict_types=1);

/**
 * Framework-free HTTP front controller.
 *
 * Browser and API routes share the same processor so validation, review rules, and
 * privacy guarantees cannot drift. Expected processing errors return 422; unknown
 * failures are logged server-side and presented as generic 500 responses.
 */

use Effortmark\PptxChecker\Http\PdfReportGenerator;
use Effortmark\PptxChecker\Http\TemplateRenderer;
use Effortmark\PptxChecker\Http\TemporaryPdfStore;
use Effortmark\PptxChecker\Http\UnifiedReportRenderer;
use Effortmark\PptxChecker\Http\UploadedPresentation;
use Effortmark\PptxChecker\Presentation\PresentationProcessor;
use Effortmark\PptxChecker\Presentation\ProcessingException;
use Effortmark\PptxChecker\Presentation\ProcessingLimits;

$configuration = [
    'applicationRoot' => dirname(__DIR__),
    'basePath' => '',
];
$deploymentConfiguration = __DIR__ . '/deployment.php';
if (is_file($deploymentConfiguration)) {
    $overrides = require $deploymentConfiguration;
    if (!is_array($overrides)) {
        throw new RuntimeException('The deployment configuration must return an array.');
    }
    $configuration = array_replace($configuration, $overrides);
}

$applicationRoot = rtrim((string) $configuration['applicationRoot'], DIRECTORY_SEPARATOR);
$basePath = '/' . trim((string) $configuration['basePath'], '/');
$basePath = $basePath === '/' ? '' : $basePath;
require $applicationRoot . '/vendor/autoload.php';

// The browser form uses a session-backed CSRF token; API clients authenticate
// independently at the deployment layer and therefore do not use browser sessions.
session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
    'path' => $basePath === '' ? '/' : $basePath . '/',
]);
session_start();

$renderer = new TemplateRenderer($applicationRoot . '/templates');
$renderTemplate = static fn (string $template, array $data = []): string => $renderer->render(
    $template,
    ['basePath' => $basePath] + $data,
);
$unifiedReportRenderer = new UnifiedReportRenderer();
$pdfReportGenerator = new PdfReportGenerator();
$pdfStore = new TemporaryPdfStore();
$pdfDownloads = is_array($_SESSION['pdf_downloads'] ?? null) ? $_SESSION['pdf_downloads'] : [];
$pdfStore->removeExpired($pdfDownloads);
$_SESSION['pdf_downloads'] = $pdfDownloads;
// Environment overrides let shared-host deployments tune limits without editing code.
$limits = new ProcessingLimits(
    maximumUploadBytes: (int) (getenv('PPTX_MAX_UPLOAD_BYTES') ?: 52_428_800),
    maximumArchiveEntries: (int) (getenv('PPTX_MAX_ARCHIVE_ENTRIES') ?: 5_000),
    maximumExpandedBytes: (int) (getenv('PPTX_MAX_EXPANDED_BYTES') ?: 524_288_000),
    maximumXmlEntryBytes: (int) (getenv('PPTX_MAX_XML_ENTRY_BYTES') ?: 52_428_800),
);
$processor = new PresentationProcessor($applicationRoot . '/resources/pptx.xsl', $limits);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = $requestPath;
if ($basePath !== '') {
    if ($requestPath === $basePath || $requestPath === $basePath . '/') {
        $path = '/';
    } elseif (str_starts_with($requestPath, $basePath . '/')) {
        $path = substr($requestPath, strlen($basePath));
    }
}

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

/** Sends a generated PDF as a one-time attachment. */
$sendPdf = static function (string $pdf, string $filename): never {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store, private');
    echo $pdf;
    exit;
};

try {
    // Route matching remains explicit to avoid a framework dependency.
    if ($method === 'GET' && $path === '/report/pdf') {
        $token = (string) ($_GET['token'] ?? '');
        $download = $_SESSION['pdf_downloads'][$token] ?? null;
        if (!is_array($download)) {
            throw new ProcessingException('This PDF download link is invalid or has expired.');
        }

        $pdfPath = (string) ($download['path'] ?? '');
        $pdf = $pdfPath !== '' && is_file($pdfPath) ? file_get_contents($pdfPath) : false;
        $pdfStore->removeFile($pdfPath);
        unset($_SESSION['pdf_downloads'][$token]);
        if ($pdf === false) {
            throw new ProcessingException('This PDF download is no longer available.');
        }

        $sendPdf($pdf, (string) ($download['filename'] ?? 'accessibility-report.pdf'));
    }

    if ($method === 'GET' && $path === '/') {
        $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
        $sendHtml($renderTemplate('index', ['csrfToken' => $_SESSION['csrf_token']]));
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
        $automaticPdfDownloadUrl = null;

        if ((string) ($_POST['generate_pdf'] ?? '') === '1') {
            $pdfHtml = $renderTemplate('pdf-report', [
                'result' => $result,
                'unifiedReportHtml' => $unifiedReportHtml,
            ]);
            $token = bin2hex(random_bytes(32));
            $_SESSION['pdf_downloads'][$token] = $pdfStore->store(
                $pdfReportGenerator->generate($pdfHtml),
                $result->filename,
            );
            $automaticPdfDownloadUrl = $basePath . '/report/pdf?token=' . rawurlencode($token);
        }

        $sendHtml($renderTemplate('report', [
            'result' => $result,
            'unifiedReportHtml' => $unifiedReportHtml,
            'automaticPdfDownloadUrl' => $automaticPdfDownloadUrl,
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

    $sendHtml($renderTemplate('error', ['message' => 'Page not found.']), 404);
} catch (ProcessingException $exception) {
    if (str_starts_with($path, '/api/')) {
        $sendJson(['error' => $exception->getMessage()], 422);
    }
    $sendHtml($renderTemplate('error', ['message' => $exception->getMessage()]), 422);
} catch (Throwable $exception) {
    error_log($exception->__toString());
    if (str_starts_with($path, '/api/')) {
        $sendJson(['error' => 'An unexpected error occurred.'], 500);
    }
    $sendHtml($renderTemplate('error', ['message' => 'An unexpected error occurred.']), 500);
}
