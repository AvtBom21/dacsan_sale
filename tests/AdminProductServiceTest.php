<?php

declare(strict_types=1);

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\AdminProductRepository;
use DacSanNhaDan\Services\AdminProductService;
use DacSanNhaDan\Services\UploadService;

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

$tooSmallConversion = productPayload();
$tooSmallConversion['uoms'][0]['conversion_to_base'] = 0.0004;
assertThrows(fn () => $service->save($tooSmallConversion), AppException::class, 422);

$tooLargeConversion = productPayload();
$tooLargeConversion['uoms'][0]['conversion_to_base'] = 1000000000;
assertThrows(fn () => $service->save($tooLargeConversion), AppException::class, 422);

$nonFiniteConversion = productPayload();
$nonFiniteConversion['uoms'][0]['conversion_to_base'] = INF;
assertThrows(fn () => $service->save($nonFiniteConversion), AppException::class, 422);

$tooLargeInteger = productPayload();
$tooLargeInteger['uoms'][0]['unit_price_vnd'] = 2147483648;
assertThrows(fn () => $service->save($tooLargeInteger), AppException::class, 422);

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

$existingImageId = (int) $pdo->query(
    "SELECT image_id FROM product_images WHERE product_id = 'TEST_PRODUCT_1' ORDER BY image_id LIMIT 1"
)->fetchColumn();
$duplicateImages = productPayload(['original_product_id' => 'TEST_PRODUCT_1']);
$duplicateImages['images'] = [
    [
        'image_id' => $existingImageId,
        'image_path' => 'products_image/duplicate-1.jpg',
        'source_url' => '',
        'image_alt' => 'Trùng 1',
        'is_base' => 1,
        'is_active' => 1,
        'sort_order' => 1,
    ],
    [
        'image_id' => $existingImageId,
        'image_path' => 'products_image/duplicate-2.jpg',
        'source_url' => '',
        'image_alt' => 'Trùng 2',
        'is_base' => 0,
        'is_active' => 1,
        'sort_order' => 2,
    ],
];
assertThrows(fn () => $service->save($duplicateImages), AppException::class, 422);

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

$uploadRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dsnd-upload-' . bin2hex(random_bytes(6));
$fixtureDir = $uploadRoot . DIRECTORY_SEPARATOR . 'fixtures';
mkdir($fixtureDir, 0775, true);

$fixtureBytes = [
    'jpeg' => base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAEf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=', true),
    'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
    'webp' => base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAUAmJaQAA3AA/v89WAAAAA==', true),
];

function uploadFixture(
    string $fixtureDir,
    string $name,
    string $bytes,
    ?int $reportedSize = null
): array {
    $path = $fixtureDir . DIRECTORY_SEPARATOR . $name;
    file_put_contents($path, $bytes);

    return [
        'name' => $name,
        'type' => 'application/octet-stream',
        'tmp_name' => $path,
        'error' => UPLOAD_ERR_OK,
        'size' => $reportedSize ?? filesize($path),
    ];
}

function removeDirectoryTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($child) && !is_link($child)) {
            removeDirectoryTree($child);
        } else {
            unlink($child);
        }
    }
    rmdir($path);
}

try {
    $testMover = static function (string $source, string $destination): bool {
        return rename($source, $destination);
    };
    $uploads = new UploadService($uploadRoot, $testMover);

    foreach ($fixtureBytes as $type => $bytes) {
        assertTrue(is_string($bytes) && $bytes !== '', 'Image fixture must decode.');
        $stored = $uploads->storeImage(
            uploadFixture($fixtureDir, 'tiny-' . $type . '.bin', $bytes),
            '../Sản Phẩm TEST.php'
        );
        assertTrue(
            preg_match('#^products_image/[a-z0-9-]+_[a-f0-9]{12}\.(jpg|png|webp)$#', $stored['path']) === 1,
            'Generated upload path should use a safe prefix and random filename.'
        );
        assertTrue(is_file($stored['absolute_path']), 'Accepted image should be stored.');
        $uploadDirectory = realpath($uploadRoot . DIRECTORY_SEPARATOR . 'products_image');
        $storedRealPath = realpath($stored['absolute_path']);
        assertTrue(
            is_string($uploadDirectory)
                && is_string($storedRealPath)
                && str_starts_with(
                    strtolower($storedRealPath),
                    strtolower(rtrim($uploadDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
                ),
            'Stored image must remain inside products_image.'
        );
    }

    $disguised = uploadFixture(
        $fixtureDir,
        'shell.jpg',
        "<?php echo 'not an image';"
    );
    assertThrows(
        fn () => $uploads->storeImage($disguised, 'product'),
        AppException::class,
        422
    );

    $largePath = $fixtureDir . DIRECTORY_SEPARATOR . 'large.png';
    $largeHandle = fopen($largePath, 'wb');
    fseek($largeHandle, (5 * 1024 * 1024));
    fwrite($largeHandle, "\0");
    fclose($largeHandle);
    assertThrows(
        fn () => $uploads->storeImage([
            'name' => 'large.png',
            'type' => 'image/png',
            'tmp_name' => $largePath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($largePath),
        ], 'product'),
        AppException::class,
        422
    );

    $failingMover = new UploadService(
        $uploadRoot,
        static fn (string $source, string $destination): bool => false
    );
    assertThrows(
        fn () => $failingMover->storeImage(
            uploadFixture($fixtureDir, 'move-fail.png', $fixtureBytes['png']),
            'product'
        ),
        AppException::class,
        500
    );

    $cleanupCandidate = uploadFixture(
        $fixtureDir,
        'cleanup.webp',
        $fixtureBytes['webp']
    );
    $storedBeforeFailure = null;
    assertThrows(
        function () use ($uploads, $cleanupCandidate, &$storedBeforeFailure, $pdo): void {
            $uploads->storeImageAndPersist(
                $cleanupCandidate,
                'product',
                static function (array $stored) use (&$storedBeforeFailure, $pdo): void {
                    $storedBeforeFailure = $stored;
                    $pdo->exec('INSERT INTO missing_upload_metadata (image_path) VALUES ("fail")');
                }
            );
        },
        PDOException::class
    );
    assertTrue(is_array($storedBeforeFailure), 'Persistence callback should receive stored metadata.');
    assertFalse(
        is_file((string) $storedBeforeFailure['absolute_path']),
        'Metadata failure must remove the exact newly generated file.'
    );
} finally {
    removeDirectoryTree($uploadRoot);
}

assertTrue(
    UploadService::isSafeLocalImagePath('products_image/product_abcdef123456.jpg'),
    'Generated local image paths should be accepted for display.'
);
assertFalse(
    UploadService::isSafeLocalImagePath('../products_image/product_abcdef123456.jpg'),
    'Traversal image paths must not be accepted for display.'
);
assertFalse(
    UploadService::isSafeLocalImagePath('https://example.com/qr.png'),
    'Remote QR paths must not be accepted for display.'
);
assertFalse(
    UploadService::isSafeLocalImagePath('products_image/shell.php.jpg'),
    'Double-extension PHP image paths must be rejected.'
);
assertFalse(
    UploadService::isSafeLocalImagePath('products_image/shell.phtml.png'),
    'Double-extension PHTML image paths must be rejected.'
);
assertFalse(
    UploadService::isSafeLocalImagePath('products_image/archive.backup.webp'),
    'Any extra dot in an image basename must be rejected.'
);

$uploadSources = implode("\n", [
    file_get_contents(__DIR__ . '/../admin/api/index.php'),
    file_get_contents(__DIR__ . '/../views/admin/product-form.php'),
    file_get_contents(__DIR__ . '/../views/admin/settings.php'),
    file_get_contents(__DIR__ . '/../public/assets/js/admin.js'),
]);
assertTrue(
    str_contains($uploadSources, "'product-image-upload' => 'products.manage'"),
    'Product upload endpoint must require products.manage.'
);
assertTrue(
    str_contains($uploadSources, "'payment-qr-upload' => 'settings.manage'"),
    'Payment QR endpoint must require settings.manage.'
);
assertTrue(
    str_contains($uploadSources, "admin_api_post_id('product_id', 40)"),
    'Product upload must validate the database product ID length.'
);
assertTrue(
    str_contains($uploadSources, 'new FormData'),
    'Admin uploads must use FormData.'
);
assertTrue(
    str_contains($uploadSources, '!(requestOptions.body instanceof FormData)'),
    'Multipart requests must bypass the JSON Content-Type header.'
);
assertTrue(
    str_contains($uploadSources, 'data-product-image-upload'),
    'Product form should expose direct image upload controls.'
);
assertTrue(
    str_contains($uploadSources, 'data-payment-qr-upload'),
    'Settings should expose payment QR upload controls.'
);
assertTrue(
    str_contains(
        file_get_contents(__DIR__ . '/../views/admin/product-detail.php'),
        'UploadService::isSafeLocalImagePath'
    ),
    'Product detail must only render safe local products_image paths.'
);
$uploadServiceSource = file_get_contents(__DIR__ . '/../app/Services/UploadService.php');
$adminApiSource = file_get_contents(__DIR__ . '/../admin/api/index.php');
assertTrue(preg_match('//u', $uploadServiceSource) === 1, 'UploadService source must be valid UTF-8.');
assertTrue(preg_match('//u', $adminApiSource) === 1, 'Admin API source must be valid UTF-8.');
assertTrue(
    str_contains($uploadServiceSource, 'Tải ảnh thất bại. Mã lỗi:'),
    'UploadService must contain correctly encoded Vietnamese messages.'
);
assertTrue(
    str_contains($uploadServiceSource, 'Chỉ chấp nhận ảnh JPEG, PNG hoặc WebP.'),
    'MIME validation message must be correctly encoded.'
);
assertTrue(
    str_contains($adminApiSource, 'Ảnh QR chuyển khoản trong products_image'),
    'QR setting note must be correctly encoded.'
);
assertFalse(
    str_contains($adminApiSource, 'unlink(')
        || str_contains($adminApiSource, 'removeStoredFile')
        || str_contains($adminApiSource, 'oldQr'),
    'Payment QR upload must not delete an old configured file.'
);

echo "AdminProductServiceTest: PASS\n";
