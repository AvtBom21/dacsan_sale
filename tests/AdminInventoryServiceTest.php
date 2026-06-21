<?php

declare(strict_types=1);

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\InventoryRepository;
use DacSanNhaDan\Services\AdminInventoryService;

require __DIR__ . '/TestSupport.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE products (
        product_id TEXT PRIMARY KEY, product_name TEXT NOT NULL,
        default_source TEXT NOT NULL, base_uom_label TEXT NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE product_uoms (
        uom_id TEXT PRIMARY KEY, product_id TEXT NOT NULL, uom_label TEXT NOT NULL,
        conversion_to_base NUMERIC NOT NULL, cost_price_vnd INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1, is_purchasable INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE inventory_lots (
        lot_id TEXT PRIMARY KEY, product_id TEXT NOT NULL, source_location TEXT NOT NULL,
        qty_base_on_hand NUMERIC NOT NULL, qty_base_reserved NUMERIC NOT NULL DEFAULT 0,
        received_date TEXT NOT NULL, expiry_date TEXT, supplier_name TEXT,
        cost_per_base_unit_vnd INTEGER NOT NULL DEFAULT 0, received_uom_id TEXT,
        received_qty_uom NUMERIC DEFAULT 0, conversion_to_base_snapshot NUMERIC DEFAULT 1,
        note TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE inventory_movements (
        movement_id TEXT PRIMARY KEY, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        movement_type TEXT NOT NULL, ref_type TEXT NOT NULL, ref_id TEXT, lot_id TEXT,
        product_id TEXT NOT NULL, source_location TEXT NOT NULL, uom_id TEXT,
        qty_uom NUMERIC DEFAULT 0, conversion_to_base_snapshot NUMERIC NOT NULL DEFAULT 1,
        qty_base NUMERIC NOT NULL, cost_per_base_unit_vnd INTEGER NOT NULL DEFAULT 0, note TEXT
    )'
);
$pdo->exec(
    "INSERT INTO products VALUES ('TEST_PRODUCT_INV', 'Sản phẩm kho test', 'Gia Lai', 'Gói 500g', 1);
     INSERT INTO product_uoms VALUES ('TEST_PRODUCT_INV_BASE', 'TEST_PRODUCT_INV', 'Gói 500g', 1, 50000, 1, 1);
     INSERT INTO product_uoms VALUES ('TEST_PRODUCT_INV_BOX', 'TEST_PRODUCT_INV', 'Thùng 10 gói', 10, 450000, 1, 1);
     INSERT INTO product_uoms VALUES ('TEST_PRODUCT_INV_DISABLED', 'TEST_PRODUCT_INV', 'Ngừng nhập', 1, 0, 1, 0)"
);

$repository = new InventoryRepository($pdo);
$service = new AdminInventoryService($pdo, $repository);

$received = $service->receiveManual([
    'product_id' => 'TEST_PRODUCT_INV',
    'uom_id' => 'TEST_PRODUCT_INV_BOX',
    'qty_uom' => 2,
    'source_location' => 'Gia Lai',
    'received_date' => '2026-06-21',
    'expiry_date' => '2026-12-21',
    'supplier_name' => 'NCC Test',
    'cost_per_uom_vnd' => 450000,
    'note' => 'Nhập thử nghiệm',
]);
$lotId = (string) $received['lot_id'];
assertSameValue(20.0, (float) $received['qty_base_on_hand'], 'Manual receipt must convert to base quantity.');
assertSameValue(2.0, (float) $received['received_qty_uom'], 'Manual receipt must retain entered UOM quantity.');
assertSameValue(10.0, (float) $received['conversion_to_base_snapshot'], 'Manual receipt must snapshot conversion.');
assertSameValue(45000, (int) $received['cost_per_base_unit_vnd'], 'Cost per base must be derived from cost per UOM.');
assertSameValue(
    1,
    (int) $pdo->query("SELECT COUNT(*) FROM inventory_movements WHERE lot_id = " . $pdo->quote($lotId) . " AND movement_type = 'IN' AND ref_type = 'MANUAL'")->fetchColumn(),
    'Manual receipt must create one IN/MANUAL movement.'
);

$increased = $service->adjustLot($lotId, 2.5, 'Kiểm kê tăng');
assertSameValue(22.5, (float) $increased['qty_base_on_hand'], 'Positive adjustment must increase on hand.');
$decreased = $service->adjustLot($lotId, -1.5, 'Hàng bị hỏng');
assertSameValue(21.0, (float) $decreased['qty_base_on_hand'], 'Negative adjustment must decrease on hand.');
assertSameValue(
    -1.5,
    (float) $pdo->query("SELECT qty_base FROM inventory_movements WHERE lot_id = " . $pdo->quote($lotId) . " AND movement_type = 'ADJUST' ORDER BY created_at DESC, rowid DESC LIMIT 1")->fetchColumn(),
    'Adjustment movement must retain signed quantity.'
);

$pdo->exec("UPDATE inventory_lots SET qty_base_reserved = 20 WHERE lot_id = " . $pdo->quote($lotId));
$movementCount = (int) $pdo->query("SELECT COUNT(*) FROM inventory_movements WHERE lot_id = " . $pdo->quote($lotId))->fetchColumn();
assertThrows(fn () => $service->adjustLot($lotId, -2, 'Không đủ tồn'), AppException::class, 409);
assertSameValue(21.0, (float) $repository->findLot($lotId)['qty_base_on_hand'], 'Rejected adjustment must leave lot unchanged.');
assertSameValue(
    $movementCount,
    (int) $pdo->query("SELECT COUNT(*) FROM inventory_movements WHERE lot_id = " . $pdo->quote($lotId))->fetchColumn(),
    'Rejected adjustment must not create movement.'
);

$pdo->exec(
    "CREATE TRIGGER fail_adjust_movement
     BEFORE INSERT ON inventory_movements
     WHEN NEW.movement_type = 'ADJUST'
     BEGIN SELECT RAISE(ABORT, 'forced movement failure'); END"
);
$beforeRollback = (float) $repository->findLot($lotId)['qty_base_on_hand'];
assertThrows(fn () => $service->adjustLot($lotId, 1, 'Buộc rollback'), PDOException::class);
assertSameValue($beforeRollback, (float) $repository->findLot($lotId)['qty_base_on_hand'], 'Movement failure must roll back lot update.');
$pdo->exec('DROP TRIGGER fail_adjust_movement');

assertThrows(fn () => $service->receiveManual([
    'product_id' => 'TEST_PRODUCT_INV',
    'uom_id' => 'TEST_PRODUCT_INV_DISABLED',
    'qty_uom' => 1,
    'source_location' => 'Gia Lai',
    'received_date' => '2026-06-21',
]), AppException::class, 422);
assertThrows(fn () => $service->receiveManual([
    'product_id' => 'OTHER_PRODUCT',
    'uom_id' => 'TEST_PRODUCT_INV_BASE',
    'qty_uom' => 1,
    'source_location' => 'Gia Lai',
    'received_date' => '2026-06-21',
]), AppException::class, 422);
assertThrows(fn () => $service->receiveManual([
    'product_id' => 'TEST_PRODUCT_INV',
    'uom_id' => 'TEST_PRODUCT_INV_BASE',
    'qty_uom' => 1,
    'source_location' => 'Gia Lai',
    'received_date' => '2026-06-21',
    'expiry_date' => '2026-06-20',
]), AppException::class, 422);
assertThrows(fn () => $service->adjustLot($lotId, 0.0004, 'Quá nhỏ'), AppException::class, 422);
assertThrows(fn () => $service->adjustLot($lotId, 1, 'x'), AppException::class, 422);

$sources = implode("\n", [
    file_get_contents(__DIR__ . '/../admin/api/index.php'),
    file_get_contents(__DIR__ . '/../admin/index.php'),
    file_get_contents(__DIR__ . '/../views/admin/inventory.php'),
    file_get_contents(__DIR__ . '/../views/admin/inventory-lot.php'),
    file_get_contents(__DIR__ . '/../public/assets/js/admin.js'),
]);
assertTrue(str_contains($sources, "'inventory-receive' => 'inventory.manage'"), 'Receipt API must require inventory.manage.');
assertTrue(str_contains($sources, "'inventory-adjust' => 'inventory.manage'"), 'Adjustment API must require inventory.manage.');
assertTrue(str_contains($sources, "'inventory-lot-detail' => 'inventory.view'"), 'Lot detail API must require inventory.view.');
assertTrue(str_contains($sources, 'data-inventory-receive'), 'Inventory page must provide a manual receipt form.');
assertTrue(str_contains($sources, 'data-inventory-adjust'), 'Lot detail must provide an adjustment form.');

echo "AdminInventoryServiceTest: PASS\n";
