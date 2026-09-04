<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Http;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Effortmark\PptxChecker\Presentation\ProcessingException;

/**
 * Merges accessibility findings into the legacy XSLT content report.
 *
 * The XSLT remains unchanged for compatibility. This HTML-only adapter locates each
 * generated slide and image block, then inserts its matching review immediately
 * after the image without changing the JSON representation or content order.
 */
final class UnifiedReportRenderer
{
    /**
     * Adds per-image findings to an HTML report and marks slides without images.
     *
     * Findings and report content originate in the uploaded presentation, so all
     * inserted text is created as DOM text nodes rather than concatenated markup.
     *
     * @param array<string, mixed> $review
     */
    public function render(string $reportHtml, array $review): string
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><main id="unified-report-root">' . $reportHtml . '</main></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            throw new ProcessingException('The presentation report could not be combined with its accessibility review.');
        }

        $xpath = new DOMXPath($document);
        $root = $document->getElementById('unified-report-root');
        $slideNodes = $xpath->query("//*[@id='unified-report-root']//*[contains(concat(' ', normalize-space(@class), ' '), ' slide-item ')]");
        if (!$root instanceof DOMElement || $slideNodes === false) {
            throw new ProcessingException('The presentation report structure could not be combined with its accessibility review.');
        }

        foreach ($slideNodes as $slideIndex => $slideNode) {
            if (!$slideNode instanceof DOMElement) {
                continue;
            }

            $reviewedSlide = $review['slides'][$slideIndex] ?? ['findings' => []];
            $findings = is_array($reviewedSlide['findings'] ?? null) ? $reviewedSlide['findings'] : [];
            $imageNodes = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' image-object ')]", $slideNode);
            $images = $imageNodes === false ? [] : iterator_to_array($imageNodes);

            if ($images === []) {
                $message = $document->createElement('p');
                $message->setAttribute('class', 'review-empty');
                $message->appendChild($document->createTextNode('No images to review.'));
                $slideNode->appendChild($message);
                continue;
            }

            foreach ($images as $imageIndex => $imageNode) {
                if (!$imageNode instanceof DOMElement || !isset($findings[$imageIndex]) || !is_array($findings[$imageIndex])) {
                    continue;
                }
                $finding = $this->createFinding($document, $findings[$imageIndex]);
                $slideNode->insertBefore($finding, $imageNode->nextSibling);
            }
        }

        return $this->innerHtml($document, $root);
    }

    /** Creates the visual finding box while ensuring uploaded values remain text. */
    private function createFinding(DOMDocument $document, array $finding): DOMElement
    {
        $severity = in_array($finding['severity'] ?? '', ['error', 'warning', 'information'], true)
            ? $finding['severity']
            : 'information';
        $article = $document->createElement('article');
        $article->setAttribute('class', 'review-finding ' . $severity);

        $heading = $document->createElement('h5');
        $status = ucwords(str_replace('_', ' ', (string) ($finding['status'] ?? 'review')));
        $heading->appendChild($document->createTextNode(sprintf(
            'Image %d: %s',
            (int) ($finding['imageNumber'] ?? 0),
            $status,
        )));
        $article->appendChild($heading);


        if (($finding['score'] ?? null) !== null) {
            $score = $document->createElement('p');
            $score->setAttribute('class', 'review-score');
            $score->appendChild($document->createTextNode('Relevance score: ' . (int) $finding['score'] . '/100'));
            $article->appendChild($score);
        }

        $this->appendParagraph($document, $article, (string) ($finding['message'] ?? ''));
        $this->appendLabelledParagraph($document, $article, 'Recommendation', (string) ($finding['recommendation'] ?? ''));

        $titleTerms = is_array($finding['matchedTitleTerms'] ?? null) ? $finding['matchedTitleTerms'] : [];
        $bodyTerms = is_array($finding['matchedBodyTerms'] ?? null) ? $finding['matchedBodyTerms'] : [];
        if ($titleTerms !== [] || $bodyTerms !== []) {
            $evidence = sprintf(
                'title terms %s; body terms %s.',
                implode(', ', $titleTerms) ?: 'none',
                implode(', ', $bodyTerms) ?: 'none',
            );
            $this->appendLabelledParagraph($document, $article, 'Evidence', $evidence);
        }

        return $article;
    }

    /** Appends a label and value, optionally omitting empty optional metadata. */
    private function appendLabelledParagraph(
        DOMDocument $document,
        DOMElement $parent,
        string $label,
        string $value,
        bool $omitWhenEmpty = false,
    ): void {
        if ($omitWhenEmpty && $value === '') {
            return;
        }

        $paragraph = $document->createElement('p');
        $strong = $document->createElement('strong');
        $strong->appendChild($document->createTextNode($label . ':'));
        $paragraph->appendChild($strong);
        $paragraph->appendChild($document->createTextNode(' ' . $value));
        $parent->appendChild($paragraph);
    }

    /** Appends plain explanatory text when a reviewer supplied a message. */
    private function appendParagraph(DOMDocument $document, DOMElement $parent, string $value): void
    {
        if ($value === '') {
            return;
        }
        $paragraph = $document->createElement('p');
        $paragraph->appendChild($document->createTextNode($value));
        $parent->appendChild($paragraph);
    }

    /** Serializes only the wrapper's children, not the synthetic HTML document. */
    private function innerHtml(DOMDocument $document, DOMNode $root): string
    {
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }
        return $html;
    }
}
