<?php

declare(strict_types=1);

namespace DacSanNhaDan\Core;

final class Request
{
    /**
     * @return array<string, mixed>
     */
    public static function json(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new AppException('JSON request body không hợp lệ.', 400);
        }

        return $decoded;
    }

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return $path === false || $path === null ? '/' : $path;
    }
}
