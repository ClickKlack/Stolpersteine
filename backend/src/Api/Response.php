<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data, int $status = 200): void
    {
        self::json(['success' => true, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, array $details = []): void
    {
        $body = ['success' => false, 'error' => $message];
        if ($details !== []) {
            $body['details'] = $details;
        }
        self::json($body, $status);
    }

    public static function created(mixed $data): void
    {
        self::success($data, 201);
    }

    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }
}
