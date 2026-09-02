<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Presentation;

/**
 * Converts primarily English prose into comparable, deterministic search terms.
 *
 * This is intentionally lightweight: it removes noise and handles common suffixes
 * without pretending to perform full linguistic or semantic analysis.
 */
final class EnglishTextNormalizer
{
    /** @var array<string, true> */
    private const STOP_WORDS = [
        'a' => true, 'an' => true, 'and' => true, 'are' => true, 'as' => true, 'at' => true,
        'be' => true, 'by' => true, 'for' => true, 'from' => true, 'has' => true, 'have' => true,
        'in' => true, 'is' => true, 'it' => true, 'of' => true, 'on' => true, 'or' => true,
        'that' => true, 'the' => true, 'this' => true, 'to' => true, 'was' => true, 'were' => true,
        'with' => true,
    ];

    /** @var array<string, true> */
    private const GENERIC_WORDS = [
        'alt' => true, 'chart' => true, 'description' => true, 'diagram' => true, 'figure' => true,
        'graphic' => true, 'icon' => true, 'image' => true, 'logo' => true, 'photo' => true,
        'photograph' => true, 'picture' => true, 'screen' => true, 'screenshot' => true,
        'show' => true, 'showing' => true, 'text' => true,
    ];

    /**
     * Produces unique lowercase terms for relevance scoring. Generic image words
     * may be removed when processing alt text because they add no topic evidence.
     *
     * @return list<string>
     */
    public function tokens(string $text, bool $removeGenericWords = false): array
    {
        $normalised = strtolower(str_replace(['%', '&'], [' percent ', ' and '], $text));
        $parts = preg_split('/[^a-z0-9]+/i', $normalised, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            if (isset(self::STOP_WORDS[$part]) || ($removeGenericWords && isset(self::GENERIC_WORDS[$part]))) {
                continue;
            }
            $token = $this->stem($part);
            if ($token !== '' && !in_array($token, $tokens, true)) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    /**
     * Identifies descriptions that are only a media filename or generic object label.
     * Empty descriptions are handled separately as the more specific missing error.
     */
    public function isClearlyGeneric(string $description, string $filename): bool
    {
        $trimmed = trim($description);
        if ($trimmed === '') {
            return false;
        }

        $basename = strtolower(pathinfo($filename, PATHINFO_FILENAME));
        $descriptionWithoutExtension = strtolower(pathinfo($trimmed, PATHINFO_FILENAME));
        if ($basename !== '' && $descriptionWithoutExtension === $basename) {
            return true;
        }
        if (preg_match('/^(img|image|picture|photo|screenshot)[-_ ]*\d*(\.[a-z0-9]{2,5})?$/i', $trimmed) === 1) {
            return true;
        }

        return $this->tokens($trimmed, true) === [];
    }

    /**
     * Reduces a small set of frequent English suffixes so grammatical variants can
     * match. Conservative rules favour missed matches over unrelated matches.
     */
    private function stem(string $word): string
    {
        if (strlen($word) <= 3 || ctype_digit($word)) {
            return $word;
        }

        foreach (['ingly', 'edly', 'ation', 'ments', 'ment', 'ness', 'ing', 'ers', 'ies', 'ed', 'es', 's'] as $suffix) {
            if (str_ends_with($word, $suffix) && strlen($word) - strlen($suffix) >= 3) {
                $stem = substr($word, 0, -strlen($suffix));
                return $suffix === 'ies' ? $stem . 'y' : $stem;
            }
        }

        return $word;
    }
}
