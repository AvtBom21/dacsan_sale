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

function po_fail(string $message): void
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit(1);
}

function po_pass(string $message): void
{
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
}

function po_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function po_create_confirmed_order(
    CheckoutService $checkoutService,
    OrderService $orderService,
    string $productId,
    string $uomId,
    string $phone
): string {
    $token = Csrf::checkoutToken(true);
    $result = $checkoutService->checkout([
        'checkout_token' => $token,
        'customer_name' => 'Khach Test PO',
        'customer_phone' => $phone,
        'customer_address' => '123 Duong Test PO, Quan 1, TP HCM',
        'receive_date' => '2026-06-01',
        'shipping_method' => 'delivery',
        'payment_method' => 'COD',
        'note' => 'Don hang PO smoke test, se cleanup.',
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

function po_cleanup(PDO $pdo, array $planIds, array $orderIds, array $phones): void
{
    $planIds = array_values(array_unique(array_filter($planIds)));
    $orderIds = array_values(array_unique(array_filter($orderIds)));
    $phones = array_values(array_unique(array_filter($phones)));

    $pdo->beginTransaction();
    try {
        if ($planIds !== []) {
            $planPlaceholders = implode(',', array_fill(0, count($planIds), '?'));
            $deletePlanOrders = $pdo->prepare("DELETE FROM purchase_plan_orders WHERE plan_id IN ($planPlaceholders)");
            $deletePlanOrders->execute($planIds);

            $deletePlans = $pdo->prepare("DELETE FROM purchase_plans WHERE plan_id IN ($planPlaceholders)");
            $deletePlans->execute($planIds);
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
    $orderService = new OrderService($pdo, $orderRepository, new InventoryService($inventoryRepository));
    $checkoutService = new CheckoutService(
        $pdo,
        new CartQuoteService($productRepository, $inventoryRepository, $shippingRepository, $settingRepository),
        $customerRepository,
        $orderRepository
    );
    $purchasePlanService = new PurchasePlanService($pdo, $purchasePlanRepository, $orderService);

    $seedLine = $pdo->query(
        "SELECT c.product_id, u.uom_id, u.conversion_to_base
         FROM v_product_cards c
         JOIN product_uoms u
           ON u.product_id = c.product_id
          AND u.is_active = 1
          AND u.is_sellable = 1
         JOIN v_inventory_summary s ON s.product_id = c.product_id
         WHERE s.qty_base_available >= (u.conversion_to_base * 2)
         ORDER BY c.product_id, u.is_default DESC, u.sort_order
         LIMIT 1"
    )->fetch();
    po_assert(is_array($seedLine), 'Need an active product with enough stock for PO test.');

    $productId = (string) $seedLine['product_id'];
    $uomId = (string) $seedLine['uom_id'];
    $conversion = (float) $seedLine['conversion_to_base'];

    for ($i = 0; $i < 2; $i++) {
        $phone = '095' . (string) random_int(1000000, 9999999);
        $phones[] = $phone;
        $orderIds[] = po_create_confirmed_order($checkoutService, $orderService, $productId, $uomId, $phone);
    }

    $preview = $purchasePlanService->previewFromSelectedOrders($orderIds);
    po_assert(count($preview['orders']) === 2, 'Preview should include two confirmed orders.');
    po_assert(count($preview['items']) === 1, 'Preview should group matching order items into one PO item.');
    po_assert((float) $preview['items'][0]['qty_needed_uom'] === 2.0, 'Preview grouped qty_uom should equal 2.');
    po_assert(abs((float) $preview['items'][0]['qty_needed_base'] - (2 * $conversion)) < 0.001, 'Preview grouped qty_base should match conversion.');
    po_pass('PO preview grouping OK');

    $planId = $purchasePlanService->createFromSelectedOrders($orderIds, ['note' => 'PO smoke test, se cleanup.']);
    $planIds[] = $planId;
    po_assert(str_starts_with($planId, 'PO-' . date('Ymd') . '-'), 'plan_id should use PO-YYYYMMDD-XXXXX format.');

    $detail = $purchasePlanService->getDetail($planId);
    po_assert($detail !== null, 'PO detail should load.');
    po_assert(($detail['status'] ?? '') === 'ordered', 'PO status should be ordered after creation.');
    po_assert(count($detail['orders']) === 2, 'PO should link both orders.');
    po_assert(count($detail['items']) === 1, 'PO should have one grouped item.');
    po_assert((float) $detail['items'][0]['qty_planned_uom'] === 2.0, 'Plan item planned qty should equal grouped qty.');

    foreach ($orderIds as $orderId) {
        $order = $orderRepository->findOrderById($orderId);
        po_assert(($order['status'] ?? '') === 'ordered', 'Order should be moved to ordered after PO creation.');
    }
    po_pass('PO creation, plan_items, links, order status OK');

    try {
        $purchasePlanService->createFromSelectedOrders($orderIds);
        throw new RuntimeException('Duplicate PO creation should fail.');
    } catch (Throwable $exception) {
        po_assert(str_contains($exception->getMessage(), 'không còn item') || str_contains($exception->getMessage(), 'trùng'), 'Duplicate PO creation should be rejected.');
    }
    po_pass('duplicate PO rejected OK');

    $copyText = $purchasePlanService->copyPurchasePlanText($planId);
    po_assert(str_contains($copyText, 'PHIẾU ĐẶT HÀNG'), 'PO copy text should be Vietnamese.');
    po_assert(str_contains($copyText, $planId), 'PO copy text should include plan id.');
    po_pass('PO copy text OK');

    $purchasePlanService->cancelPlan($planId);
    $cancelled = $purchasePlanService->getDetail($planId);
    po_assert(($cancelled['status'] ?? '') === 'cancelled', 'PO should be cancelled.');
    foreach ($orderIds as $orderId) {
        $order = $orderRepository->findOrderById($orderId);
        po_assert(($order['status'] ?? '') === 'confirmed', 'Cancelled PO should return test orders to confirmed.');
    }
    po_pass('PO cancel before receipt OK');

    po_pass('Purchase plan smoke test passed.');
} catch (Throwable $exception) {
    $failure = $exception->getMessage();
} finally {
    try {
        po_cleanup($pdo, $planIds, $orderIds, $phones);
    } catch (Throwable $cleanupException) {
        $failure = trim((string) $failure . ' Cleanup failed: ' . $cleanupException->getMessage());
    }
}

if ($failure !== null) {
    po_fail($failure);
}
