<?php

declare(strict_types=1);

namespace Stolpersteine\Config;

class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    public static function load(string $file): void
    {
        if (!file_exists($file)) {
            throw new \RuntimeException("Konfigurationsdatei nicht gefunden: $file");
        }

        self::$config = require $file;
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            throw new \RuntimeException('Konfiguration wurde noch nicht geladen.');
        }

        return self::$config[$key] ?? $default;
    }
}
