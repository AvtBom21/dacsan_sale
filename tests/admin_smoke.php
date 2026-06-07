<?php

declare(strict_types=1);

use DacSanNhaDan\Core\Csrf;
use DacSanNhaDan\Core\Database;
use DacSanNhaDan\Repositories\AdminDashboardRepository;
use DacSanNhaDan\Repositories\AdminUserRepository;
use DacSanNhaDan\Services\AdminAuthService;
use DacSanNhaDan\Services\AdminService;

require dirname(__DIR__) . '/app/bootstrap.php';

function admin_fail(string $message): void
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit(1);
}

function admin_pass(string $message): void
{
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
}

function admin_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$failure = null;

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $pdo = Database::connection();
    $auth = new AdminAuthService(new AdminUserRepository($pdo));
    $admin = new AdminService(new AdminDashboardRepository($pdo));

    $token = Csrf::adminToken(true);
    admin_assert($token !== '', 'Admin CSRF token should be generated.');
    admin_assert(Csrf::verifyAdminToken($token), 'Admin CSRF token should verify.');
    admin_pass('admin CSRF token OK');

    $user = $auth->login('admin', 'admin123');
    admin_assert(($user['username'] ?? '') === 'admin', 'Demo admin should login.');
    admin_assert(in_array((string) ($user['role'] ?? ''), ['owner', 'superadmin', 'admin'], true), 'Demo admin role should be accepted.');
    admin_assert($auth->check(), 'Admin session should be authenticated after login.');
    admin_pass('admin login/session OK');

    $dashboard = $admin->dashboard();
    foreach (['orders_today', 'revenue_today_vnd', 'pending_orders', 'low_stock_count', 'expiring_lots_count', 'open_purchase_plans'] as $key) {
        admin_assert(array_key_exists($key, $dashboard), 'Dashboard missing metric ' . $key);
    }
    admin_pass('dashboard metrics load OK');

    $orders = $admin->orders(['limit' => 10]);
    admin_assert(isset($orders['items']) && is_array($orders['items']), 'Orders list should load.');
    admin_pass('orders list load OK');

    $products = $admin->products(['limit' => 10]);
    admin_assert(isset($products['items']) && is_array($products['items']), 'Products list should load.');
    admin_pass('products list load OK');

    $inventory = $admin->inventory(['limit' => 10]);
    admin_assert(isset($inventory['summary']) && is_array($inventory['summary']), 'Inventory summary should load.');
    admin_assert(isset($inventory['lots']) && is_array($inventory['lots']), 'Inventory lots should load.');
    admin_pass('inventory load OK');

    $plans = $admin->purchasePlans(['limit' => 10]);
    admin_assert(isset($plans['items']) && is_array($plans['items']), 'PO list should load.');
    admin_pass('PO list load OK');

    $settings = $admin->settings();
    admin_assert(isset($settings['settings']) && is_array($settings['settings']), 'Settings should load.');
    admin_assert(isset($settings['shipping_zones']) && is_array($settings['shipping_zones']), 'Shipping zones should load.');
    admin_pass('settings load OK');

    $auth->logout();
    admin_assert(!$auth->check(), 'Admin logout should clear session.');
    admin_pass('admin logout OK');

    admin_pass('Admin smoke test passed.');
} catch (Throwable $exception) {
    $failure = $exception->getMessage();
}

if ($failure !== null) {
    admin_fail($failure);
}
