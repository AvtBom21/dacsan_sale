<?php

declare(strict_types=1);

namespace DacSanNhaDan\Support;

final class Logger
{
    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        $logDir = dirname(__DIR__, 2) . '/storage/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $line = json_encode([
            'level' => 'error',
            'time' => date(DATE_ATOM),
            'message' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($line !== false) {
            @file_put_contents($logDir . '/app.log', $line . PHP_EOL, FILE_APPEND);
        }
    }
}
