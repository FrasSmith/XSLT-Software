<?php

declare(strict_types=1);

namespace Effortmark\PptxChecker\Http;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

/** Renders the trusted HTML report as an A4 portrait PDF. */
final class PdfReportGenerator
{
    public function generate(string $html): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $pdf = $dompdf->output();
        if ($pdf === '' || !str_starts_with($pdf, '%PDF-')) {
            throw new RuntimeException('The PDF report could not be generated.');
        }

        return $pdf;
    }
}
