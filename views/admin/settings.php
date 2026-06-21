<?php

declare(strict_types=1);

use DacSanNhaDan\Services\UploadService;
use DacSanNhaDan\Support\Formatter;

$values = is_array($data['setting_values'] ?? null) ? $data['setting_values'] : [];
$zones = is_array($data['shipping_zones'] ?? null) ? $data['shipping_zones'] : [];
$canManage = ($capabilities['settings_manage'] ?? false) === true;
$qrPath = (string) ($values['bank_qr_image_path'] ?? '');
$qrUrl = UploadService::isSafeLocalImagePath($qrPath)
    ? rtrim((string) ($appBase ?? ''), '/') . '/' . $qrPath
    : '';
?>
<form class="panel" data-settings-form>
    <div class="section-heading"><div><span class="eyebrow">Cửa hàng</span><h2>Liên hệ và thanh toán</h2></div></div>
    <div class="form-grid">
        <label>Tên cửa hàng<input name="store_name" value="<?= Formatter::h((string) ($values['store_name'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>></label>
        <label>Điện thoại<input name="store_phone" value="<?= Formatter::h((string) ($values['store_phone'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>></label>
        <label>Zalo<input name="zalo_link" value="<?= Formatter::h((string) ($values['zalo_link'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>></label>
        <label>Ngưỡng miễn phí giao<input name="free_ship_threshold" type="number" min="0" value="<?= Formatter::h((string) ($values['free_ship_threshold'] ?? '0')) ?>" <?= $canManage ? '' : 'readonly' ?>></label>
        <label>Ngân hàng<input name="bank_name" value="<?= Formatter::h((string) ($values['bank_name'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>></label>
        <label>Số tài khoản<input name="bank_account_number" value="<?= Formatter::h((string) ($values['bank_account_number'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>></label>
        <label>Chủ tài khoản<input name="bank_account_holder" value="<?= Formatter::h((string) ($values['bank_account_holder'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>></label>
        <label>Nội dung chuyển khoản<input name="bank_transfer_content" value="<?= Formatter::h((string) ($values['bank_transfer_content'] ?? 'THANH TOAN {order_id}')) ?>" <?= $canManage ? '' : 'readonly' ?>></label>
        <label>Vùng mặc định
            <select name="default_shipping_zone_id" <?= $canManage ? '' : 'disabled' ?>>
                <?php foreach ($zones as $zone): ?>
                    <?php if ((int) $zone['is_active'] === 1): ?>
                        <option value="<?= Formatter::h((string) $zone['zone_id']) ?>" <?= (($values['default_shipping_zone_id'] ?? '') === $zone['zone_id']) ? 'selected' : '' ?>><?= Formatter::h((string) $zone['zone_name']) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <?php if ($canManage): ?>
        <div class="page-actions"><button class="button" type="submit">Lưu cài đặt</button><p class="field-error" data-settings-error hidden></p></div>
    <?php endif; ?>
</form>

<?php if ($canManage): ?>
<section class="panel upload-panel" data-payment-qr-upload>
    <div class="section-heading"><div><span class="eyebrow">Thanh toán</span><h2>QR chuyển khoản</h2></div></div>
    <label>Chọn JPEG/PNG/WebP tối đa 5 MiB<input type="file" accept="image/jpeg,image/png,image/webp" data-upload-file></label>
    <button type="button" class="button" data-upload-payment-qr>Tải QR lên</button>
    <img class="upload-preview" data-upload-preview src="<?= Formatter::h($qrUrl) ?>" alt="QR chuyển khoản" <?= $qrUrl === '' ? 'hidden' : '' ?>>
    <p class="form-hint" data-payment-qr-path><?= Formatter::h($qrPath) ?></p>
    <p class="field-error" data-upload-error hidden></p>
</section>
<?php endif; ?>

<section class="panel">
    <div class="section-heading"><div><span class="eyebrow">Vận chuyển</span><h2>Vùng giao hàng</h2></div></div>
    <div class="zone-list">
        <?php foreach ($zones as $zone): ?>
            <form class="zone-row" data-zone-form>
                <input type="hidden" name="zone_id" value="<?= Formatter::h((string) $zone['zone_id']) ?>">
                <input name="zone_name" maxlength="120" value="<?= Formatter::h((string) $zone['zone_name']) ?>" <?= $canManage ? '' : 'readonly' ?>>
                <input name="fee_vnd" type="number" min="0" value="<?= (int) $zone['fee_vnd'] ?>" <?= $canManage ? '' : 'readonly' ?>>
                <label><input name="is_active" type="checkbox" <?= ((int) $zone['is_active'] === 1) ? 'checked' : '' ?> <?= $canManage ? '' : 'disabled' ?>> Hoạt động</label>
                <span><?= ((int) $zone['is_default'] === 1) ? 'Mặc định' : '' ?></span>
                <?php if ($canManage): ?>
                    <button type="submit" class="button-secondary">Lưu</button>
                    <button type="button" class="button-secondary" data-zone-active="<?= ((int) $zone['is_active'] === 1) ? '0' : '1' ?>"><?= ((int) $zone['is_active'] === 1) ? 'Ngừng' : 'Bật' ?></button>
                    <?php if ((int) $zone['is_default'] !== 1): ?><button type="button" class="button-secondary" data-zone-default>Đặt mặc định</button><?php endif; ?>
                <?php endif; ?>
            </form>
        <?php endforeach; ?>
    </div>
    <?php if ($canManage): ?>
        <form class="zone-row" data-zone-form>
            <input name="zone_id" maxlength="40" placeholder="Mã vùng mới" required>
            <input name="zone_name" maxlength="120" placeholder="Tên vùng" required>
            <input name="fee_vnd" type="number" min="0" value="0">
            <label><input name="is_active" type="checkbox" checked> Hoạt động</label>
            <label><input name="is_default" type="checkbox"> Mặc định</label>
            <button type="submit" class="button">Thêm vùng</button>
        </form>
        <p class="field-error" data-zone-error hidden></p>
    <?php endif; ?>
</section>
