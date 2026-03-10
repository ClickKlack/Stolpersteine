<?php

// Statische Dateien (CSS, JS, Bilder etc.) direkt ausliefern
if (is_file(__DIR__ . $_SERVER['REQUEST_URI'])) {
    return false;
}

// Alles andere → index.php (API-Router)
require __DIR__ . '/index.php';
