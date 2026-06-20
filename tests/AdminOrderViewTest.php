<?php

declare(strict_types=1);

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Services\AdminAuthorizationService;

require_once __DIR__ . '/TestSupport.php';

function assertContainsValue(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . var_export($needle, true) . PHP_EOL);
        exit(1);
    }
}

function assertNotContainsValue(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . var_export($needle, true) . PHP_EOL);
        exit(1);
    }
}

define('DSND_ADMIN_HELPERS_ONLY', true);
require_once dirname(__DIR__) . '/admin/index.php';

assertSameValue(
    'order-detail',
    admin_page_from_value('order-detail'),
    'Order detail should be an allowed admin page.'
);
assertSameValue(
    'product-form',
    admin_page_from_value('product-form'),
    'Product form should be an allowed admin page.'
);
assertSameValue(
    'dashboard',
    admin_page_from_value('invalid'),
    'Unknown admin pages should fall back to dashboard.'
);

assertSameValue(
    'ORDER_2026-01',
    admin_page_id_from_value('order-detail', 'ORDER_2026-01'),
    'Detail pages should accept safe identifiers.'
);
assertSameValue(
    null,
    admin_page_id_from_value('product-form', null),
    'Product form should allow a missing identifier when creating.'
);
assertSameValue(
    null,
    admin_page_id_from_value('orders', '../bad'),
    'List pages should ignore an irrelevant identifier.'
);
assertThrows(
    static fn () => admin_page_id_from_value('order-detail', '../bad'),
    AppException::class,
    422
);
assertThrows(
    static fn () => admin_page_id_from_value('inventory-lot', ''),
    AppException::class,
    422
);
assertThrows(
    static fn () => admin_page_id_from_value('order-detail', ['ORDER_2026-01']),
    AppException::class,
    422
);

$authorization = new AdminAuthorizationService();
admin_require_page_permission($authorization, 'staff', 'order-detail');
assertThrows(
    static fn () => admin_require_page_permission($authorization, 'staff', 'products'),
    AppException::class,
    403
);

$staffHtml = renderAdminLayoutForTest(
    'order-detail',
    ['id' => 'ORDER_2026-01'],
    [
        'dashboard' => true,
        'orders' => true,
        'products' => false,
        'inventory' => false,
        'purchase_plans' => true,
        'settings' => false,
        'admin_users' => false,
    ]
);
assertContainsValue('href="./?page=orders"', $staffHtml, 'Staff should see order navigation.');
assertContainsValue('href="./?page=purchase-plans"', $staffHtml, 'Staff should see PO navigation.');
assertNotContainsValue('href="./?page=products"', $staffHtml, 'Staff should not see product navigation.');
assertNotContainsValue('href="./?page=inventory"', $staffHtml, 'Staff should not see inventory navigation.');
assertNotContainsValue('href="./?page=settings"', $staffHtml, 'Staff should not see settings navigation.');
assertNotContainsValue('href="./?page=admin-users"', $staffHtml, 'Staff should not see user management navigation.');
assertContainsValue(
    'data-admin-placeholder="order-detail"',
    $staffHtml,
    'Missing detail views should render an explicit safe placeholder.'
);
assertNotContainsValue('metric-grid', $staffHtml, 'Missing detail views must not fall back to dashboard content.');
assertContainsValue(
    'href="./?page=orders" class="active"',
    $staffHtml,
    'Order detail should activate the parent order navigation.'
);

$adminHtml = renderAdminLayoutForTest(
    'product-form',
    ['id' => null],
    [
        'dashboard' => true,
        'orders' => true,
        'products' => true,
        'inventory' => true,
        'purchase_plans' => true,
        'settings' => true,
        'admin_users' => false,
    ],
    'admin'
);
assertContainsValue('href="./?page=products"', $adminHtml, 'Admin should see product navigation.');
assertContainsValue('href="./?page=inventory"', $adminHtml, 'Admin should see inventory navigation.');
assertContainsValue('href="./?page=settings"', $adminHtml, 'Admin should see settings navigation.');
assertNotContainsValue('href="./?page=admin-users"', $adminHtml, 'Admin should not see owner user management.');
assertContainsValue(
    'href="./?page=products" class="active"',
    $adminHtml,
    'Product form should activate the parent product navigation.'
);

$ownerHtml = renderAdminLayoutForTest(
    'admin-users',
    [],
    [
        'dashboard' => true,
        'orders' => true,
        'products' => true,
        'inventory' => true,
        'purchase_plans' => true,
        'settings' => true,
        'admin_users' => true,
    ],
    'owner'
);
assertContainsValue('href="./?page=admin-users"', $ownerHtml, 'Owner should see user management navigation.');

echo 'Admin order view tests passed.' . PHP_EOL;

/**
 * @param array<string, mixed> $data
 * @param array<string, bool> $capabilities
 */
function renderAdminLayoutForTest(
    string $page,
    array $data,
    array $capabilities,
    string $role = 'staff'
): string {
    $user = [
        'username' => $role . '-user',
        'full_name' => ucfirst($role) . ' User',
        'role' => $role,
    ];
    $csrfToken = 'test-token';
    $_SERVER['SCRIPT_NAME'] = '/Dacsan/admin/index.php';

    ob_start();
    require dirname(__DIR__) . '/views/admin/layout.php';

    return (string) ob_get_clean();
}
