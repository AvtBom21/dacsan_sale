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

require dirname(__DIR__) . '/app/bootstrap.php';

function checkout_fail(string $message): void
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit(1);
}

function checkout_pass(string $message): void
{
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
}

function checkout_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function scalar_count(PDO $pdo, string $sql, array $params = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

$pdo = Database::connection();
$orderId = null;
$customerPhone = '090' . (string) random_int(1000000, 9999999);
$failureMessage = null;

try {
    $seedLine = $pdo->query(
        "SELECT c.product_id, c.product_name, u.uom_id, u.uom_label,
                u.conversion_to_base, u.unit_price_vnd, s.qty_base_available
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

    checkout_assert(is_array($seedLine), 'A real active product with sellable UOM and stock is required.');

    $productId = (string) $seedLine['product_id'];
    $uomId = (string) $seedLine['uom_id'];
    $dbUnitPrice = (int) $seedLine['unit_price_vnd'];
    $initialAvailable = (float) $seedLine['qty_base_available'];
    $token = Csrf::checkoutToken(true);

    checkout_assert($token !== '', 'checkout token should be generated.');
    checkout_pass('checkout token OK');

    $productRepository = new ProductRepository($pdo);
    $inventoryRepository = new InventoryRepository($pdo);
    $settingRepository = new SettingRepository($pdo);
    $shippingRepository = new ShippingRepository($pdo);
    $customerRepository = new CustomerRepository($pdo);
    $orderRepository = new OrderRepository($pdo);

    $quoteService = new CartQuoteService(
        $productRepository,
        $inventoryRepository,
        $shippingRepository,
        $settingRepository
    );
    $checkoutService = new CheckoutService(
        $pdo,
        $quoteService,
        $customerRepository,
        $orderRepository
    );

    $quote = $quoteService->quote([
        'shipping_method' => 'delivery',
        'items' => [[
            'product_id' => $productId,
            'uom_id' => $uomId,
            'qty' => 1,
            'unit_price_vnd' => 1,
            'line_total_vnd' => 1,
        ]],
    ]);

    checkout_assert($quote['can_checkout'] === true, 'cart quote should be checkoutable.');
    checkout_assert((int) $quote['items'][0]['unit_price_vnd'] === $dbUnitPrice, 'quote must ignore client unit price.');
    checkout_assert((int) $quote['items'][0]['line_total_vnd'] === $dbUnitPrice, 'quote must calculate line total server-side.');
    checkout_pass('cart quote OK and ignores client price');

    $beforeMovementCount = scalar_count($pdo, 'SELECT COUNT(*) FROM inventory_movements');
    $beforeAllocationCount = scalar_count($pdo, 'SELECT COUNT(*) FROM order_item_allocations');
    $beforeAvailable = (float) $inventoryRepository->getAvailableBaseQty($productId);

    $result = $checkoutService->checkout([
        'checkout_token' => $token,
        'customer_name' => 'Khach Test Checkout',
        'customer_phone' => $customerPhone,
        'customer_address' => '123 Duong Test, Quan 1, TP HCM',
        'receive_date' => '2026-06-01',
        'shipping_method' => 'delivery',
        'payment_method' => 'COD',
        'note' => 'Don hang smoke test, se cleanup.',
        'items' => [[
            'product_id' => $productId,
            'uom_id' => $uomId,
            'qty' => 1,
            'unit_price_vnd' => 1,
        ]],
    ]);

    $orderId = (string) $result['order_id'];
    checkout_assert(str_starts_with($orderId, 'ORD-' . date('Ymd') . '-'), 'order_id should use ORD-YYYYMMDD-XXXXX format.');
    checkout_assert(($result['message'] ?? '') === 'Đơn hàng đã được ghi nhận thành công.', 'checkout should return success message.');
    checkout_pass('checkout creates order OK');

    $order = $orderRepository->findOrderById($orderId);
    $orderItems = $orderRepository->getOrderItems($orderId);
    checkout_assert(is_array($order), 'created order should be readable.');
    checkout_assert(($order['status'] ?? '') === 'new', 'new order status should be new, got ' . var_export($order['status'] ?? null, true));
    checkout_assert(count($orderItems) === 1, 'created order should have one order item.');
    checkout_assert((string) $orderItems[0]['product_name_snapshot'] === (string) $seedLine['product_name'], 'order item product snapshot should match DB.');
    checkout_assert((string) $orderItems[0]['uom_label_snapshot'] === (string) $seedLine['uom_label'], 'order item UOM snapshot should match DB.');
    checkout_assert((int) $orderItems[0]['unit_price_vnd'] === $dbUnitPrice, 'order item unit price snapshot should match DB.');
    checkout_assert((int) $orderItems[0]['line_total_vnd'] === $dbUnitPrice, 'order item line total should match server quote.');
    checkout_assert((float) $orderItems[0]['qty_base'] === (float) $seedLine['conversion_to_base'], 'qty_base should equal qty_uom x conversion.');
    checkout_pass('order_items snapshots OK');

    $customer = $customerRepository->findByPhone($customerPhone);
    checkout_assert($customer !== null, 'customer should be upserted by phone.');
    checkout_pass('customer upsert by phone OK');

    checkout_assert(Csrf::checkoutToken() !== $token, 'checkout token should be regenerated after successful checkout.');
    checkout_pass('checkout token regenerated OK');

    $afterMovementCount = scalar_count($pdo, 'SELECT COUNT(*) FROM inventory_movements');
    $afterAllocationCount = scalar_count($pdo, 'SELECT COUNT(*) FROM order_item_allocations');
    $afterAvailable = (float) $inventoryRepository->getAvailableBaseQty($productId);
    checkout_assert($afterMovementCount === $beforeMovementCount, 'checkout must not create inventory movements.');
    checkout_assert($afterAllocationCount === $beforeAllocationCount, 'checkout must not create order item allocations.');
    checkout_assert(abs($afterAvailable - $beforeAvailable) < 0.0001, 'checkout must not deduct or reserve stock.');
    checkout_assert($initialAvailable >= $afterAvailable, 'available stock snapshot should remain valid.');
    checkout_pass('no inventory deduction/movement/allocation OK');

    checkout_pass('Checkout smoke test passed.');
} catch (Throwable $exception) {
    $failureMessage = $exception->getMessage();
} finally {
    if ($orderId !== null) {
        $statement = $pdo->prepare('DELETE FROM orders WHERE order_id = :order_id');
        $statement->execute(['order_id' => $orderId]);
    }

    $statement = $pdo->prepare(
        'DELETE c
         FROM customers c
         LEFT JOIN orders o ON o.customer_id = c.customer_id
         WHERE c.customer_phone = :phone
           AND o.order_id IS NULL'
    );
    $statement->execute(['phone' => $customerPhone]);
}

if ($failureMessage !== null) {
    checkout_fail($failureMessage);
}
