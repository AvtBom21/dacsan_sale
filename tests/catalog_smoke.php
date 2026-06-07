<?php

declare(strict_types=1);

use DacSanNhaDan\Core\Database;
use DacSanNhaDan\Repositories\InventoryRepository;
use DacSanNhaDan\Repositories\ProductRepository;
use DacSanNhaDan\Repositories\SettingRepository;
use DacSanNhaDan\Repositories\ShippingRepository;
use DacSanNhaDan\Services\CatalogService;
use DacSanNhaDan\Services\SettingsService;
use DacSanNhaDan\Services\ShippingService;

require dirname(__DIR__) . '/app/bootstrap.php';

function catalog_fail(string $message): void
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit(1);
}

function catalog_pass(string $message): void
{
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
}

function catalog_assert(bool $condition, string $message): void
{
    if (!$condition) {
        catalog_fail($message);
    }
}

try {
    $pdo = Database::connection();

    $settingRepository = new SettingRepository($pdo);
    $productRepository = new ProductRepository($pdo);
    $inventoryRepository = new InventoryRepository($pdo);
    $shippingRepository = new ShippingRepository($pdo);

    $settingsService = new SettingsService($settingRepository);
    $shippingService = new ShippingService($shippingRepository);
    $catalogService = new CatalogService($productRepository, $inventoryRepository);

    $settings = $settingsService->publicSettings();
    catalog_assert(($settings['store_name'] ?? '') !== '', 'Public settings should include store_name.');
    catalog_pass('load settings OK');

    $catalog = $catalogService->catalog();
    catalog_assert(count($catalog) > 0, 'Catalog should contain active products.');
    catalog_pass('load catalog OK');

    $filteredCatalog = $catalogService->catalog(['q' => (string) $catalog[0]['product_name']]);
    catalog_assert(count($filteredCatalog) > 0, 'Catalog search filter should return products.');
    catalog_pass('catalog search filter OK');

    foreach ($catalog as $product) {
        catalog_assert(($product['product_id'] ?? '') !== '', 'Each product should have product_id.');
        catalog_assert(count($product['sellable_uoms'] ?? []) > 0, 'Each active product should have sellable UOMs.');
        catalog_assert(is_array($product['base_image'] ?? null), 'Each product should have a base_image payload.');
        catalog_assert(($product['base_image']['url'] ?? '') !== '', 'Image fallback should provide a non-empty URL.');
        catalog_assert(count($product['gallery_images'] ?? []) > 0, 'Each product should have gallery images or fallback image.');
        catalog_assert(($product['stock']['badge'] ?? '') !== '', 'Each product should have a stock badge.');
    }
    catalog_pass('active products have sellable UOMs, images, and stock badges');

    $detail = $catalogService->productDetail((string) $catalog[0]['product_id']);
    catalog_assert($detail !== null, 'Product detail should load for a catalog product.');
    catalog_assert(count($detail['gallery_images']) > 0, 'Product detail images should fallback without error.');
    catalog_pass('product image fallback does not error');

    $zones = $shippingService->zones();
    catalog_assert(count($zones) > 0, 'Shipping zones should load.');
    catalog_pass('shipping zones load OK');

    $stock = $catalogService->stockSummary((string) $catalog[0]['product_id']);
    catalog_assert($stock !== null, 'Stock summary should load for a catalog product.');
    catalog_assert(($stock['badge'] ?? '') !== '', 'Stock summary should include badge.');
    catalog_pass('stock summary load OK');

    catalog_pass('Catalog smoke test passed.');
} catch (Throwable $exception) {
    catalog_fail($exception->getMessage());
}
