<?php

declare(strict_types=1);

use DacSanNhaDan\Core\Csrf;
use DacSanNhaDan\Core\Database;
use DacSanNhaDan\Repositories\CustomerRepository;
use DacSanNhaDan\Repositories\InventoryRepository;
use DacSanNhaDan\Repositories\OrderRepository;
use DacSanNhaDan\Repositories\ProductRepository;
use DacSanNhaDan\Repositories\SettingRepository;
use DacSanNhaDan\Repositories\ShippingRepository;
use DacSanNhaDan\Services\CartQuoteService;
use DacSanNhaDan\Services\CheckoutService;
use DacSanNhaDan\Services\InventoryService;
use DacSanNhaDan\Services\OrderService;
use DacSanNhaDan\Support\IdGenerator;

require dirname(__DIR__) . '/app/bootstrap.php';

function fefo_fail(string $message): void
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit(1);
}

function fefo_pass(string $message): void
{
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
}

function fefo_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function fefo_count(PDO $pdo, string $sql, array $params = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

function fefo_create_checkout_order(PDO $pdo, CheckoutService $checkoutService, string $productId, string $uomId, string $phone): string
{
    $token = Csrf::checkoutToken(true);
    $result = $checkoutService->checkout([
        'checkout_token' => $token,
        'customer_name' => 'Khach Test FEFO',
        'customer_phone' => $phone,
        'customer_address' => '123 Duong Test FEFO, Quan 1, TP HCM',
        'receive_date' => '2026-06-01',
        'shipping_method' => 'delivery',
        'payment_method' => 'COD',
        'note' => 'Don hang FEFO smoke test, se cleanup.',
        'items' => [[
            'product_id' => $productId,
            'uom_id' => $uomId,
            'qty' => 1,
        ]],
    ]);

    return (string) $result['order_id'];
}

function fefo_cleanup(PDO $pdo, array $orderIds, array $phones): void
{
    $orderIds = array_values(array_unique(array_filter($orderIds)));
    $phones = array_values(array_unique(array_filter($phones)));

    if ($orderIds !== []) {
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

        $allocStmt = $pdo->prepare(
            "SELECT lot_id, qty_base
             FROM order_item_allocations
             WHERE order_id IN ($placeholders)"
        );
        $allocStmt->execute($orderIds);
        $allocations = $allocStmt->fetchAll();

        $movementStmt = $pdo->prepare(
            "SELECT movement_id
             FROM inventory_movements
             WHERE ref_type = 'ORDER'
               AND ref_id IN ($placeholders)"
        );
        $movementStmt->execute($orderIds);
        $movementIds = array_map('strval', $movementStmt->fetchAll(PDO::FETCH_COLUMN));

        $pdo->beginTransaction();
        try {
            $restoreStmt = $pdo->prepare(
                'UPDATE inventory_lots
                 SET qty_base_on_hand = qty_base_on_hand + :qty_base
                 WHERE lot_id = :lot_id'
            );
            foreach ($allocations as $allocation) {
                $restoreStmt->execute([
                    'qty_base' => (float) $allocation['qty_base'],
                    'lot_id' => (string) $allocation['lot_id'],
                ]);
            }

            $deleteOrders = $pdo->prepare("DELETE FROM orders WHERE order_id IN ($placeholders)");
            $deleteOrders->execute($orderIds);

            if ($movementIds !== []) {
                $movementPlaceholders = implode(',', array_fill(0, count($movementIds), '?'));
                $deleteMovements = $pdo->prepare("DELETE FROM inventory_movements WHERE movement_id IN ($movementPlaceholders)");
                $deleteMovements->execute($movementIds);
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
}

$pdo = Database::connection();
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
    $inventoryService = new InventoryService($inventoryRepository);
    $orderService = new OrderService($pdo, $orderRepository, $inventoryService);
    $checkoutService = new CheckoutService(
        $pdo,
        new CartQuoteService($productRepository, $inventoryRepository, $shippingRepository, $settingRepository),
        $customerRepository,
        $orderRepository
    );

    $seedLine = $pdo->query(
        "SELECT c.product_id, u.uom_id, u.conversion_to_base, c.default_source,
                s.qty_base_available
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
    fefo_assert(is_array($seedLine), 'Need an active product with enough stock for FEFO test.');

    $productId = (string) $seedLine['product_id'];
    $uomId = (string) $seedLine['uom_id'];
    $qtyBase = (float) $seedLine['conversion_to_base'];
    $beforeAvailable = $inventoryRepository->getAvailableBaseQty($productId);
    $beforeMovementCount = fefo_count($pdo, 'SELECT COUNT(*) FROM inventory_movements');
    $beforeAllocationCount = fefo_count($pdo, 'SELECT COUNT(*) FROM order_item_allocations');

    fefo_assert($orderService->getAllowedTransitions('new') === ['confirmed', 'cancelled'], 'Allowed transitions for new are wrong.');
    fefo_assert($orderService->isTransitionAllowed('ready', 'done'), 'ready -> done should be allowed.');
    fefo_assert(!$orderService->isTransitionAllowed('new', 'done'), 'new -> done should not be allowed.');
    fefo_pass('state machine basics OK');

    $phone = '091' . (string) random_int(1000000, 9999999);
    $phones[] = $phone;
    $orderId = fefo_create_checkout_order($pdo, $checkoutService, $productId, $uomId, $phone);
    $orderIds[] = $orderId;

    $orderService->changeStatus($orderId, 'confirmed');
    $orderService->changeStatus($orderId, 'ordered');
    $orderService->changeStatus($orderId, 'received');
    $orderService->changeStatus($orderId, 'ready');
    $doneResult = $orderService->changeStatus($orderId, 'done');
    fefo_assert(($doneResult['status'] ?? '') === 'done', 'Order should transition to done.');
    fefo_pass('status flow new -> confirmed -> ordered -> received -> ready -> done OK');

    $order = $orderRepository->findOrderById($orderId);
    fefo_assert(($order['status'] ?? '') === 'done', 'Final order status should be done.');

    $afterAvailable = $inventoryRepository->getAvailableBaseQty($productId);
    fefo_assert(abs(($beforeAvailable - $afterAvailable) - $qtyBase) < 0.001, 'Inventory lot should be deducted by order qty_base.');

    $movementCountForOrder = fefo_count(
        $pdo,
        "SELECT COUNT(*) FROM inventory_movements WHERE ref_type = 'ORDER' AND ref_id = :order_id AND movement_type = 'OUT'",
        ['order_id' => $orderId]
    );
    $allocationCountForOrder = fefo_count(
        $pdo,
        'SELECT COUNT(*) FROM order_item_allocations WHERE order_id = :order_id',
        ['order_id' => $orderId]
    );
    fefo_assert($movementCountForOrder > 0, 'Movement OUT should be created.');
    fefo_assert($allocationCountForOrder > 0, 'Order item allocation should be created.');
    fefo_pass('FEFO deduction, movement OUT, allocation OK');

    try {
        $orderService->changeStatus($orderId, 'done');
        throw new RuntimeException('Done twice should fail.');
    } catch (Throwable $exception) {
        fefo_assert(str_contains($exception->getMessage(), 'không hợp lệ') || str_contains($exception->getMessage(), 'hoàn tất'), 'Done twice should be rejected.');
    }
    fefo_pass('done twice rejected OK');

    $skipPhone = '092' . (string) random_int(1000000, 9999999);
    $phones[] = $skipPhone;
    $skipOrderId = fefo_create_checkout_order($pdo, $checkoutService, $productId, $uomId, $skipPhone);
    $orderIds[] = $skipOrderId;
    try {
        $orderService->changeStatus($skipOrderId, 'done');
        throw new RuntimeException('new -> done should fail.');
    } catch (Throwable $exception) {
        fefo_assert(str_contains($exception->getMessage(), 'không hợp lệ'), 'new -> done should be rejected.');
    }
    fefo_assert(
        fefo_count($pdo, "SELECT COUNT(*) FROM inventory_movements WHERE ref_type = 'ORDER' AND ref_id = :order_id", ['order_id' => $skipOrderId]) === 0,
        'Rejected skip transition should not create movement.'
    );
    fefo_pass('skip new -> done rejected with clean rollback OK');

    $manualOrderId = IdGenerator::orderId();
    $orderIds[] = $manualOrderId;
    $pdo->beginTransaction();
    $orderRepository->createOrder([
        'order_id' => $manualOrderId,
        'customer_id' => null,
        'status' => 'ready',
        'customer_name' => 'Khach Test FEFO Thieu Ton',
        'customer_phone' => '093' . (string) random_int(1000000, 9999999),
        'customer_address' => 'Dia chi test',
        'receive_date' => null,
        'note' => 'Don hang FEFO insufficient stock smoke test, se cleanup.',
        'shipping_method' => 'pickup',
        'shipping_zone_id' => null,
        'shipping_fee_vnd' => 0,
        'subtotal_vnd' => 1,
        'total_vnd' => 1,
        'source_summary' => (string) ($seedLine['default_source'] ?: 'Unknown'),
    ]);
    $orderRepository->createOrderItems($manualOrderId, [[
        'product_id' => $productId,
        'product_name' => 'Manual FEFO Stock Test',
        'uom_id' => $uomId,
        'uom_label' => 'Manual UOM',
        'source_location' => (string) ($seedLine['default_source'] ?: 'Unknown'),
        'qty_uom' => 999999,
        'conversion_to_base' => 1,
        'qty_base' => 999999,
        'unit_price_vnd' => 1,
        'line_total_vnd' => 1,
    ]]);
    $pdo->commit();

    $movementBeforeFail = fefo_count($pdo, 'SELECT COUNT(*) FROM inventory_movements');
    $allocationBeforeFail = fefo_count($pdo, 'SELECT COUNT(*) FROM order_item_allocations');
    try {
        $orderService->changeStatus($manualOrderId, 'done');
        throw new RuntimeException('Insufficient stock should fail.');
    } catch (Throwable $exception) {
        fefo_assert(str_contains($exception->getMessage(), 'Không đủ tồn kho'), 'Insufficient stock should produce stock error.');
    }
    fefo_assert(fefo_count($pdo, 'SELECT COUNT(*) FROM inventory_movements') === $movementBeforeFail, 'Failed FEFO should rollback movements.');
    fefo_assert(fefo_count($pdo, 'SELECT COUNT(*) FROM order_item_allocations') === $allocationBeforeFail, 'Failed FEFO should rollback allocations.');
    fefo_pass('insufficient stock rollback OK');

    fefo_assert(fefo_count($pdo, 'SELECT COUNT(*) FROM inventory_movements') === $beforeMovementCount + $movementCountForOrder, 'Only successful order should add OUT movements before cleanup.');
    fefo_assert(fefo_count($pdo, 'SELECT COUNT(*) FROM order_item_allocations') === $beforeAllocationCount + $allocationCountForOrder, 'Only successful order should add allocations before cleanup.');
    fefo_pass('FEFO smoke test passed.');
} catch (Throwable $exception) {
    $failure = $exception->getMessage();
} finally {
    try {
        fefo_cleanup($pdo, $orderIds, $phones);
    } catch (Throwable $cleanupException) {
        $failure = trim((string) $failure . ' Cleanup failed: ' . $cleanupException->getMessage());
    }
}

if ($failure !== null) {
    fefo_fail($failure);
}
