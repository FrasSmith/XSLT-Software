<?php
/**
 * Shared browser error response for validation, routing, and unexpected failures.
 * The front controller controls which messages are safe to expose to the user.
 *
 * @var string $message
 * @var string $basePath
 */
?>
<!DOCTYPE html>
<html lang="en-gb">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error – PPTX Checker</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/assets/main.css">
</head>
<body>
<main class="container" role="main">
    <h1>Unable to process presentation</h1>
    <p><?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?></p>
    <p><a href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/">Return to the upload form</a></p>
</main>
</body>
</html>
