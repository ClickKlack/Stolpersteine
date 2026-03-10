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
        'debug'       => false,           // true nur lokal, nie auf Produktion!
        'upload_dir'  => __DIR__ . '/../uploads',
        'base_url'    => 'https://example.com',
    ],

    'session' => [
        'name'     => 'stolpersteine_sess',
        'lifetime' => 7200,               // Sekunden (2 Stunden)
    ],

];
