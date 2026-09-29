<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Presentation;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Extracts the small semantic model required by accessibility rules from OOXML.
 *
 * This intentionally runs before XSLT because the HTML report discards metadata
 * such as decorative-image markers and relationship identifiers.
 */
final class PresentationInspector
{
    private const PRESENTATION_NS = 'http://schemas.openxmlformats.org/presentationml/2006/main';
    private const DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const RELATIONSHIP_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * Reads slides in numeric order and captures titles, visible text, and pictures.
     * Image binaries are not loaded; their relationship targets identify them.
     *
     * @return list<array{number: int, title: string, visibleText: string, images: list<array{filename: string, description: string, decorative: bool, groupId: string|null, groupDescription: string}>}>
     */
    public function inspect(DOMDocument $document): array
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('p', self::PRESENTATION_NS);
        $xpath->registerNamespace('a', self::DRAWING_NS);
        $xpath->registerNamespace('r', self::RELATIONSHIP_NS);

        $nodes = $xpath->query("/files/file[contains(@name, 'ppt/slides/slide') and not(contains(@name, '/_rels/'))]");
        if ($nodes === false) {
            throw new ProcessingException('The presentation slides could not be inspected.');
        }

        $slideNodes = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $slideNodes[] = $node;
            }
        }
        usort($slideNodes, static fn (DOMElement $left, DOMElement $right): int => (int) $left->getAttribute('num') <=> (int) $right->getAttribute('num'));

        $slides = [];
        foreach ($slideNodes as $position => $slideNode) {
             $number = (int) $slideNode->getAttribute('num');
            $titleNodes = $xpath->query(".//p:sp[.//p:ph[@type='title' or @type='ctrTitle']]//a:t", $slideNode);
            $textNodes = $xpath->query('.//a:t', $slideNode);
            $pictureNodes = $xpath->query('.//p:pic', $slideNode);
            $notesNodes = $xpath->query(
                sprintf("/files/file[@name='ppt/notesSlides/notesSlide%d.xml']//p:sp[.//p:ph[@type='body' or @type='obj']]//a:t", $number),
            );

            $images = [];

            if ($pictureNodes !== false) {
                foreach ($pictureNodes as $pictureNode) {
                    if (!$pictureNode instanceof DOMElement) {
                        continue;
                    }
                    $relationshipId = $this->firstValue($xpath, './/a:blip/@r:embed', $pictureNode);
                    $images[] = [
                        'filename' => $this->relationshipTarget($xpath, $document, $number, $relationshipId),
                        'description' => trim($this->firstValue($xpath, './/p:cNvPr/@descr', $pictureNode)),
                        'decorative' => $this->isDecorative($xpath, $pictureNode),
                        'groupId' => $this->groupId($xpath, $pictureNode, $number),
                        'groupDescription' => $this->groupDescription($xpath, $pictureNode),
                    ];
                }
            }

            $slides[] = [
                'title' => $this->joinValues($titleNodes === false ? [] : iterator_to_array($titleNodes)),
                'visibleText' => $this->joinValues($textNodes === false ? [] : iterator_to_array($textNodes)),
                'images' => $images,
                'notes' => $this->joinValues($notesNodes === false ? [] : iterator_to_array($notesNodes)),
                'number' => $position + 1,
            ];
        }

        return $slides;
    }

    /**
     * Resolves an image's local relationship ID through the current slide's
     * relationship document. Relationship IDs are scoped to each slide.
     */
    private function relationshipTarget(DOMXPath $xpath, DOMDocument $document, int $slideNumber, string $relationshipId): string
    {
        if ($relationshipId === '') {
            return '';
        }

        foreach ($document->getElementsByTagName('file') as $file) {
            if (!$file instanceof DOMElement || $file->getAttribute('name') !== sprintf('ppt/slides/_rels/slide%d.xml.rels', $slideNumber)) {
                continue;
            }

            $relationships = $xpath->query('.//*[@Id]', $file);
            if ($relationships === false) {
                return '';
            }
            foreach ($relationships as $relationship) {
                if ($relationship instanceof DOMElement && $relationship->getAttribute('Id') === $relationshipId) {
                    return $relationship->getAttribute('Target');
                }
            }
        }

        return '';
    }

    /** Returns a slide-scoped identifier for the outermost containing picture group. */
    private function groupId(DOMXPath $xpath, DOMElement $picture, int $slideNumber): ?string
    {
        $group = $this->outermostGroup($picture);
        if (!$group instanceof DOMElement) {
            return null;
        }

        $id = $this->firstValue($xpath, './p:nvGrpSpPr/p:cNvPr/@id', $group);
        return sprintf('slide%d-group-%s', $slideNumber, $id !== '' ? $id : spl_object_id($group));
    }

    /** Returns alt text assigned to the outer group rather than an individual image. */
    private function groupDescription(DOMXPath $xpath, DOMElement $picture): string
    {
        $group = $this->outermostGroup($picture);
        return $group instanceof DOMElement
            ? trim($this->firstValue($xpath, './p:nvGrpSpPr/p:cNvPr/@descr', $group))
            : '';
    }

    private function outermostGroup(DOMElement $picture): ?DOMElement
    {
        $group = null;
        $ancestor = $picture->parentNode;
        while ($ancestor instanceof DOMElement) {
            if ($ancestor->namespaceURI === self::PRESENTATION_NS && $ancestor->localName === 'grpSp') {
                $group = $ancestor;
            }
            $ancestor = $ancestor->parentNode;
        }
        return $group;
    }

    /**
     * Recognises PowerPoint's extension-based decorative marker without depending
     * on a particular namespace prefix, which Office producers may rename.
     */
    private function isDecorative(DOMXPath $xpath, DOMElement $picture): bool
    {
        $nodes = $xpath->query(".//*[local-name()='decorative']", $picture);
        if ($nodes === false) {
            return false;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $value = strtolower($node->getAttribute('val'));
            if ($value === '' || $value === '1' || $value === 'true') {
                return true;
            }
        }

        return false;
    }

    /** Returns the first XPath value or an empty string for optional metadata. */
    private function firstValue(DOMXPath $xpath, string $query, DOMNode $context): string
    {
        $nodes = $xpath->query($query, $context);
        return $nodes !== false && $nodes->length > 0 ? (string) $nodes->item(0)?->nodeValue : '';
    }

    /**
     * Flattens DrawingML text runs while preserving their document order.
     *
     * @param iterable<DOMNode> $nodes
     */
    private function joinValues(iterable $nodes): string
    {
        $values = [];
        foreach ($nodes as $node) {
            $value = trim((string) $node->nodeValue);
            if ($value !== '') {
                $values[] = $value;
            }
        }
        return implode(' ', $values);
    }
}
