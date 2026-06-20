<?php

declare(strict_types=1);

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Core\Autoloader;

$testRootPath = dirname(__DIR__);

require_once $testRootPath . '/app/Core/Autoloader.php';

Autoloader::register($testRootPath . '/app');

if (!function_exists('assertSameValue')) {
    function assertSameValue(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            fwrite(STDERR, $message . PHP_EOL);
            fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
            fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
            exit(1);
        }
    }
}

if (!function_exists('assertTrue')) {
    function assertTrue(bool $actual, string $message): void
    {
        assertSameValue(true, $actual, $message);
    }
}

if (!function_exists('assertFalse')) {
    function assertFalse(bool $actual, string $message): void
    {
        assertSameValue(false, $actual, $message);
    }
}

if (!function_exists('assertThrows')) {
    /**
     * @param class-string<Throwable> $expectedClass
     */
    function assertThrows(
        callable $callback,
        string $expectedClass,
        ?int $expectedHttpStatus = null
    ): Throwable {
        $message = 'Expected callback to throw ' . $expectedClass . '.';

        try {
            $callback();
        } catch (Throwable $exception) {
            if (!$exception instanceof $expectedClass) {
                fwrite(STDERR, $message . PHP_EOL);
                fwrite(STDERR, 'Expected exception: ' . $expectedClass . PHP_EOL);
                fwrite(STDERR, 'Actual exception:   ' . $exception::class . PHP_EOL);
                exit(1);
            }

            if ($expectedHttpStatus !== null) {
                if (!$exception instanceof AppException) {
                    fwrite(STDERR, $message . PHP_EOL);
                    fwrite(STDERR, 'HTTP status can only be asserted for AppException.' . PHP_EOL);
                    exit(1);
                }

                assertSameValue(
                    $expectedHttpStatus,
                    $exception->httpStatus(),
                    $message . ' (HTTP status)'
                );
            }

            return $exception;
        }

        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected exception was not thrown: ' . $expectedClass . PHP_EOL);
        exit(1);
    }
}
