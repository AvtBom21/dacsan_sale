<?php

declare(strict_types=1);

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\AdminProductRepository;
use DacSanNhaDan\Services\AdminProductService;

require __DIR__ . '/TestSupport.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec(
    'CREATE TABLE categories (
        category_id TEXT PRIMARY KEY,
        category_name TEXT NOT NULL,
        category_slug TEXT NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE products (
        product_id TEXT PRIMARY KEY,
        product_name TEXT NOT NULL,
        product_slug TEXT NOT NULL UNIQUE,
        category_id TEXT NOT NULL,
        category_label TEXT NOT NULL,
        default_source TEXT NOT NULL,
        short_description TEXT,
        full_description TEXT,
        ingredients TEXT,
        base_uom_label TEXT NOT NULL,
        shelf_life_value INTEGER NOT NULL DEFAULT 0,
        shelf_life_unit TEXT NOT NULL DEFAULT "",
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE product_uoms (
        uom_id TEXT PRIMARY KEY,
        product_id TEXT NOT NULL,
        uom_label TEXT NOT NULL,
        conversion_to_base NUMERIC NOT NULL,
        unit_price_vnd INTEGER NOT NULL DEFAULT 0,
        cost_price_vnd INTEGER NOT NULL DEFAULT 0,
        is_base_unit INTEGER NOT NULL DEFAULT 0,
        is_default INTEGER NOT NULL DEFAULT 0,
        is_sellable INTEGER NOT NULL DEFAULT 1,
        is_purchasable INTEGER NOT NULL DEFAULT 1,
        is_active INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        note TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE product_images (
        image_id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id TEXT NOT NULL,
        image_path TEXT NOT NULL,
        source_url TEXT,
        image_alt TEXT,
        is_base INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE inventory_lots (
        lot_id TEXT PRIMARY KEY,
        product_id TEXT NOT NULL,
        qty_base_on_hand NUMERIC NOT NULL DEFAULT 0,
        qty_base_reserved NUMERIC NOT NULL DEFAULT 0,
        expiry_date TEXT
    )'
);
$pdo->exec("INSERT INTO categories VALUES ('TEST_CAT', 'Nhóm test', 'nhom-test', 1, 1)");

$service = new AdminProductService($pdo, new AdminProductRepository($pdo));

function productPayload(array $overrides = []): array
{
    $payload = [
        'product_id' => 'TEST_PRODUCT_1',
        'product_name' => 'Sản phẩm test',
        'product_slug' => 'san-pham-test',
        'category_id' => 'TEST_CAT',
        'category_label' => 'KHÔNG ĐƯỢC TIN CLIENT',
        'default_source' => 'Binh Dinh',
        'short_description' => 'Mô tả ngắn',
        'full_description' => 'Mô tả đầy đủ',
        'ingredients' => 'Nguyên liệu',
        'base_uom_label' => 'Gói',
        'shelf_life_value' => 30,
        'shelf_life_unit' => 'days',
        'is_active' => 1,
        'uoms' => [
            [
                'uom_id' => 'TEST_PRODUCT_1_BASE',
                'uom_label' => 'Gói',
                'conversion_to_base' => 1,
                'unit_price_vnd' => 100000,
                'cost_price_vnd' => 70000,
                'is_base_unit' => 1,
                'is_default' => 1,
                'is_sellable' => 1,
                'is_purchasable' => 1,
                'is_active' => 1,
                'sort_order' => 1,
                'note' => '',
            ],
        ],
        'images' => [
            [
                'image_id' => null,
                'image_path' => 'products_image/test-product.jpg',
                'source_url' => '',
                'image_alt' => 'Ảnh test',
                'is_base' => 1,
                'is_active' => 1,
                'sort_order' => 1,
            ],
        ],
    ];

    return array_replace_recursive($payload, $overrides);
}

$noUoms = productPayload();
$noUoms['uoms'] = [];
assertThrows(fn () => $service->save($noUoms), AppException::class, 422);

$twoBase = productPayload();
$twoBase['uoms'][] = [
    'uom_id' => 'TEST_PRODUCT_1_OTHER',
    'uom_label' => 'Thùng',
    'conversion_to_base' => 2,
    'unit_price_vnd' => 200000,
    'cost_price_vnd' => 140000,
    'is_base_unit' => 1,
    'is_default' => 0,
    'is_sellable' => 1,
    'is_purchasable' => 1,
    'is_active' => 1,
    'sort_order' => 2,
    'note' => '',
];
assertThrows(fn () => $service->save($twoBase), AppException::class, 422);

$invalidBase = productPayload();
$invalidBase['uoms'][0]['conversion_to_base'] = 2;
assertThrows(fn () => $service->save($invalidBase), AppException::class, 422);

$pdo->exec(
    "INSERT INTO product_uoms (
        uom_id, product_id, uom_label, conversion_to_base, unit_price_vnd,
        cost_price_vnd, is_base_unit, is_default, is_sellable,
        is_purchasable, is_active, sort_order, note
     ) VALUES (
        'FOREIGN_UOM', 'OTHER_PRODUCT', 'Khác', 1, 0,
        0, 1, 1, 1, 1, 1, 0, ''
     )"
);
$foreignUom = productPayload();
$foreignUom['uoms'][0]['uom_id'] = 'FOREIGN_UOM';
assertThrows(fn () => $service->save($foreignUom), AppException::class, 422);

$created = $service->save(productPayload());
assertSameValue('TEST_PRODUCT_1', $created['product_id'], 'Valid product should be created.');
$detail = $service->detail('TEST_PRODUCT_1');
assertSameValue('Nhóm test', $detail['category_label'], 'Category label must come from database.');
assertSameValue(1, count($detail['uoms']), 'Created UOM should be persisted.');
assertSameValue(1, count($detail['images']), 'Created image metadata should be persisted.');

assertThrows(
    fn () => $service->save(productPayload([
        'product_id' => 'TEST_PRODUCT_2',
        'product_name' => 'Trùng slug',
    ])),
    AppException::class,
    409
);

$update = productPayload([
    'original_product_id' => 'TEST_PRODUCT_1',
    'product_id' => 'TEST_PRODUCT_RENAMED',
]);
assertThrows(fn () => $service->save($update), AppException::class, 422);

$update = productPayload(['original_product_id' => 'TEST_PRODUCT_1']);
$update['uoms'][] = [
    'uom_id' => 'TEST_PRODUCT_1_BOX',
    'uom_label' => 'Thùng',
    'conversion_to_base' => 10,
    'unit_price_vnd' => 900000,
    'cost_price_vnd' => 650000,
    'is_base_unit' => 0,
    'is_default' => 0,
    'is_sellable' => 1,
    'is_purchasable' => 1,
    'is_active' => 1,
    'sort_order' => 2,
    'note' => '',
];
$service->save($update);

$update['uoms'] = [$update['uoms'][0]];
$update['images'] = [];
$service->save($update);
assertSameValue(
    0,
    (int) $pdo->query("SELECT is_active FROM product_uoms WHERE uom_id = 'TEST_PRODUCT_1_BOX'")->fetchColumn(),
    'Missing UOM must be deactivated, not deleted.'
);
assertSameValue(
    1,
    (int) $pdo->query("SELECT COUNT(*) FROM product_uoms WHERE uom_id = 'TEST_PRODUCT_1_BOX'")->fetchColumn(),
    'Deactivated UOM row must remain.'
);
assertSameValue(
    0,
    (int) $pdo->query("SELECT is_active FROM product_images WHERE product_id = 'TEST_PRODUCT_1'")->fetchColumn(),
    'Missing image metadata must be deactivated, not deleted.'
);

$beforeName = (string) $pdo->query(
    "SELECT product_name FROM products WHERE product_id = 'TEST_PRODUCT_1'"
)->fetchColumn();
$broken = productPayload([
    'original_product_id' => 'TEST_PRODUCT_1',
    'product_name' => 'Tên không được lưu',
    'uoms' => [[
        'uom_id' => 'TEST_PRODUCT_1_BASE',
        'uom_label' => null,
    ]],
]);
assertThrows(fn () => $service->save($broken), AppException::class, 422);
assertSameValue(
    $beforeName,
    (string) $pdo->query("SELECT product_name FROM products WHERE product_id = 'TEST_PRODUCT_1'")->fetchColumn(),
    'Validation failure must not partially update the product.'
);

$pdo->exec(
    "INSERT INTO product_images (
        product_id, image_path, source_url, image_alt, is_base, is_active, sort_order
     ) VALUES ('OTHER_PRODUCT', 'products_image/other.jpg', '', 'Khác', 1, 1, 1)"
);
$foreignImageId = (int) $pdo->lastInsertId();
$rollbackPayload = productPayload([
    'original_product_id' => 'TEST_PRODUCT_1',
    'product_name' => 'Tên phải rollback',
]);
$rollbackPayload['images'] = [[
    'image_id' => $foreignImageId,
    'image_path' => 'products_image/other.jpg',
    'source_url' => '',
    'image_alt' => 'Khác',
    'is_base' => 1,
    'is_active' => 1,
    'sort_order' => 1,
]];
assertThrows(fn () => $service->save($rollbackPayload), AppException::class, 422);
assertSameValue(
    $beforeName,
    (string) $pdo->query("SELECT product_name FROM products WHERE product_id = 'TEST_PRODUCT_1'")->fetchColumn(),
    'Failure during persistence must roll back the product update.'
);

$service->setActive('TEST_PRODUCT_1', false);
assertSameValue(
    0,
    (int) $pdo->query("SELECT is_active FROM products WHERE product_id = 'TEST_PRODUCT_1'")->fetchColumn(),
    'Product should be deactivated without deletion.'
);
assertSameValue(
    1,
    (int) $pdo->query("SELECT COUNT(*) FROM products WHERE product_id = 'TEST_PRODUCT_1'")->fetchColumn(),
    'Deactivated product row must remain.'
);

$managedSources = implode("\n", [
    file_get_contents(__DIR__ . '/../app/Repositories/AdminProductRepository.php'),
    file_get_contents(__DIR__ . '/../admin/api/index.php'),
    file_get_contents(__DIR__ . '/../views/admin/products.php'),
    file_get_contents(__DIR__ . '/../views/admin/product-detail.php'),
    file_get_contents(__DIR__ . '/../views/admin/product-form.php'),
]);
assertFalse(
    preg_match('/DELETE\s+FROM\s+(products|product_uoms|product_images)/i', $managedSources) === 1,
    'Product management must not delete product, UOM, or image rows.'
);
assertFalse(
    str_contains($managedSources, 'product-delete'),
    'Product management must not expose a delete API.'
);
assertTrue(
    str_contains($managedSources, "'product-detail' => 'products.view'"),
    'Product detail API must require products.view.'
);
assertTrue(
    str_contains($managedSources, "'product-save' => 'products.manage'"),
    'Product save API must require products.manage.'
);

$data = $service->detail('TEST_PRODUCT_1');
$capabilities = ['products_manage' => true];
$appBase = '/Dacsan';
ob_start();
require __DIR__ . '/../views/admin/product-detail.php';
$detailHtml = (string) ob_get_clean();
assertTrue(str_contains($detailHtml, 'TEST_PRODUCT_1'), 'Product detail view should render the product.');
assertTrue(str_contains($detailHtml, 'Sửa sản phẩm'), 'Managers should see the product edit action.');

$data = $service->form('TEST_PRODUCT_1');
ob_start();
require __DIR__ . '/../views/admin/product-form.php';
$formHtml = (string) ob_get_clean();
assertTrue(str_contains($formHtml, 'data-product-form'), 'Product form should render operational markup.');
assertFalse(str_contains($formHtml, 'product-delete'), 'Product form must not expose deletion.');

echo "AdminProductServiceTest: PASS\n";
