<?php

declare(strict_types=1);

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\AdminDashboardRepository;
use DacSanNhaDan\Repositories\InventoryRepository;
use DacSanNhaDan\Repositories\OrderRepository;
use DacSanNhaDan\Services\AdminService;
use DacSanNhaDan\Services\AdminAuthorizationService;
use DacSanNhaDan\Services\InventoryService;
use DacSanNhaDan\Services\OrderService;

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
    adminOrderViewFixture(),
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
    'id="invoice"',
    $staffHtml,
    'Order detail should render the invoice view.'
);
assertNotContainsValue('metric-grid', $staffHtml, 'Missing detail views must not fall back to dashboard content.');
assertContainsValue(
    'href="./?page=orders" class="active"',
    $staffHtml,
    'Order detail should activate the parent order navigation.'
);
assertContainsValue(
    'data-api-base="/Dacsan/admin/api/index.php"',
    $staffHtml,
    'Admin layout should expose the correct API base for a /Dacsan installation.'
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

$pdo = adminOrderTestDatabase();
$dashboardRepository = new AdminDashboardRepository($pdo);
$orderService = new OrderService(
    $pdo,
    new OrderRepository($pdo),
    new InventoryService(new InventoryRepository($pdo))
);
$adminService = new AdminService($dashboardRepository);
$detail = $adminService->orderDetail('ORDER_FIXTURE_1');

assertTrue(is_array($detail), 'Order detail should be returned for an existing order.');
assertSameValue('Snapshot Bò một nắng', $detail['items'][0]['product_name_snapshot'], 'Invoice data must keep the product name snapshot.');
assertSameValue('Gói snapshot 500g', $detail['items'][0]['uom_label_snapshot'], 'Invoice data must keep the UOM snapshot.');
assertSameValue(345000, (int) $detail['items'][0]['unit_price_vnd'], 'Invoice data must keep the unit-price snapshot.');
assertSameValue(1, count($detail['allocations']), 'Order detail should include allocations.');
assertSameValue(1, count($detail['movements']), 'Order detail should include inventory movements.');
assertSameValue(['confirmed', 'cancelled'], $detail['allowed_next_statuses'], 'Order detail should include valid next statuses.');
assertSameValue('Đặc Sản Test', $detail['store']['store_name'], 'Order detail should include store settings.');
assertSameValue('VCB', $detail['payment']['bank_name'], 'Order detail should include configured payment settings.');
assertSameValue('THANH TOAN ORDER_FIXTURE_1', $detail['payment']['bank_transfer_content'], 'Transfer content should interpolate the order ID.');
assertSameValue('', $detail['payment']['bank_qr_image_path'], 'Missing payment settings should gracefully use empty values.');

$invoiceHtml = renderOrderDetailForTest($detail);
assertContainsValue('Snapshot Bò một nắng', $invoiceHtml, 'Invoice should render the product snapshot.');
assertContainsValue('Gói snapshot 500g', $invoiceHtml, 'Invoice should render the UOM snapshot.');
assertNotContainsValue('Tên sản phẩm hiện tại', $invoiceHtml, 'Invoice must not render current product master data.');
assertContainsValue('data-print-invoice', $invoiceHtml, 'Invoice should expose a print action.');
assertContainsValue('data-order-status="confirmed"', $invoiceHtml, 'Invoice should render only allowed transition buttons.');
assertContainsValue('data-order-status="cancelled"', $invoiceHtml, 'Invoice should render cancellation when allowed.');
assertNotContainsValue('data-order-status="done"', $invoiceHtml, 'Invoice should not render invalid transitions.');
assertContainsValue('VCB', $invoiceHtml, 'Invoice should render bank information when configured.');
assertContainsValue('345.000', $invoiceHtml, 'Invoice should render snapshot pricing.');

$qrDetail = $detail;
$qrDetail['payment']['bank_qr_image_path'] = 'products_image/payment-qr.png';
$qrHtml = renderOrderDetailForTest($qrDetail);
assertContainsValue('src="/products_image/payment-qr.png"', $qrHtml, 'Invoice should render a configured payment QR.');

$ordersHtml = renderOrdersListForTest([
    'items' => [[
        'order_id' => 'ORDER_FIXTURE_1',
        'created_at' => '2026-06-20 10:00:00',
        'customer_name' => 'Khách Test',
        'customer_phone' => '0900000000',
        'status' => 'new',
        'total_vnd' => 370000,
    ]],
    'filters' => [],
]);
assertContainsValue(
    './?page=order-detail&amp;id=ORDER_FIXTURE_1',
    $ordersHtml,
    'Order list should link clearly to order detail.'
);

assertSameValue([], $orderService->getAllowedTransitions('done'), 'Done orders should have no actions.');
$conflict = assertThrows(
    static fn () => $orderService->changeStatus('ORDER_FIXTURE_1', 'done'),
    AppException::class,
    409
);
assertContainsValue('không hợp lệ', $conflict->getMessage(), 'Invalid state transitions should report a conflict.');
assertThrows(
    static fn () => $orderService->changeStatus('ORDER_FIXTURE_1', 'bogus'),
    AppException::class,
    422
);

$apiSource = file_get_contents(dirname(__DIR__) . '/admin/api/index.php');
assertTrue(is_string($apiSource), 'Admin API source should be readable.');
assertContainsValue("'order-detail' => 'orders.view'", $apiSource, 'Order detail API should require order view permission.');
assertContainsValue("'order-status' => 'orders.transition'", $apiSource, 'Order status API should require transition permission.');
assertContainsValue("admin_api_require_method('GET')", $apiSource, 'Order detail API should explicitly require GET.');

$jsSource = file_get_contents(dirname(__DIR__) . '/public/assets/js/admin.js');
assertTrue(is_string($jsSource), 'Admin JavaScript source should be readable.');
assertContainsValue('async function adminRequest', $jsSource, 'Admin JavaScript should expose a shared request helper.');
assertContainsValue('window.DSND_ADMIN.apiBase', $jsSource, 'Admin requests should use the application-aware API base.');
assertContainsValue('data-order-status', $jsSource, 'Admin JavaScript should wire order status actions.');
assertContainsValue('window.confirm', $jsSource, 'Cancellation should require confirmation.');

$cssSource = file_get_contents(dirname(__DIR__) . '/public/assets/css/admin.css');
assertTrue(is_string($cssSource), 'Admin CSS source should be readable.');
assertContainsValue('@media print', $cssSource, 'Admin CSS should define print output.');
assertContainsValue('.invoice {', $cssSource, 'Admin CSS should style the printable invoice.');
assertContainsValue('.no-print', $cssSource, 'Print output should hide operational controls.');
assertContainsValue('@page', $cssSource, 'Print output should define an A4 page.');

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

/**
 * @param array<string, mixed> $data
 */
function renderOrderDetailForTest(array $data): string
{
    $capabilities = ['orders_transition' => true, 'orders_print' => true];

    ob_start();
    require dirname(__DIR__) . '/views/admin/order-detail.php';

    return (string) ob_get_clean();
}

/**
 * @param array<string, mixed> $data
 */
function renderOrdersListForTest(array $data): string
{
    ob_start();
    require dirname(__DIR__) . '/views/admin/orders.php';

    return (string) ob_get_clean();
}

function adminOrderTestDatabase(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE orders (
            order_id TEXT PRIMARY KEY, customer_id INTEGER, created_at TEXT, status TEXT,
            customer_name TEXT, customer_phone TEXT, customer_address TEXT, receive_date TEXT,
            note TEXT, shipping_method TEXT, shipping_zone_id TEXT, shipping_fee_vnd INTEGER,
            subtotal_vnd INTEGER, total_vnd INTEGER, source_summary TEXT, updated_at TEXT
        );
        CREATE TABLE order_items (
            order_item_id INTEGER PRIMARY KEY, order_id TEXT, line_no INTEGER, product_id TEXT,
            product_name_snapshot TEXT, uom_id TEXT, uom_label_snapshot TEXT, source_location TEXT,
            qty_uom REAL, conversion_to_base_snapshot REAL, qty_base REAL, unit_price_vnd INTEGER,
            line_total_vnd INTEGER, allocated_lot_id TEXT, planned_plan_id TEXT, planned_at TEXT,
            created_at TEXT
        );
        CREATE TABLE order_item_allocations (
            allocation_id INTEGER PRIMARY KEY, order_item_id INTEGER, order_id TEXT, lot_id TEXT,
            product_id TEXT, qty_base REAL, movement_id TEXT, created_at TEXT
        );
        CREATE TABLE inventory_movements (
            movement_id TEXT PRIMARY KEY, created_at TEXT, movement_type TEXT, ref_type TEXT,
            ref_id TEXT, lot_id TEXT, product_id TEXT, source_location TEXT, uom_id TEXT,
            qty_uom REAL, conversion_to_base_snapshot REAL, qty_base REAL,
            cost_per_base_unit_vnd INTEGER, note TEXT
        );
        CREATE TABLE settings (
            setting_key TEXT PRIMARY KEY, setting_value TEXT, note TEXT, updated_at TEXT
        );'
    );
    $pdo->exec(
        "INSERT INTO orders VALUES (
            'ORDER_FIXTURE_1', NULL, '2026-06-20 10:00:00', 'new', 'Khách Test',
            '0900000000', '1 Đường Test', '2026-06-21', 'Giao buổi sáng', 'delivery',
            'ZONE_TEST', 25000, 345000, 370000, 'Gia Lai', '2026-06-20 10:00:00'
        );
        INSERT INTO order_items VALUES (
            1, 'ORDER_FIXTURE_1', 1, 'pro_current', 'Snapshot Bò một nắng',
            'uom_current', 'Gói snapshot 500g', 'Gia Lai', 1, 1, 1, 345000,
            345000, 'LOT_TEST', NULL, NULL, '2026-06-20 10:00:00'
        );
        INSERT INTO order_item_allocations VALUES (
            1, 1, 'ORDER_FIXTURE_1', 'LOT_TEST', 'pro_current', 1, 'MOV_TEST',
            '2026-06-20 10:05:00'
        );
        INSERT INTO inventory_movements VALUES (
            'MOV_TEST', '2026-06-20 10:05:00', 'RESERVE', 'ORDER',
            'ORDER_FIXTURE_1', 'LOT_TEST', 'pro_current', 'Gia Lai', NULL,
            1, 1, 1, 230000, 'Giữ hàng'
        );
        INSERT INTO settings VALUES ('store_name', 'Đặc Sản Test', '', '');
        INSERT INTO settings VALUES ('store_phone', '0378000000', '', '');
        INSERT INTO settings VALUES ('bank_name', 'VCB', '', '');
        INSERT INTO settings VALUES ('bank_account_number', '123456789', '', '');
        INSERT INTO settings VALUES ('bank_account_holder', 'NGUYEN VAN A', '', '');
        INSERT INTO settings VALUES ('bank_transfer_content', 'THANH TOAN {order_id}', '', '');"
    );

    return $pdo;
}

/**
 * @return array<string, mixed>
 */
function adminOrderViewFixture(): array
{
    return [
        'order_id' => 'ORDER_2026-01',
        'created_at' => '2026-06-20 10:00:00',
        'status' => 'new',
        'customer_name' => 'Khách Test',
        'customer_phone' => '0900000000',
        'customer_address' => '1 Đường Test',
        'receive_date' => '2026-06-21',
        'note' => 'Giao buổi sáng',
        'shipping_method' => 'delivery',
        'shipping_fee_vnd' => 25000,
        'subtotal_vnd' => 345000,
        'total_vnd' => 370000,
        'items' => [[
            'product_name_snapshot' => 'Snapshot Bò một nắng',
            'uom_label_snapshot' => 'Gói snapshot 500g',
            'qty_uom' => 1,
            'unit_price_vnd' => 345000,
            'line_total_vnd' => 345000,
        ]],
        'allowed_next_statuses' => ['confirmed', 'cancelled'],
        'store' => ['store_name' => 'Đặc Sản Test', 'store_phone' => '0378000000'],
        'payment' => [],
    ];
}
