<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Presentation;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Converts the legacy XSLT HTML fragment into the API's backward-compatible fields.
 *
 * Accessibility findings do not use this parser; they come from OOXML directly.
 * Keeping this adapter separate allows the old response shape to remain stable.
 */
final class ReportParser
{
    /**
     * Extracts slide text and image labels from the generated HTML fragment. Libxml
     * warnings are isolated because XSLT emits an HTML fragment rather than a full
     * modern HTML document.
     *
     * @return list<array{number: int, text: string, images: list<array{filename: string, description: string}>}>
     */
    public function parseSlides(string $reportHtml): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><main id="report-root">' . $reportHtml . '</main></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            throw new ProcessingException('The generated report could not be parsed.');
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query("//*[@id='report-root']//*[contains(concat(' ', normalize-space(@class), ' '), ' slide-item ')]");
        if ($nodes === false) {
            throw new ProcessingException('The generated report could not be queried.');
        }

        $slides = [];
        foreach ($nodes as $index => $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $images = [];
            $imageNodes = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' image-object ')]", $node);
            if ($imageNodes !== false) {
                foreach ($imageNodes as $imageNode) {
                    $value = $this->readableText($imageNode);
                    preg_match('/^Image:\s*(.*?)\s*Description\/Alt-text:\s*(.*)$/s', $value, $matches);
                    $images[] = [
                        'filename' => trim($matches[1] ?? ''),
                        'description' => trim($matches[2] ?? ''),
                    ];
                }
            }

            $slides[] = [
                'number' => $index + 1,
                'text' => $this->readableText($node),
                'images' => $images,
            ];
        }

        return $slides;
    }

    /** Flattens visible HTML text while retaining separators represented by markup. */
    private function readableText(DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if ($tag === 'br') {
                    $text .= ' ';
                    continue;
                }

                $childText = $this->readableText($child);
                if (in_array($tag, ['address', 'article', 'blockquote', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'p', 'section', 'td', 'th'], true)) {
                    $text .= ' ' . $childText . ' ';
                } else {
                    $text .= $childText;
                }
                continue;
            }

            $text .= $child->nodeValue ?? '';
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
