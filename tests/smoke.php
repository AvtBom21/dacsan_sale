<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$php = PHP_BINARY;

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fail($message);
    }
}

function run_php(string $php, string $file): string
{
    $command = escapeshellarg($php) . ' ' . escapeshellarg($file);
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        fail("PHP script failed: {$file}");
    }

    return trim(implode(PHP_EOL, $output));
}

$requiredPaths = [
    'app/Core/Autoloader.php',
    'app/Core/AppException.php',
    'app/Core/Database.php',
    'app/Core/Request.php',
    'app/Core/Response.php',
    'config/app.php',
    'config/database.php',
    'config/session.php',
    'public/index.php',
    'public/api/index.php',
    'admin/index.php',
    'admin/api/index.php',
    'router.php',
    '.htaccess',
];

foreach ($requiredPaths as $path) {
    assert_true(file_exists($root . DIRECTORY_SEPARATOR . $path), "Missing required path: {$path}");
}

$storeHtml = run_php($php, $root . '/public/index.php');
assert_true(str_contains($storeHtml, 'Đặc Sản Nhà Dân — Storefront'), 'Storefront page text is missing.');

$adminHtml = run_php($php, $root . '/admin/index.php');
assert_true(str_contains($adminHtml, 'Đặc Sản Nhà Dân'), 'Admin page brand text is missing.');
assert_true(str_contains($adminHtml, 'Đăng nhập quản trị'), 'Admin login text is missing.');

$publicHealth = json_decode(run_php($php, $root . '/public/api/index.php'), true);
assert_true(is_array($publicHealth), 'Public API health check did not return JSON.');
assert_true(($publicHealth['status'] ?? null) === 'ok', 'Public API health status is not ok.');
assert_true(($publicHealth['data']['component'] ?? null) === 'storefront-api', 'Public API component is wrong.');

$adminHealth = json_decode(run_php($php, $root . '/admin/api/index.php'), true);
assert_true(is_array($adminHealth), 'Admin API health check did not return JSON.');
assert_true(($adminHealth['status'] ?? null) === 'ok', 'Admin API health status is not ok.');
assert_true(($adminHealth['data']['component'] ?? null) === 'admin-api', 'Admin API component is wrong.');

echo 'Smoke tests passed.' . PHP_EOL;
