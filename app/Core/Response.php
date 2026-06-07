<?php

declare(strict_types=1);

namespace DacSanNhaDan\Core;

final class Response
{
    /**
     * @param array<string, mixed>|array<int, mixed> $data
     */
    public static function ok(array $data = [], int $status = 200): void
    {
        self::json([
            'status' => 'ok',
            'data' => $data,
        ], $status);
    }

    public static function error(string $message, int $status = 400): void
    {
        self::json([
            'status' => 'error',
            'message' => $message,
        ], $status);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function html(string $html, int $status = 200): void
    {
        http_response_code($status);

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        echo $html;
    }
}
