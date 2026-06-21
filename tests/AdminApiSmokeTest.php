<?php

declare(strict_types=1);

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Core\Autoloader;
use DacSanNhaDan\Core\Database;
use DacSanNhaDan\Repositories\InventoryRepository;
use DacSanNhaDan\Repositories\AdminDashboardRepository;
use DacSanNhaDan\Repositories\OrderRepository;
use DacSanNhaDan\Repositories\PurchasePlanRepository;
use DacSanNhaDan\Services\InventoryService;
use DacSanNhaDan\Services\AdminService;
use DacSanNhaDan\Services\OrderService;
use DacSanNhaDan\Services\PurchasePlanService;

require __DIR__ . '/TestSupport.php';

$sources = implode("\n", [
    file_get_contents(__DIR__ . '/../admin/api/index.php'),
    file_get_contents(__DIR__ . '/../admin/index.php'),
    file_get_contents(__DIR__ . '/../views/admin/orders.php'),
    file_get_contents(__DIR__ . '/../views/admin/purchase-plans.php'),
    file_get_contents(__DIR__ . '/../views/admin/purchase-plan-detail.php'),
    file_get_contents(__DIR__ . '/../public/assets/js/admin.js'),
]);
assertTrue(str_contains($sources, "'po-preview' => 'purchase_plans.manage'"), 'PO preview must require manage permission.');
assertTrue(str_contains($sources, "'receive-po' => 'purchase_plans.manage'"), 'PO receipt must require manage permission.');
assertTrue(str_contains($sources, "'po-mark-ordered' => 'purchase_plans.manage'"), 'PO ordering must require manage permission.');
assertTrue(str_contains($sources, 'data-po-receive'), 'PO detail must expose receipt controls.');
assertTrue(str_contains($sources, 'data-po-mark-ordered'), 'Draft PO detail must expose ordering controls.');
assertTrue(str_contains($sources, 'data-po-cancel'), 'PO detail must expose valid cancellation controls.');
assertTrue(str_contains($sources, 'data-po-preview'), 'Order list must expose PO preview controls.');

if ((getenv('DB_DATABASE') ?: '') !== 'dac_san_nha_dan_test') {
    echo "AdminApiSmokeTest: PASS (source contract; MariaDB flow skipped)\n";
    return;
}

require dirname(__DIR__) . '/app/bootstrap.php';
$pdo = Database::connection();
$adminService = new AdminService(new AdminDashboardRepository($pdo));
$orderList = $adminService->orders(['page' => 1, 'per_page' => 20]);
assertTrue(isset($orderList['pagination']), 'Order list must include pagination.');
assertSameValue(20, $orderList['pagination']['per_page'], 'Order list must honor allowed page size.');
$productList = $adminService->products(['page' => 1, 'per_page' => 20, 'q' => 'bò']);
assertTrue(isset($productList['pagination']), 'Product list must include pagination.');
$planList = $adminService->purchasePlans(['page' => 1, 'per_page' => 20]);
assertTrue(isset($planList['pagination']), 'PO list must include pagination.');
$inventory = new InventoryService(new InventoryRepository($pdo));
$service = new PurchasePlanService(
    $pdo,
    new PurchasePlanRepository($pdo),
    new OrderService($pdo, new OrderRepository($pdo), $inventory),
    $inventory
);

$suffix = strtoupper(bin2hex(random_bytes(4)));
$orderId = 'TEST_PO_ORDER_' . $suffix;
$planId = null;
$lotIds = [];

try {
    $pdo->prepare(
        "INSERT INTO orders (
            order_id, status, customer_name, customer_phone, customer_address,
            shipping_method, shipping_fee_vnd, subtotal_vnd, total_vnd, source_summary
         ) VALUES (?, 'confirmed', 'Khách test PO', '0900000000', 'Test',
                   'pickup', 0, 330000, 330000, 'Gia Lai')"
    )->execute([$orderId]);
    $pdo->prepare(
        "INSERT INTO order_items (
            order_id, line_no, product_id, product_name_snapshot, uom_id,
            uom_label_snapshot, source_location, qty_uom,
            conversion_to_base_snapshot, qty_base, unit_price_vnd, line_total_vnd
         ) VALUES (?, 1, 'pro_006', 'Bò một nắng snapshot', 'pro_006_500G',
                   'Gói 500g snapshot', 'Gia Lai', 1, 1, 1, 330000, 330000)"
    )->execute([$orderId]);

    $preview = $service->previewFromSelectedOrders([$orderId]);
    assertSameValue(1, $preview['order_count'], 'PO preview must include selected order.');
    assertSameValue(1.0, (float) $preview['items'][0]['qty_planned_uom'], 'PO preview must group planned quantity.');

    $planId = $service->createFromSelectedOrders([$orderId], ['note' => 'TEST PO flow']);
    assertSameValue('ordered', (string) $pdo->query("SELECT status FROM orders WHERE order_id = " . $pdo->quote($orderId))->fetchColumn(), 'Creating PO must mark order ordered.');
    $pdo->prepare("UPDATE purchase_plans SET status = 'draft' WHERE plan_id = ?")->execute([$planId]);
    $detail = $service->getDetail($planId);
    assertSameValue(true, $detail['can_mark_ordered'], 'Draft PO must expose an ordering action.');
    assertSameValue(false, $detail['can_receive'], 'Draft PO must not be receivable before ordering.');
    $service->markPlanOrdered($planId);
    assertSameValue('ordered', (string) $pdo->query("SELECT status FROM purchase_plans WHERE plan_id = " . $pdo->quote($planId))->fetchColumn(), 'Ordering a draft PO must persist ordered status.');
    $detail = $service->getDetail($planId);
    assertTrue(is_array($detail), 'Created PO detail must exist.');
    assertSameValue(true, $detail['can_receive'], 'Ordered PO must be receivable.');
    assertSameValue(true, $detail['can_cancel'], 'Unreceived PO must be cancellable.');
    $planItemId = (int) $detail['items'][0]['plan_item_id'];

    $service->receivePlan($planId, [
        'note' => 'TEST partial',
        'items' => [[
            'plan_item_id' => $planItemId,
            'qty_received_uom' => 0.5,
            'cost_per_uom_vnd' => 230000,
            'received_date' => '2026-06-21',
            'expiry_date' => '2026-12-21',
            'supplier_name' => 'TEST NCC',
            'note' => 'TEST partial receive',
        ]],
    ]);
    $detail = $service->getDetail($planId);
    assertSameValue('partial_received', $detail['status'], 'Partial receipt must update PO status.');
    assertSameValue(0.5, (float) $detail['items'][0]['qty_remaining_uom'], 'Partial receipt must expose remaining quantity.');
    assertSameValue(false, $detail['can_cancel'], 'PO with a receipt must not be cancellable.');

    $service->receivePlan($planId, [
        'note' => 'TEST full',
        'items' => [[
            'plan_item_id' => $planItemId,
            'qty_received_uom' => 0.5,
            'cost_per_uom_vnd' => 230000,
            'received_date' => '2026-06-21',
            'expiry_date' => '2026-12-21',
            'supplier_name' => 'TEST NCC',
            'note' => 'TEST full receive',
        ]],
    ]);
    $detail = $service->getDetail($planId);
    assertSameValue('received', $detail['status'], 'Full receipt must update PO status.');
    assertSameValue(false, $detail['can_receive'], 'Fully received PO must not be receivable.');
    assertSameValue('received', (string) $pdo->query("SELECT status FROM orders WHERE order_id = " . $pdo->quote($orderId))->fetchColumn(), 'Full PO receipt must mark linked order received.');
    assertThrows(fn () => $service->cancelPlan($planId), AppException::class, 422);

    $lotIds = $pdo->query(
        "SELECT lot_id FROM purchase_plan_receipt_items WHERE plan_id = " . $pdo->quote($planId)
    )->fetchAll(PDO::FETCH_COLUMN);
} finally {
    if ($planId !== null) {
        $lotIds = $lotIds ?: $pdo->query(
            "SELECT lot_id FROM purchase_plan_receipt_items WHERE plan_id = " . $pdo->quote($planId)
        )->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare('DELETE FROM purchase_plan_receipt_items WHERE plan_id = ?')->execute([$planId]);
        $pdo->prepare('DELETE FROM purchase_plan_receipts WHERE plan_id = ?')->execute([$planId]);
        foreach ($lotIds as $lotId) {
            $pdo->prepare('DELETE FROM inventory_movements WHERE lot_id = ?')->execute([$lotId]);
            $pdo->prepare('DELETE FROM inventory_lots WHERE lot_id = ?')->execute([$lotId]);
        }
        $pdo->prepare('DELETE FROM plan_items WHERE plan_id = ?')->execute([$planId]);
        $pdo->prepare('DELETE FROM purchase_plan_orders WHERE plan_id = ?')->execute([$planId]);
        $pdo->prepare('DELETE FROM purchase_plans WHERE plan_id = ?')->execute([$planId]);
    }
    $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$orderId]);
    $pdo->prepare('DELETE FROM orders WHERE order_id = ?')->execute([$orderId]);
}

echo "AdminApiSmokeTest: PASS\n";
