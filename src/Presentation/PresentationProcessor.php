<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Presentation;

use DOMDocument;
use DOMElement;
use XSLTProcessor;
use ZipArchive;

/**
 * Coordinates the complete PPTX processing pipeline.
 *
 * The processor owns the untrusted-file boundary: it validates and isolates the
 * upload, builds the combined OOXML document expected by the legacy stylesheet,
 * runs the accessibility review, and guarantees temporary-file cleanup.
 */
final class PresentationProcessor
{
    /**
     * Dependencies are injectable so the transformation and review stages can be
     * tested independently while production callers retain sensible defaults.
     */
    public function __construct(
        private readonly string $stylesheetPath,
        private readonly ProcessingLimits $limits = new ProcessingLimits(),
        private readonly ReportParser $reportParser = new ReportParser(),
        private readonly PresentationInspector $inspector = new PresentationInspector(),
        private readonly AccessibilityReviewer $reviewer = new AccessibilityReviewer(),
    ) {
    }

    /**
     * Produces both the legacy HTML content report and the structured review.
     *
     * The source file is copied before it is opened so all processing happens in
     * a private workspace controlled by this service. The caller's file is never
     * modified or deleted.
     */
    public function process(string $sourcePath, string $originalFilename): AnalysisResult
    {
        $this->assertSourceIsValid($sourcePath, $originalFilename);
        $workspace = $this->createWorkspace();
        $presentationPath = $workspace . '/presentation.pptx';

        try {
            if (!copy($sourcePath, $presentationPath)) {
                throw new ProcessingException('The uploaded presentation could not be stored for processing.');
            }

            $combinedDocument = $this->buildCombinedDocument($presentationPath);
            $presentation = $this->inspector->inspect($combinedDocument);
            $review = $this->reviewer->review($presentation);
            $reportHtml = $this->transform($combinedDocument);
            $slides = $this->reportParser->parseSlides($reportHtml);

            return new AnalysisResult(
                basename($originalFilename),
                count($slides),
                $reportHtml,
                $slides,
                $review,
            );
        } finally {
            $this->removeWorkspace($workspace);
        }
    }

    /**
     * Rejects obviously unsuitable input before allocating a processing workspace.
     * Archive structure and expanded-size checks happen later, after ZipArchive
     * has safely opened the file.
     */
    private function assertSourceIsValid(string $sourcePath, string $originalFilename): void
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new ProcessingException('No readable presentation was uploaded.');
        }

        if (strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION)) !== 'pptx') {
            throw new ProcessingException('Only .pptx files are supported.');
        }

        $size = filesize($sourcePath);
        if ($size === false || $size <= 0) {
            throw new ProcessingException('The uploaded presentation is empty.');
        }
        if ($size > $this->limits->maximumUploadBytes) {
            throw new ProcessingException('The uploaded presentation exceeds the configured size limit.');
        }

        if (!is_file($this->stylesheetPath) || !is_readable($this->stylesheetPath)) {
            throw new ProcessingException('The report stylesheet is unavailable.');
        }
    }

    /**
     * Recreates the synthetic XML format consumed by the original XSLT.
     *
     * A PPTX is a ZIP of related OOXML documents. Slides, relationships, layouts,
     * and masters are wrapped as <file> elements in one DOM so XSLT 1.0 can query
     * across document boundaries. Limits here also mitigate oversized archives.
     */
    private function buildCombinedDocument(string $presentationPath): DOMDocument
    {
        $archive = new ZipArchive();
        $openResult = $archive->open($presentationPath, ZipArchive::RDONLY);
        if ($openResult !== true) {
            throw new ProcessingException('The uploaded file is not a readable PowerPoint archive.');
        }

        try {
            if ($archive->numFiles > $this->limits->maximumArchiveEntries) {
                throw new ProcessingException('The presentation contains too many archive entries.');
            }
            if ($archive->locateName('[Content_Types].xml') === false || $archive->locateName('ppt/presentation.xml') === false) {
                throw new ProcessingException('The archive does not have the expected PowerPoint structure.');
            }

            $document = new DOMDocument('1.0', 'UTF-8');
            $files = $document->createElement('files');
            $document->appendChild($files);
            $expandedBytes = 0;

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index);
                if ($stat === false) {
                    throw new ProcessingException('A PowerPoint archive entry could not be inspected.');
                }

                $name = (string) ($stat['name'] ?? '');
                $size = (int) ($stat['size'] ?? 0);
                $expandedBytes += $size;
                if ($expandedBytes > $this->limits->maximumExpandedBytes) {
                    throw new ProcessingException('The expanded presentation exceeds the configured size limit.');
                }

                if (!$this->isSelectedXmlEntry($name)) {
                    continue;
                }
                if ($size > $this->limits->maximumXmlEntryBytes) {
                    throw new ProcessingException('A presentation XML document exceeds the configured size limit.');
                }

                $xml = $archive->getFromIndex($index);
                if ($xml === false) {
                    throw new ProcessingException(sprintf('Could not read archive entry "%s".', $name));
                }

                $entryDocument = new DOMDocument();
                $previous = libxml_use_internal_errors(true);
                try {
                    $loaded = $entryDocument->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
                if (!$loaded || !$entryDocument->documentElement instanceof DOMElement) {
                    throw new ProcessingException(sprintf('Archive entry "%s" contains invalid XML.', $name));
                }

                preg_match('/\d+/', $name, $numberMatches);
                $file = $document->createElement('file');
                $file->setAttribute('name', $name);
                $file->setAttribute('num', $numberMatches[0] ?? '0');
                $file->appendChild($document->importNode($entryDocument->documentElement, true));
                $files->appendChild($file);
            }

            return $document;
        } finally {
            $archive->close();
        }
    }

    /**
     * Selects only OOXML parts needed by the stylesheet and accessibility inspector.
     * Media binaries are deliberately excluded because neither stage decodes images.
     */
    private function isSelectedXmlEntry(string $name): bool
    {
        if ($name === '' || str_ends_with($name, '/')) {
            return false;
        }

        return str_contains($name, 'ppt/slideLayouts')
            || str_contains($name, 'ppt/slides')
            || str_contains($name, 'ppt/slideMasters');
    }

    /**
     * Applies the unchanged legacy stylesheet with all XSLT file and network access
     * disabled. The stylesheet needs only the in-memory combined document.
     */
    private function transform(DOMDocument $source): string
    {
        $stylesheet = new DOMDocument();
        if (!$stylesheet->load($this->stylesheetPath, LIBXML_NONET)) {
            throw new ProcessingException('The report stylesheet could not be parsed.');
        }

        $processor = new XSLTProcessor();
        $processor->setSecurityPrefs(
            XSL_SECPREF_READ_FILE
            | XSL_SECPREF_WRITE_FILE
            | XSL_SECPREF_CREATE_DIRECTORY
            | XSL_SECPREF_READ_NETWORK
            | XSL_SECPREF_WRITE_NETWORK,
        );
        if (!$processor->importStylesheet($stylesheet)) {
            throw new ProcessingException('The report stylesheet could not be loaded.');
        }

        $result = $processor->transformToXML($source);
        if ($result === false) {
            throw new ProcessingException('The presentation report could not be generated.');
        }

        return $result;
    }

    /**
     * Creates an unpredictable, owner-only directory to prevent filename collisions
     * and cross-request access on shared servers.
     */
    private function createWorkspace(): string
    {
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'pptx-checker-'
            . bin2hex(random_bytes(16));

        if (!mkdir($path, 0700)) {
            throw new ProcessingException('A private processing directory could not be created.');
        }

        return $path;
    }

    /**
     * Best-effort cleanup used from a finally block on every processing path.
     * Suppressed cleanup errors must not hide the report or the original failure.
     */
    private function removeWorkspace(string $workspace): void
    {
        if (!is_dir($workspace)) {
            return;
        }

        foreach (scandir($workspace) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $workspace . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
        }
        @rmdir($workspace);
    }
}
