<?php

declare(strict_types=1);

namespace DacSanNhaDan\Core;

use PDO;
use PDOException;

final class Database
{
    /**
     * @var array<string, mixed>
     */
    private static array $config = [];

    private static ?PDO $connection = null;

    /**
     * @param array<string, mixed> $config
     */
    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        if (self::$config === []) {
            throw new AppException('Database configuration has not been loaded.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            self::$config['host'],
            self::$config['port'],
            self::$config['database'],
            self::$config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ] + (self::$config['options'] ?? []);

        try {
            self::$connection = new PDO(
                $dsn,
                (string) self::$config['username'],
                (string) self::$config['password'],
                $options
            );
        } catch (PDOException $exception) {
            throw new AppException('Unable to connect to the database.', 500, [], $exception);
        }

        return self::$connection;
    }

    public static function reset(): void
    {
        self::$connection = null;
    }
}
