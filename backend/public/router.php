<?php

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Uploads aus dem Projekt-Root ausliefern (/uploads/...)
if (str_starts_with($uri, '/uploads/')) {
    $datei = __DIR__ . '/../../' . ltrim($uri, '/');
    $datei = realpath($datei);
    $uploadRoot = realpath(__DIR__ . '/../../uploads');

    if ($datei && $uploadRoot && str_starts_with($datei, $uploadRoot) && is_file($datei)) {
        $mime = mime_content_type($datei) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($datei));
        header('Cache-Control: max-age=86400');
        readfile($datei);
        exit;
    }

    http_response_code(404);
    exit;
}

// Statische Dateien aus public/ direkt ausliefern
if (is_file(__DIR__ . $uri)) {
    return false;
}

// Alles andere → index.php (API-Router)
require __DIR__ . '/index.php';
