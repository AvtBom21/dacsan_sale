<?php

declare(strict_types=1);

namespace DacSanNhaDan\Support;

final class Phone
{
    public static function normalize(mixed $phone): string
    {
        $phone = trim((string) $phone);
        $phone = preg_replace('/[^\d+]/', '', $phone) ?? '';

        if (str_starts_with($phone, '+')) {
            return '+' . (preg_replace('/\D/', '', substr($phone, 1)) ?? '');
        }

        return preg_replace('/\D/', '', $phone) ?? '';
    }

    public static function isValidVietnamPhone(mixed $phone): bool
    {
        $normalized = self::normalize($phone);

        if (preg_match('/^0(3|5|7|8|9)\d{8}$/', $normalized) === 1) {
            return true;
        }

        if (preg_match('/^\+84(3|5|7|8|9)\d{8}$/', $normalized) === 1) {
            return true;
        }

        return preg_match('/^84(3|5|7|8|9)\d{8}$/', $normalized) === 1;
    }
}
