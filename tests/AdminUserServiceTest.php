<?php

declare(strict_types=1);

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\AdminUserRepository;
use DacSanNhaDan\Services\AdminAuthorizationService;
use DacSanNhaDan\Services\AdminUserService;

require __DIR__ . '/TestSupport.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE admin_users (
        admin_id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL, full_name TEXT, role TEXT NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
$pdo->prepare(
    "INSERT INTO admin_users (username,password_hash,full_name,role,is_active)
     VALUES ('owner1',?,'Owner 1','owner',1)"
)->execute([password_hash('owner-password', PASSWORD_DEFAULT)]);

$repository = new AdminUserRepository($pdo);
$service = new AdminUserService($pdo, $repository);
$staff = $service->create([
    'username' => 'test.staff',
    'password' => 'strong-password',
    'full_name' => 'Test Staff',
    'role' => 'staff',
]);
$staffId = (int) $staff['admin_id'];
$hash = (string) $pdo->query("SELECT password_hash FROM admin_users WHERE admin_id=$staffId")->fetchColumn();
assertTrue(password_verify('strong-password', $hash), 'Created password must be hashed.');

$updated = $service->update([
    'admin_id' => $staffId,
    'full_name' => 'Test Admin',
    'role' => 'admin',
    'is_active' => 1,
]);
assertSameValue('admin', $updated['role'], 'Owner must be able to change role.');

$service->resetPassword($staffId, 'new-password-123');
$hash = (string) $pdo->query("SELECT password_hash FROM admin_users WHERE admin_id=$staffId")->fetchColumn();
assertTrue(password_verify('new-password-123', $hash), 'Password reset must store a new hash.');
assertThrows(fn () => $service->resetPassword($staffId, 'short'), AppException::class, 422);
assertThrows(fn () => $service->create([
    'username' => 'test.staff', 'password' => 'another-password', 'role' => 'staff',
]), AppException::class, 409);

assertThrows(fn () => $service->update([
    'admin_id' => 1, 'full_name' => 'Owner 1', 'role' => 'admin', 'is_active' => 1,
]), AppException::class, 409);
assertThrows(fn () => $service->update([
    'admin_id' => 1, 'full_name' => 'Owner 1', 'role' => 'owner', 'is_active' => 0,
]), AppException::class, 409);
assertSameValue('owner', (string) $pdo->query('SELECT role FROM admin_users WHERE admin_id=1')->fetchColumn(), 'Last owner must remain owner.');
assertSameValue(1, (int) $pdo->query('SELECT is_active FROM admin_users WHERE admin_id=1')->fetchColumn(), 'Last owner must remain active.');

$secondOwner = $service->create([
    'username' => 'owner2', 'password' => 'second-owner-password', 'full_name' => 'Owner 2', 'role' => 'owner',
]);
$service->update([
    'admin_id' => 1, 'full_name' => 'Owner 1', 'role' => 'admin', 'is_active' => 1,
]);
assertSameValue('admin', (string) $pdo->query('SELECT role FROM admin_users WHERE admin_id=1')->fetchColumn(), 'Owner may be demoted when another active owner exists.');

$policy = new AdminAuthorizationService();
assertFalse($policy->allows('admin', 'admin_users.manage'), 'Admin role must not manage admin users.');
assertTrue($policy->allows('owner', 'admin_users.manage'), 'Owner role must manage admin users.');

$sources = implode("\n", [
    file_get_contents(__DIR__ . '/../admin/api/index.php'),
    file_get_contents(__DIR__ . '/../admin/index.php'),
    file_get_contents(__DIR__ . '/../views/admin/admin-users.php'),
]);
assertTrue(str_contains($sources, "'admin-user-create' => 'admin_users.manage'"), 'Create endpoint must be owner-only.');
assertTrue(str_contains($sources, "'admin-user-update' => 'admin_users.manage'"), 'Update endpoint must be owner-only.');
assertTrue(str_contains($sources, "'admin-user-password' => 'admin_users.manage'"), 'Password endpoint must be owner-only.');

echo "AdminUserServiceTest: PASS\n";
