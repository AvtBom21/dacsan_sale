<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

$titles = [
    'dashboard' => 'Dashboard',
    'orders' => 'Đơn hàng',
    'products' => 'Sản phẩm',
    'inventory' => 'Kho',
    'purchase-plans' => 'Purchase Plan',
    'settings' => 'Settings',
];
$viewFile = dirname(__DIR__) . '/admin/' . $page . '.php';
$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$scriptDir = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');
$appBase = preg_replace('#/admin$#', '', $scriptDir) ?: '';
$assetBase = $appBase . '/assets';
$storefrontUrl = $appBase === '' ? '/' : $appBase . '/';

?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Formatter::h($titles[$page] ?? 'Admin') ?> - Đặc Sản Nhà Dân</title>
    <link rel="stylesheet" href="<?= Formatter::h($assetBase) ?>/css/admin.css">
</head>
<body class="admin-shell" data-csrf="<?= Formatter::h($csrfToken) ?>">
    <aside class="sidebar">
        <a class="brand" href="./">Đặc Sản Nhà Dân</a>
        <nav>
            <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="./?page=dashboard">Dashboard</a>
            <a class="<?= $page === 'orders' ? 'active' : '' ?>" href="./?page=orders">Đơn hàng</a>
            <a class="<?= $page === 'products' ? 'active' : '' ?>" href="./?page=products">Sản phẩm</a>
            <a class="<?= $page === 'inventory' ? 'active' : '' ?>" href="./?page=inventory">Kho</a>
            <a class="<?= $page === 'purchase-plans' ? 'active' : '' ?>" href="./?page=purchase-plans">PO</a>
            <a class="<?= $page === 'settings' ? 'active' : '' ?>" href="./?page=settings">Settings</a>
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
        <?php require is_file($viewFile) ? $viewFile : dirname(__DIR__) . '/admin/dashboard.php'; ?>
    </main>
    <script src="<?= Formatter::h($assetBase) ?>/js/admin.js"></script>
</body>
</html>

