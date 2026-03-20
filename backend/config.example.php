<?php

// Kopiere diese Datei nach config.php und trage deine Werte ein.
// config.php darf NICHT ins Git-Repository!

return [

    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'stolpersteine',
        'user'     => 'db_user',
        'password' => 'geheimes_passwort',
    ],

    'app' => [
        'debug'        => false,           // true nur lokal, nie auf Produktion!
        // Log-Level: DEBUG, INFO, WARNING, ERROR, CRITICAL
        // Wird automatisch aus 'debug' abgeleitet wenn nicht gesetzt (debug=true → DEBUG, sonst WARNING).
        // 'log_level' => 'WARNING',
        'upload_dir'   => __DIR__ . '/../uploads',
        'log_dir'      => __DIR__ . '/../storage/logs',     // Lokal: neben config.php; Produktion: stst/storage/logs
        'spiegel_dir'  => __DIR__ . '/../storage/spiegel',  // lokale PDF-Spiegelung, nicht web-zugänglich
        'base_url'     => 'https://example.com',
        // CORS: erlaubte Frontend-Origins (nur relevant wenn debug = true)
        'cors_origins' => [
            // 'http://localhost:8001',
        ],
    ],

    'session' => [
        'name'     => 'stolpersteine_sess',
        'lifetime' => 7200,               // Sekunden (2 Stunden)
    ],

];
