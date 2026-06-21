<?php

declare(strict_types=1);

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\AdminSettingsRepository;
use DacSanNhaDan\Services\AdminSettingsService;

require __DIR__ . '/TestSupport.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE settings (
        setting_key TEXT PRIMARY KEY, setting_value TEXT, note TEXT,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE shipping_zones (
        zone_id TEXT PRIMARY KEY, zone_name TEXT NOT NULL, fee_vnd INTEGER NOT NULL DEFAULT 0,
        is_default INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    INSERT INTO settings VALUES ("store_name", "Shop cũ", "Tên", CURRENT_TIMESTAMP);
    INSERT INTO settings VALUES ("default_shipping_zone_id", "ZONE_A", "Mặc định", CURRENT_TIMESTAMP);
    INSERT INTO shipping_zones (zone_id, zone_name, fee_vnd, is_default, is_active)
      VALUES ("ZONE_A", "Vùng A", 25000, 1, 1);
    INSERT INTO shipping_zones (zone_id, zone_name, fee_vnd, is_default, is_active)
      VALUES ("ZONE_B", "Vùng B", 40000, 0, 1)'
);

$service = new AdminSettingsService($pdo, new AdminSettingsRepository($pdo));
$page = $service->updateSettings(['settings' => [
    'store_name' => 'Đặc Sản Test',
    'bank_name' => 'Ngân hàng Test',
    'bank_account_number' => '123456',
    'bank_account_holder' => 'CHU TAI KHOAN',
    'bank_transfer_content' => 'THANH TOAN {order_id}',
    'bank_qr_image_path' => 'products_image/payment-qr_abcdef123456.png',
    'free_ship_threshold' => '350000',
    'default_shipping_zone_id' => 'ZONE_A',
]]);
assertSameValue('Ngân hàng Test', $page['setting_values']['bank_name'], 'Known payment setting must be upserted.');
$service->updateSettings(['settings' => ['bank_name' => 'Ngân hàng Test']]);
assertSameValue(1, (int) $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key='bank_name'")->fetchColumn(), 'Upsert must be idempotent when value is unchanged.');
assertThrows(fn () => $service->updateSettings(['settings' => ['arbitrary_key' => 'x']]), AppException::class, 422);
assertThrows(fn () => $service->updateSettings(['settings' => ['bank_qr_image_path' => '../bad.png']]), AppException::class, 422);

$service->saveZone([
    'zone_id' => 'ZONE_C',
    'zone_name' => 'Vùng C',
    'fee_vnd' => 60000,
    'is_active' => 1,
    'is_default' => 0,
]);
assertSameValue(60000, (int) $pdo->query("SELECT fee_vnd FROM shipping_zones WHERE zone_id='ZONE_C'")->fetchColumn(), 'Zone must be created.');
$service->saveZone([
    'zone_id' => 'ZONE_C',
    'zone_name' => 'Vùng C cập nhật',
    'fee_vnd' => 65000,
    'is_active' => 1,
    'is_default' => 0,
]);
assertSameValue(65000, (int) $pdo->query("SELECT fee_vnd FROM shipping_zones WHERE zone_id='ZONE_C'")->fetchColumn(), 'Zone fee must be updated.');

$service->setZoneDefault('ZONE_B');
assertSameValue(1, (int) $pdo->query('SELECT COUNT(*) FROM shipping_zones WHERE is_default=1')->fetchColumn(), 'Only one zone may be default.');
assertSameValue('ZONE_B', (string) $pdo->query('SELECT zone_id FROM shipping_zones WHERE is_default=1')->fetchColumn(), 'Requested zone must become default.');

$service->setZoneActive('ZONE_B', false);
assertSameValue(0, (int) $pdo->query("SELECT is_active FROM shipping_zones WHERE zone_id='ZONE_B'")->fetchColumn(), 'Default zone may be deactivated when replacement exists.');
assertSameValue(1, (int) $pdo->query('SELECT COUNT(*) FROM shipping_zones WHERE is_default=1 AND is_active=1')->fetchColumn(), 'Deactivation must move default to an active zone.');

$pdo->exec("UPDATE shipping_zones SET is_active=0, is_default=0; UPDATE shipping_zones SET is_active=1, is_default=1 WHERE zone_id='ZONE_A'");
assertThrows(fn () => $service->setZoneActive('ZONE_A', false), AppException::class, 409);
assertSameValue(1, (int) $pdo->query("SELECT is_active FROM shipping_zones WHERE zone_id='ZONE_A'")->fetchColumn(), 'Failed deactivation must roll back.');

$sources = implode("\n", [
    file_get_contents(__DIR__ . '/../admin/api/index.php'),
    file_get_contents(__DIR__ . '/../views/admin/settings.php'),
    file_get_contents(__DIR__ . '/../public/assets/js/admin.js'),
]);
assertTrue(str_contains($sources, "'settings-update' => 'settings.manage'"), 'Settings update must require settings.manage.');
assertTrue(str_contains($sources, "'shipping-zone-save' => 'settings.manage'"), 'Zone save must require settings.manage.');
assertTrue(str_contains($sources, 'data-settings-form'), 'Settings page must expose an allowlisted settings form.');
assertFalse(str_contains($sources, "'setting-update' =>"), 'Legacy arbitrary single-key update endpoint must be removed.');

echo "AdminSettingsServiceTest: PASS\n";
