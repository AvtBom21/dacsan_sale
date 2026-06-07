<?php

declare(strict_types=1);

namespace DacSanNhaDan\Support;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class IdGenerator
{
    private const PREFIXES = [
        'order' => 'ORD',
        'lot' => 'LOT',
        'plan' => 'PO',
        'movement' => 'MOV',
        'receipt' => 'RCV',
    ];

    public static function generate(string $kind, ?DateTimeInterface $date = null): string
    {
        if (!isset(self::PREFIXES[$kind])) {
            throw new InvalidArgumentException('Unknown ID kind.');
        }

        return self::generateWithPrefix(self::PREFIXES[$kind], $date);
    }

    public static function orderId(?DateTimeInterface $date = null): string
    {
        return self::generate('order', $date);
    }

    public static function lotId(?DateTimeInterface $date = null): string
    {
        return self::generate('lot', $date);
    }

    public static function planId(?DateTimeInterface $date = null): string
    {
        return self::generate('plan', $date);
    }

    public static function movementId(?DateTimeInterface $date = null): string
    {
        return self::generate('movement', $date);
    }

    public static function receiptId(?DateTimeInterface $date = null): string
    {
        return self::generate('receipt', $date);
    }

    private static function generateWithPrefix(string $prefix, ?DateTimeInterface $date = null): string
    {
        $date ??= new DateTimeImmutable();

        return sprintf('%s-%s-%05d', $prefix, $date->format('Ymd'), random_int(0, 99999));
    }
}
