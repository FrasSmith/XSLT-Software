<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Http;

use RuntimeException;

/**
 * Minimal renderer for trusted PHP templates.
 *
 * Rendering is deliberately framework-free to keep deployment practical on basic
 * PHP hosting. Template names are supplied only by application code, never users.
 */
final class TemplateRenderer
{
    /** Stores the single trusted directory from which templates may be loaded. */
    public function __construct(private readonly string $templateDirectory)
    {
    }

    /**
     * Makes controller data available to a template and captures its output.
     * EXTR_SKIP prevents template data from replacing local renderer variables.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $path = $this->templateDirectory . '/' . $template . '.php';
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Template "%s" was not found.', $template));
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        return (string) ob_get_clean();
    }
}
