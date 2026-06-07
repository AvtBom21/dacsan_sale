<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$scriptDir = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');
$appBase = preg_replace('#/admin$#', '', $scriptDir) ?: '';
$assetBase = $appBase . '/assets';

?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập quản trị - Đặc Sản Nhà Dân</title>
    <link rel="stylesheet" href="<?= Formatter::h($assetBase) ?>/css/admin.css">
</head>
<body class="admin-login">
    <main class="login-panel">
        <h1>Đặc Sản Nhà Dân</h1>
        <p>Đăng nhập quản trị</p>
        <?php if (!empty($error)): ?>
            <div class="alert"><?= Formatter::h((string) $error) ?></div>
        <?php endif; ?>
        <form method="post" action="./">
            <input type="hidden" name="admin_action" value="login">
            <input type="hidden" name="csrf_token" value="<?= Formatter::h($csrfToken) ?>">
            <label>
                Tài khoản
                <input name="username" autocomplete="username" value="admin" required>
            </label>
            <label>
                Mật khẩu
                <input name="password" type="password" autocomplete="current-password" required>
            </label>
            <button type="submit">Đăng nhập</button>
        </form>
        <p class="hint">Demo seed hiện tại: <code>admin</code> / <code>admin123</code></p>
    </main>
</body>
</html>
