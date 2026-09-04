<?php
/**
 * Print-focused report rendered by Dompdf. Download controls intentionally live
 * only in the browser template and are not included here.
 *
 * @var \Effortmark\PptxChecker\Presentation\AnalysisResult $result
 * @var string $unifiedReportHtml
 */
$summary = $result->review['summary'];
$summaryBoxes = [
    'images' => ['label' => 'Images', 'style' => 'neutral'],
    'errors' => ['label' => 'Errors', 'style' => 'error'],
    'warnings' => ['label' => 'Warnings', 'style' => 'warning'],
    'passed' => ['label' => 'Passed', 'style' => 'success'],
    'excluded' => ['label' => 'Decorative', 'style' => 'neutral'],
];
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
        .review-summary { display: table; width: 100%; margin: 5mm 0; border-collapse: separate; border-spacing: 1.5mm 0; table-layout: fixed; }
        .review-count { display: table-cell; width: 20%; padding: 1.5mm; border: 1px solid #aaa; border-left-width: 2mm; font-size: 8.5pt; vertical-align: top; }
        .review-count strong { display: block; font-size: 14pt; }
        .review-count.error { border-left-color: #b42318; background: #fef3f2; }
        .review-count.warning { border-left-color: #b54708; background: #fffaeb; }
        .review-count.success { border-left-color: #027a48; background: #ecfdf3; }
        .review-count.neutral { border-left-color: #667085; background: #f8f9fa; }
        .slide-item { margin: 0 0 6mm; padding: 4mm; border: 1px solid #aaa; line-height: 1.2; }
        .slide-item > h1, .slide-item > h2, .slide-item > h3, .slide-item > h4 { margin-bottom: 1.5mm; }
        .slide-item > p { margin-bottom: 1.2mm; }
        .slide-item > ul, .slide-item > ol { margin-top: 0.5mm; margin-bottom: 1.2mm; }
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
        .review-score-ranges { margin: 3mm 0; padding-left: 6mm; }
        .review-score { font-weight: bold; }
        ul { margin-top: 1mm; margin-bottom: 2mm; }
    </style>
</head>
<body>
    <h1><?= htmlspecialchars($result->filename, ENT_QUOTES, 'UTF-8') ?></h1>
    <section>
        <h2>Presentation review</h2>
        <p>This automated review checks image alternative text using deterministic rules. Relevance scores measure textual connections and do not prove that a description is correct or incorrect.</p>
        <ul class="review-score-ranges">
            <li><strong>70–100:</strong> strong evidence</li>
            <li><strong>35–69:</strong> some evidence</li>
            <li><strong>0–34:</strong> manual review recommended</li>
        </ul>
        <div class="review-summary">
            <?php foreach ($summaryBoxes as $key => $box): ?>
                <?php
                $count = (int) ($summary[$key] ?? 0);
                $style = $count === 0 ? 'neutral' : $box['style'];
                ?>
                <span class="review-count <?= htmlspecialchars($style, ENT_QUOTES, 'UTF-8') ?>">
                    <strong><?= $count ?></strong><?= htmlspecialchars($box['label'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?= $unifiedReportHtml ?>
    </section>
</body>
</html>
