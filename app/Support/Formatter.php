<?php

declare(strict_types=1);

namespace DacSanNhaDan\Support;

use DateTimeImmutable;
use Exception;

final class Formatter
{
    public static function h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function moneyVnd(mixed $value): string
    {
        return number_format((int) round((float) $value), 0, ',', '.') . 'đ';
    }

    public static function decimalDisplay(mixed $value): string
    {
        $number = (float) $value;

        if (abs($number - round($number)) < 0.0001) {
            return (string) (int) round($number);
        }

        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    }

    public static function dateDisplay(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($value))->format('d/m/Y');
        } catch (Exception) {
            return '';
        }
    }
}
