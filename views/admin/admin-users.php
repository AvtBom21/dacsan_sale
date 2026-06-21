<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

$roles = is_array($data['roles'] ?? null) ? $data['roles'] : [];
$accounts = is_array($data['users'] ?? null) ? $data['users'] : [];
?>
<section class="panel">
    <div class="section-heading"><div><span class="eyebrow">Owner only</span><h2>Tạo tài khoản quản trị</h2></div></div>
    <form class="form-grid" data-admin-user-create>
        <label>Tên đăng nhập<input name="username" minlength="3" maxlength="80" required></label>
        <label>Họ tên<input name="full_name" maxlength="160"></label>
        <label>Vai trò
            <select name="role"><?php foreach ($roles as $role): ?><option value="<?= Formatter::h($role) ?>"><?= Formatter::h($role) ?></option><?php endforeach; ?></select>
        </label>
        <label>Mật khẩu<input name="password" type="password" minlength="10" maxlength="200" required></label>
        <div class="full page-actions"><button class="button" type="submit">Tạo tài khoản</button><p class="field-error" data-user-error hidden></p></div>
    </form>
</section>

<section class="user-list">
    <?php foreach ($accounts as $account): ?>
        <article class="panel">
            <form class="form-grid" data-admin-user-update data-admin-id="<?= (int) $account['admin_id'] ?>">
                <div class="full section-heading">
                    <div><span class="eyebrow">@<?= Formatter::h((string) $account['username']) ?></span><h2><?= Formatter::h((string) ($account['full_name'] ?: $account['username'])) ?></h2></div>
                    <span class="status-pill" data-status="<?= ((int) $account['is_active'] === 1) ? 'ready' : 'cancelled' ?>"><?= ((int) $account['is_active'] === 1) ? 'Hoạt động' : 'Đã khóa' ?></span>
                </div>
                <label>Họ tên<input name="full_name" maxlength="160" value="<?= Formatter::h((string) $account['full_name']) ?>"></label>
                <label>Vai trò
                    <select name="role"><?php foreach ($roles as $role): ?><option value="<?= Formatter::h($role) ?>" <?= ((string) $account['role'] === $role) ? 'selected' : '' ?>><?= Formatter::h($role) ?></option><?php endforeach; ?></select>
                </label>
                <label><input name="is_active" type="checkbox" <?= ((int) $account['is_active'] === 1) ? 'checked' : '' ?>> Hoạt động</label>
                <div class="page-actions"><button class="button-secondary" type="submit">Lưu hồ sơ</button></div>
            </form>
            <form class="form-grid password-reset" data-admin-user-password data-admin-id="<?= (int) $account['admin_id'] ?>">
                <label>Mật khẩu mới<input name="password" type="password" minlength="10" maxlength="200" required></label>
                <div class="page-actions"><button class="button-secondary" type="submit">Đặt lại mật khẩu</button><p class="field-error" data-user-error hidden></p></div>
            </form>
        </article>
    <?php endforeach; ?>
</section>
