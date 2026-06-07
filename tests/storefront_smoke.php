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
use DacSanNhaDan\Services\CatalogService;
use DacSanNhaDan\Services\CheckoutService;
use DacSanNhaDan\Services\ShippingService;

require dirname(__DIR__) . '/app/bootstrap.php';

function storefront_fail(string $message): void
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit(1);
}

function storefront_pass(string $message): void
{
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
}

function storefront_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function storefront_run_php(string $file): string
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file);
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException('PHP page render failed: ' . $file);
    }

    return implode(PHP_EOL, $output);
}

$pdo = Database::connection();
$orderId = null;
$phone = '098' . (string) random_int(1000000, 9999999);
$failure = null;

try {
    $html = storefront_run_php(dirname(__DIR__) . '/public/index.php');
    storefront_assert(str_contains($html, 'Đặc sản nhà làm từ cao nguyên Gia Lai đến duyên hải Bình Định'), 'Storefront hero headline should render.');
    storefront_assert(str_contains($html, 'id="gialai-products"'), 'Gia Lai product rail should render.');
    storefront_assert(str_contains($html, 'id="binhdinh-products"'), 'Binh Dinh product rail should render.');
    storefront_assert(str_contains($html, 'id="cart-sidebar"'), 'Storefront cart sidebar should render.');
    storefront_assert(str_contains($html, 'id="product-modal"'), 'Product modal should render.');
    storefront_assert(str_contains($html, 'id="checkout-modal"'), 'Checkout modal should render.');
    storefront_assert(str_contains($html, '/assets/css/store.css') || str_contains($html, '/public/assets/css/store.css'), 'Storefront CSS should be linked.');
    storefront_assert(str_contains($html, '/assets/js/store.js') || str_contains($html, '/public/assets/js/store.js'), 'Storefront JS should be linked.');
    storefront_pass('storefront page load OK');

    $productRepository = new ProductRepository($pdo);
    $inventoryRepository = new InventoryRepository($pdo);
    $settingRepository = new SettingRepository($pdo);
    $shippingRepository = new ShippingRepository($pdo);
    $customerRepository = new CustomerRepository($pdo);
    $orderRepository = new OrderRepository($pdo);

    $catalogService = new CatalogService($productRepository, $inventoryRepository);
    $shippingService = new ShippingService($shippingRepository);
    $quoteService = new CartQuoteService($productRepository, $inventoryRepository, $shippingRepository, $settingRepository);
    $checkoutService = new CheckoutService($pdo, $quoteService, $customerRepository, $orderRepository);

    $catalog = $catalogService->catalog();
    storefront_assert($catalog !== [], 'Catalog should load products.');
    $product = null;
    foreach ($catalog as $candidate) {
        if (($candidate['stock']['qty_base_available'] ?? 0) >= ($candidate['default_uom']['conversion_to_base'] ?? 1)) {
            $product = $candidate;
            break;
        }
    }
    storefront_assert(is_array($product), 'Need a product with enough stock for storefront checkout.');
    storefront_pass('catalog service load OK');

    $shipping = $shippingService->summary();
    storefront_assert(($shipping['zones'] ?? []) !== [], 'Shipping zones should load.');
    $zoneId = (string) $shipping['zones'][0]['zone_id'];

    $quote = $quoteService->quote([
        'shipping_method' => 'delivery',
        'shipping_zone_id' => $zoneId,
        'items' => [[
            'product_id' => $product['product_id'],
            'uom_id' => $product['default_uom']['uom_id'],
            'qty' => 1,
        ]],
    ]);
    storefront_assert(($quote['can_checkout'] ?? false) === true, 'Cart quote should be checkoutable.');
    storefront_assert((int) $quote['subtotal_vnd'] > 0, 'Cart quote subtotal should be positive.');
    storefront_pass('cart quote service OK');

    $result = $checkoutService->checkout([
        'checkout_token' => Csrf::checkoutToken(true),
        'customer_name' => 'Khach Test Storefront',
        'customer_phone' => $phone,
        'customer_address' => '123 Duong Storefront Test, Quan 1, TP HCM',
        'receive_date' => '2026-06-01',
        'shipping_method' => 'delivery',
        'shipping_zone_id' => $zoneId,
        'payment_method' => 'COD',
        'note' => 'Storefront smoke test, se cleanup.',
        'items' => [[
            'product_id' => $product['product_id'],
            'uom_id' => $product['default_uom']['uom_id'],
            'qty' => 1,
        ]],
    ]);
    $orderId = (string) $result['order_id'];
    storefront_assert(str_starts_with($orderId, 'ORD-' . date('Ymd') . '-'), 'Checkout should return formatted order id.');
    storefront_pass('checkout flow service OK');

    storefront_pass('Storefront smoke test passed.');
} catch (Throwable $exception) {
    $failure = $exception->getMessage();
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
    $statement->execute(['phone' => $phone]);
}

if ($failure !== null) {
    storefront_fail($failure);
}
