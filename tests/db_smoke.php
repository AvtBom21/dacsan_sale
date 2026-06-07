<?php

declare(strict_types=1);

use DacSanNhaDan\Core\Database;
use DacSanNhaDan\Support\DatabaseBaseline;

$config = require dirname(__DIR__) . '/app/bootstrap.php';

function fail_line(string $message): void
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
}

function pass_line(string $message): void
{
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
}

try {
    $pdo = Database::connection();
    pass_line('PDO connection established.');

    $databaseName = (string) $config['database']['database'];
    $inspection = DatabaseBaseline::inspect($pdo, $databaseName);

    if ($inspection['missing_tables'] !== []) {
        fail_line('Missing tables: ' . implode(', ', $inspection['missing_tables']));
    }

    if ($inspection['missing_views'] !== []) {
        fail_line('Missing views: ' . implode(', ', $inspection['missing_views']));
    }

    if ($inspection['missing_tables'] !== [] || $inspection['missing_views'] !== []) {
        exit(1);
    }

    if ($inspection['empty_seed_tables'] !== []) {
        fail_line('Seed data missing from tables: ' . implode(', ', $inspection['empty_seed_tables']));
        exit(1);
    }

    pass_line('All 18 core tables exist.');
    pass_line('Both core views exist.');
    pass_line('Seed data exists in baseline master tables.');
    pass_line('Table count: ' . (string) $inspection['table_count']);
    pass_line('View count: ' . (string) $inspection['view_count']);
    pass_line('Database baseline smoke test passed.');
} catch (Throwable $exception) {
    fail_line($exception->getMessage());
    exit(1);
}
