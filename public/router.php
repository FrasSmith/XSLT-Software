<?php

/**
 * Router used only by PHP's built-in development server.
 * Existing static files are served directly; every other request is delegated to
 * the same front controller used by Apache or Nginx in production.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
