<?php

declare(strict_types=1);

use DacSanNhaDan\Core\Csrf;
use DacSanNhaDan\Core\Database;
use DacSanNhaDan\Repositories\CustomerRepository;
use DacSanNhaDan\Repositories\InventoryRepository;
use DacSanNhaDan\Repositories\OrderRepository;
use DacSanNhaDan\Repositories\ProductRepository;
use DacSanNhaDan\Repositories\PurchasePlanRepository;
use DacSanNhaDan\Repositories\SettingRepository;
use DacSanNhaDan\Repositories\ShippingRepository;
use DacSanNhaDan\Services\CartQuoteService;
use DacSanNhaDan\Services\CheckoutService;
use DacSanNhaDan\Services\InventoryService;
use DacSanNhaDan\Services\OrderService;
use DacSanNhaDan\Services\PurchasePlanService;

require dirname(__DIR__) . '/app/bootstrap.php';

function receive_fail(string $message): void
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit(1);
}

function receive_pass(string $message): void
{
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
}

function receive_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function receive_count(PDO $pdo, string $sql, array $params = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

function receive_create_confirmed_order(
    CheckoutService $checkoutService,
    OrderService $orderService,
    string $productId,
    string $uomId,
    string $phone
): string {
    $token = Csrf::checkoutToken(true);
    $result = $checkoutService->checkout([
        'checkout_token' => $token,
        'customer_name' => 'Khach Test Receive PO',
        'customer_phone' => $phone,
        'customer_address' => '123 Duong Test Receive PO, Quan 1, TP HCM',
        'receive_date' => '2026-06-01',
        'shipping_method' => 'delivery',
        'payment_method' => 'COD',
        'note' => 'Don hang receive PO smoke test, se cleanup.',
        'items' => [[
            'product_id' => $productId,
            'uom_id' => $uomId,
            'qty' => 1,
        ]],
    ]);
    $orderId = (string) $result['order_id'];
    $orderService->changeStatus($orderId, 'confirmed');

    return $orderId;
}

function receive_cleanup(PDO $pdo, array $planIds, array $orderIds, array $phones): void
{
    $planIds = array_values(array_unique(array_filter($planIds)));
    $orderIds = array_values(array_unique(array_filter($orderIds)));
    $phones = array_values(array_unique(array_filter($phones)));

    $lotIds = [];
    if ($planIds !== []) {
        $planPlaceholders = implode(',', array_fill(0, count($planIds), '?'));
        $lotStmt = $pdo->prepare(
            "SELECT DISTINCT lot_id
             FROM purchase_plan_receipt_items
             WHERE plan_id IN ($planPlaceholders)"
        );
        $lotStmt->execute($planIds);
        $lotIds = array_values(array_filter(array_map('strval', $lotStmt->fetchAll(PDO::FETCH_COLUMN))));
    }

    $pdo->beginTransaction();
    try {
        if ($planIds !== []) {
            $planPlaceholders = implode(',', array_fill(0, count($planIds), '?'));

            $deleteReceiptItems = $pdo->prepare("DELETE FROM purchase_plan_receipt_items WHERE plan_id IN ($planPlaceholders)");
            $deleteReceiptItems->execute($planIds);

            $deleteReceipts = $pdo->prepare("DELETE FROM purchase_plan_receipts WHERE plan_id IN ($planPlaceholders)");
            $deleteReceipts->execute($planIds);

            $deleteMovements = $pdo->prepare("DELETE FROM inventory_movements WHERE ref_type = 'PLAN' AND ref_id IN ($planPlaceholders)");
            $deleteMovements->execute($planIds);

            $deletePlanOrders = $pdo->prepare("DELETE FROM purchase_plan_orders WHERE plan_id IN ($planPlaceholders)");
            $deletePlanOrders->execute($planIds);

            $deletePlans = $pdo->prepare("DELETE FROM purchase_plans WHERE plan_id IN ($planPlaceholders)");
            $deletePlans->execute($planIds);
        }

        if ($lotIds !== []) {
            $lotPlaceholders = implode(',', array_fill(0, count($lotIds), '?'));
            $deleteLots = $pdo->prepare("DELETE FROM inventory_lots WHERE lot_id IN ($lotPlaceholders)");
            $deleteLots->execute($lotIds);
        }

        if ($orderIds !== []) {
            $orderPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
            $deleteOrderPlanLinks = $pdo->prepare("DELETE FROM purchase_plan_orders WHERE order_id IN ($orderPlaceholders)");
            $deleteOrderPlanLinks->execute($orderIds);

            $deleteOrders = $pdo->prepare("DELETE FROM orders WHERE order_id IN ($orderPlaceholders)");
            $deleteOrders->execute($orderIds);
        }

        if ($phones !== []) {
            $phonePlaceholders = implode(',', array_fill(0, count($phones), '?'));
            $deleteCustomers = $pdo->prepare(
                "DELETE c
                 FROM customers c
                 LEFT JOIN orders o ON o.customer_id = c.customer_id
                 WHERE c.customer_phone IN ($phonePlaceholders)
                   AND o.order_id IS NULL"
            );
            $deleteCustomers->execute($phones);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

$pdo = Database::connection();
$planIds = [];
$orderIds = [];
$phones = [];
$failure = null;

try {
    $productRepository = new ProductRepository($pdo);
    $inventoryRepository = new InventoryRepository($pdo);
    $settingRepository = new SettingRepository($pdo);
    $shippingRepository = new ShippingRepository($pdo);
    $customerRepository = new CustomerRepository($pdo);
    $orderRepository = new OrderRepository($pdo);
    $purchasePlanRepository = new PurchasePlanRepository($pdo);
    $inventoryService = new InventoryService($inventoryRepository);
    $orderService = new OrderService($pdo, $orderRepository, $inventoryService);
    $checkoutService = new CheckoutService(
        $pdo,
        new CartQuoteService($productRepository, $inventoryRepository, $shippingRepository, $settingRepository),
        $customerRepository,
        $orderRepository
    );
    $purchasePlanService = new PurchasePlanService($pdo, $purchasePlanRepository, $orderService, $inventoryService);

    $seedLine = $pdo->query(
        "SELECT c.product_id, u.uom_id, u.conversion_to_base
         FROM v_product_cards c
         JOIN product_uoms u
           ON u.product_id = c.product_id
          AND u.is_active = 1
          AND u.is_sellable = 1
         JOIN v_inventory_summary s ON s.product_id = c.product_id
         WHERE s.qty_base_available >= u.conversion_to_base
         ORDER BY c.product_id, u.is_default DESC, u.sort_order
         LIMIT 1"
    )->fetch();
    receive_assert(is_array($seedLine), 'Need an active product with enough stock for receive PO test.');

    $productId = (string) $seedLine['product_id'];
    $uomId = (string) $seedLine['uom_id'];
    $conversion = (float) $seedLine['conversion_to_base'];
    $phone = '096' . (string) random_int(1000000, 9999999);
    $phones[] = $phone;
    $orderId = receive_create_confirmed_order($checkoutService, $orderService, $productId, $uomId, $phone);
    $orderIds[] = $orderId;

    $planId = $purchasePlanService->createFromSelectedOrders($orderIds, ['note' => 'Receive PO smoke test, se cleanup.']);
    $planIds[] = $planId;
    $detail = $purchasePlanService->getDetail($planId);
    receive_assert($detail !== null, 'PO detail should load.');
    receive_assert(($detail['status'] ?? '') === 'ordered', 'PO should start as ordered.');
    receive_assert(count($detail['items']) === 1, 'PO should have one test item.');
    $planItemId = (int) $detail['items'][0]['plan_item_id'];

    $firstReceiptId = $purchasePlanService->receivePlan($planId, [
        'note' => 'Partial receive smoke test.',
        'items' => [[
            'plan_item_id' => $planItemId,
            'qty_received_uom' => 0.5,
            'received_date' => '2026-06-02',
            'expiry_date' => '2026-12-31',
            'supplier_name' => 'Nha cung cap test',
            'cost_per_uom_vnd' => 120000,
            'note' => 'Partial lot.',
        ]],
    ]);
    receive_assert(str_starts_with($firstReceiptId, 'RCV-' . date('Ymd') . '-'), 'receipt_id should use RCV-YYYYMMDD-XXXXX format.');

    $partial = $purchasePlanService->getDetail($planId);
    receive_assert(($partial['status'] ?? '') === 'partial_received', 'Partial receive should set PO status to partial_received.');
    receive_assert(count($partial['receipts'] ?? []) === 1, 'Partial receive should create one receipt.');
    receive_assert(count($partial['receipt_items'] ?? []) === 1, 'Partial receive should create one receipt item.');
    receive_assert(abs((float) $partial['items'][0]['qty_received_uom'] - 0.5) < 0.001, 'Plan item received qty_uom should be 0.5.');
    receive_assert(abs((float) $partial['items'][0]['qty_received_base'] - (0.5 * $conversion)) < 0.001, 'Plan item received qty_base should match conversion.');
    receive_assert(
        receive_count($pdo, "SELECT COUNT(*) FROM inventory_movements WHERE ref_type = 'PLAN' AND ref_id = :plan_id AND movement_type = 'IN'", ['plan_id' => $planId]) === 1,
        'Partial receive should create one IN movement.'
    );
    receive_pass('partial PO receive creates receipt, lot, movement IN, and partial status OK');

    $orderAfterPartial = $orderRepository->findOrderById($orderId);
    receive_assert(($orderAfterPartial['status'] ?? '') === 'ordered', 'Linked order should remain ordered after partial receive.');

    $secondReceiptId = $purchasePlanService->receivePlan($planId, [
        'note' => 'Final receive smoke test.',
        'items' => [[
            'plan_item_id' => $planItemId,
            'qty_received_uom' => 0.5,
            'received_date' => '2026-06-03',
            'expiry_date' => '2026-12-31',
            'supplier_name' => 'Nha cung cap test',
            'cost_per_uom_vnd' => 120000,
            'note' => 'Final lot.',
        ]],
    ]);
    receive_assert($secondReceiptId !== $firstReceiptId, 'Second receive should create a different receipt.');

    $received = $purchasePlanService->getDetail($planId);
    receive_assert(($received['status'] ?? '') === 'received', 'Full receive should set PO status to received.');
    receive_assert(count($received['receipts'] ?? []) === 2, 'Full receive flow should have two receipts.');
    receive_assert(count($received['receipt_items'] ?? []) === 2, 'Full receive flow should have two receipt items.');
    receive_assert(abs((float) $received['items'][0]['qty_received_uom'] - 1.0) < 0.001, 'Plan item received qty_uom should be full.');
    receive_assert(
        receive_count($pdo, "SELECT COUNT(*) FROM inventory_movements WHERE ref_type = 'PLAN' AND ref_id = :plan_id AND movement_type = 'IN'", ['plan_id' => $planId]) === 2,
        'Full receive flow should create two IN movements.'
    );

    $orderAfterFull = $orderRepository->findOrderById($orderId);
    receive_assert(($orderAfterFull['status'] ?? '') === 'received', 'Linked order should move ordered -> received after full PO receive.');
    receive_pass('full PO receive updates plan and linked order status OK');

    try {
        $purchasePlanService->receivePlan($planId, [
            'items' => [[
                'plan_item_id' => $planItemId,
                'qty_received_uom' => 0.1,
                'received_date' => '2026-06-04',
            ]],
        ]);
        throw new RuntimeException('Over receiving should fail.');
    } catch (Throwable $exception) {
        receive_assert($exception->getMessage() !== '', 'Over receiving should be rejected.');
    }
    receive_pass('over receive rejected OK');

    receive_pass('Receive PO smoke test passed.');
} catch (Throwable $exception) {
    $failure = $exception->getMessage();
} finally {
    try {
        receive_cleanup($pdo, $planIds, $orderIds, $phones);
    } catch (Throwable $cleanupException) {
        $failure = trim((string) $failure . ' Cleanup failed: ' . $cleanupException->getMessage());
    }
}

if ($failure !== null) {
    receive_fail($failure);
}
