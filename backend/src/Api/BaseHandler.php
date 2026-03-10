<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

abstract class BaseHandler
{
    // JSON-Request-Body einlesen und dekodieren
    protected function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Response::error('Ungültiger JSON-Body.', 400);
        }
        return $data;
    }

    // Integer aus Pfad-Parameter sicher auslesen
    protected function intParam(array $params, string $key): int
    {
        $val = $params[$key] ?? null;
        if ($val === null || !ctype_digit((string) $val)) {
            Response::error("Ungültiger Parameter: $key", 400);
        }
        return (int) $val;
    }

    // Query-Parameter aus GET auslesen
    protected function queryParam(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
}
