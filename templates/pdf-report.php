<?php
/**
 * Print-focused report rendered by Dompdf. Download controls intentionally live
 * only in the browser template and are not included here.
 *
 * @var \Effortmark\PptxChecker\Presentation\AnalysisResult $result
 * @var string $unifiedReportHtml
 */
$missingImages = [];
foreach ($result->review['slides'] as $slide) {
    foreach ($slide['findings'] as $finding) {
        if (($finding['code'] ?? '') === 'image.alt.missing') {
            $missingImages[] = [
                'slide' => (int) $slide['number'],
                'image' => (int) ($finding['imageNumber'] ?? 0),
                'filename' => (string) ($finding['filename'] ?? ''),
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en-gb">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($result->filename, ENT_QUOTES, 'UTF-8') ?> – PPTX Checker</title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        body { color: #212529; font-family: "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.4; }
        h1 { margin: 0 0 8mm; font-size: 20pt; }
        h2 { margin: 0 0 3mm; font-size: 16pt; }
        h4, h5 { margin: 0 0 3mm; }
        p { margin: 0 0 3mm; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2mm; border: 1px solid #555; }
        th, .tableRowFirst th, .tableRowFirst td { font-weight: bold; }
        .review-overview { margin: 5mm 0; padding: 3mm; border: 1px solid #aaa; border-left-width: 3mm; }
        .review-overview.error { border-left-color: #b42318; background: #fef3f2; }

        .review-overview.neutral { border-left-color: #667085; background: #f8f9fa; }
        .review-overview ul { margin-bottom: 0; }
        .slide-item { margin: 0 0 6mm; padding: 4mm; border: 1px solid #aaa; line-height: 1.2; }
        .slide-item .slide-title { margin-bottom: 1.5mm; font-size: 11.5pt; font-weight: bold; }
        .slide-item .slide-title p { margin-bottom: 0; }
        .slide-item > p { margin-bottom: 1.2mm; }
        .slide-item > .slide-number { margin-top: 2mm; padding-top: 1.2mm; border-top: 0.5pt solid #aaa; margin-bottom: 0; }
        .slide-item > ul, .slide-item > ol { margin-top: 0.5mm; margin-bottom: 1.2mm; }
        .grouped-images { margin: 2mm 0; padding: 3mm; border: 1.5pt solid #667085; background: #f8f9fa; }
        .grouped-images-label { margin-bottom: 2mm; }
        .image-object { margin-top: 3mm; padding-top: 2mm; border-top: 1px solid #ddd; }
        .review-finding, .review-empty { margin: 3mm 0; padding: 2mm; border: 1px solid #aaa; }
        .review-finding { line-height: 1.2; }
        .review-finding h5 { margin-bottom: 1.5mm; }
        .review-finding p { margin-bottom: 1.2mm; }
        .review-finding p:last-child { margin-bottom: 0; }
        .review-finding.error { border-left: 3mm solid #b42318; background: #fef3f2; }
        .review-finding.warning { border-left: 3mm solid #b54708; background: #fffaeb; }
        .review-finding.information { border-left: 3mm solid #027a48; background: #ecfdf3; }
        .review-empty { border-left: 3mm solid #667085; background: #f8f9fa; }

        ul { margin-top: 1mm; margin-bottom: 2mm; }
    </style>
</head>
<body>
    <h1><?= htmlspecialchars($result->filename, ENT_QUOTES, 'UTF-8') ?></h1>
    <section>
        <?php if ((int) $result->review['summary']['images'] === 0): ?>
            <h2>Presentation review</h2>
            <div class="review-overview neutral">
                <strong>No images were found in the presentation.</strong>
            </div>
        <?php elseif ($missingImages !== []): ?>
            <h2>Presentation review</h2>
            <div class="review-overview error">
                <strong>The following images have missing descriptions/alt text</strong>
                <ul>
                    <?php foreach ($missingImages as $image): ?>
                        <li>
                            Slide <?= $image['slide'] ?>, image <?= $image['image'] ?><?php if ($image['filename'] !== ''): ?>:
                                <?= htmlspecialchars($image['filename'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?= $unifiedReportHtml ?>
    </section>
</body>
</html>
