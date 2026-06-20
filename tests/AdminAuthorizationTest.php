<?php

declare(strict_types=1);

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\AdminUserRepository;
use DacSanNhaDan\Services\AdminAuthService;
use DacSanNhaDan\Services\AdminAuthorizationService;

require_once __DIR__ . '/TestSupport.php';

assertTrue(
    class_exists(AdminAuthorizationService::class),
    'AdminAuthorizationService should exist.'
);

$authorization = new AdminAuthorizationService();

assertTrue(
    $authorization->allows('owner', 'admin_users.manage'),
    'Owner should be allowed to manage admin users.'
);

assertTrue(
    $authorization->allows('admin', 'products.manage'),
    'Admin should be allowed to manage products.'
);

assertTrue(
    $authorization->allows('staff', 'orders.transition'),
    'Staff should be allowed to transition orders.'
);

assertFalse(
    $authorization->allows('staff', 'inventory.manage'),
    'Staff should not be allowed to manage inventory.'
);

$exception = assertThrows(
    static fn () => $authorization->require('staff', 'settings.manage'),
    AppException::class,
    403
);

assertSameValue(
    'Bạn không có quyền thực hiện thao tác này.',
    $exception->getMessage(),
    'Denied authorization should use the expected message.'
);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE admin_users (
        admin_id INTEGER PRIMARY KEY,
        username TEXT NOT NULL,
        password_hash TEXT NOT NULL,
        full_name TEXT,
        role TEXT NOT NULL,
        is_active INTEGER NOT NULL,
        created_at TEXT,
        updated_at TEXT
    )'
);
$statement = $pdo->prepare(
    'INSERT INTO admin_users (
        admin_id, username, password_hash, full_name, role, is_active, created_at, updated_at
    ) VALUES (
        :admin_id, :username, :password_hash, :full_name, :role, :is_active, :created_at, :updated_at
    )'
);
$statement->execute([
    'admin_id' => 1,
    'username' => 'staff-user',
    'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
    'full_name' => 'Staff User',
    'role' => 'staff',
    'is_active' => 1,
    'created_at' => '2026-06-20 00:00:00',
    'updated_at' => '2026-06-20 00:00:00',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['admin_user'] = ['admin_id' => 1];

assertTrue(
    method_exists(AdminAuthService::class, 'role'),
    'AdminAuthService should expose the current role.'
);
assertTrue(
    method_exists(AdminAuthService::class, 'can'),
    'AdminAuthService should expose permission checks.'
);

$auth = new AdminAuthService(
    new AdminUserRepository($pdo),
    $authorization
);

assertSameValue('staff', $auth->role(), 'AdminAuthService should return the active user role.');
assertTrue(
    $auth->can('orders.transition'),
    'AdminAuthService should delegate allowed permission checks to the policy.'
);
assertFalse(
    $auth->can('inventory.manage'),
    'AdminAuthService should delegate denied permission checks to the policy.'
);

echo 'Admin authorization tests passed.' . PHP_EOL;
