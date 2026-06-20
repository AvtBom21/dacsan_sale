<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

$titles = [
    'dashboard' => 'Dashboard',
    'orders' => 'Đơn hàng',
    'order-detail' => 'Chi tiết đơn hàng',
    'products' => 'Sản phẩm',
    'product-detail' => 'Chi tiết sản phẩm',
    'product-form' => empty($data['product']) ? 'Thêm sản phẩm' : 'Sửa sản phẩm',
    'inventory' => 'Kho',
    'inventory-lot' => 'Chi tiết lot kho',
    'purchase-plans' => 'Purchase Plan',
    'purchase-plan-detail' => 'Chi tiết Purchase Plan',
    'settings' => 'Cài đặt',
    'admin-users' => 'Tài khoản quản trị',
];
$parentPages = [
    'order-detail' => 'orders',
    'product-detail' => 'products',
    'product-form' => 'products',
    'inventory-lot' => 'inventory',
    'purchase-plan-detail' => 'purchase-plans',
];
$activePage = $parentPages[$page] ?? $page;
$viewFile = dirname(__DIR__) . '/admin/' . $page . '.php';
$capabilities = is_array($capabilities ?? null) ? $capabilities : [];
$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$scriptDir = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');
$appBase = preg_replace('#/admin$#', '', $scriptDir) ?: '';
$assetBase = $appBase . '/assets';
$storefrontUrl = $appBase === '' ? '/' : $appBase . '/';
$apiBase = $appBase . '/admin/api/index.php';

?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Formatter::h($titles[$page] ?? 'Admin') ?> - Đặc Sản Nhà Dân</title>
    <link rel="stylesheet" href="<?= Formatter::h($assetBase) ?>/css/admin.css">
</head>
<body
    class="admin-shell"
    data-csrf="<?= Formatter::h($csrfToken) ?>"
    data-api-base="<?= Formatter::h($apiBase) ?>"
>
    <aside class="sidebar">
        <a class="brand" href="./">Đặc Sản Nhà Dân</a>
        <nav>
            <?php if (($capabilities['dashboard'] ?? false) === true): ?>
                <a href="./?page=dashboard" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <?php endif; ?>
            <?php if (($capabilities['orders'] ?? false) === true): ?>
                <a href="./?page=orders" class="<?= $activePage === 'orders' ? 'active' : '' ?>">Đơn hàng</a>
            <?php endif; ?>
            <?php if (($capabilities['products'] ?? false) === true): ?>
                <a href="./?page=products" class="<?= $activePage === 'products' ? 'active' : '' ?>">Sản phẩm</a>
            <?php endif; ?>
            <?php if (($capabilities['inventory'] ?? false) === true): ?>
                <a href="./?page=inventory" class="<?= $activePage === 'inventory' ? 'active' : '' ?>">Kho</a>
            <?php endif; ?>
            <?php if (($capabilities['purchase_plans'] ?? false) === true): ?>
                <a href="./?page=purchase-plans" class="<?= $activePage === 'purchase-plans' ? 'active' : '' ?>">PO</a>
            <?php endif; ?>
            <?php if (($capabilities['settings'] ?? false) === true): ?>
                <a href="./?page=settings" class="<?= $activePage === 'settings' ? 'active' : '' ?>">Cài đặt</a>
            <?php endif; ?>
            <?php if (($capabilities['admin_users'] ?? false) === true): ?>
                <a href="./?page=admin-users" class="<?= $activePage === 'admin-users' ? 'active' : '' ?>">Tài khoản</a>
            <?php endif; ?>
        </nav>
        <form method="post" action="./">
            <input type="hidden" name="admin_action" value="logout">
            <input type="hidden" name="csrf_token" value="<?= Formatter::h($csrfToken) ?>">
            <button type="submit" class="ghost">Đăng xuất</button>
        </form>
    </aside>
    <main class="content">
        <header class="topbar">
            <div>
                <h1><?= Formatter::h($titles[$page] ?? 'Admin') ?></h1>
                <span><?= Formatter::h((string) ($user['full_name'] ?: $user['username'])) ?> · <?= Formatter::h((string) $user['role']) ?></span>
            </div>
            <a href="<?= Formatter::h($storefrontUrl) ?>" target="_blank" rel="noreferrer">Mở storefront</a>
        </header>
        <?php if (is_file($viewFile)): ?>
            <?php require $viewFile; ?>
        <?php else: ?>
            <section class="panel" data-admin-placeholder="<?= Formatter::h($page) ?>">
                <h2><?= Formatter::h($titles[$page] ?? 'Trang quản trị') ?></h2>
                <p>Chức năng chi tiết đang được hoàn thiện. Dữ liệu route đã được kiểm tra an toàn.</p>
            </section>
        <?php endif; ?>
    </main>
    <div class="toast-host" aria-live="polite" aria-atomic="true"></div>
    <script src="<?= Formatter::h($assetBase) ?>/js/admin.js"></script>
</body>
</html>
