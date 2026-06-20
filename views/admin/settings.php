<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;
use DacSanNhaDan\Services\UploadService;

$settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
$settingValues = [];
foreach ($settings as $setting) {
    $settingValues[(string) $setting['setting_key']] = (string) ($setting['setting_value'] ?? '');
}
$qrPath = $settingValues['bank_qr_image_path'] ?? '';
$qrUrl = UploadService::isSafeLocalImagePath($qrPath)
    ? rtrim((string) ($appBase ?? ''), '/') . '/' . $qrPath
    : '';
$canManageSettings = ($capabilities['settings_manage'] ?? false) === true;
?>
<section class="split">
    <div>
        <h2>Shop settings</h2>
        <?php if ($canManageSettings): ?>
            <div class="panel upload-panel" data-payment-qr-upload>
                <h3>QR chuyển khoản</h3>
                <label>Chọn ảnh JPEG/PNG/WebP (tối đa 5 MiB)
                    <input type="file" accept="image/jpeg,image/png,image/webp" data-upload-file>
                </label>
                <button type="button" class="button" data-upload-payment-qr>Tải QR lên</button>
                <img
                    class="upload-preview"
                    data-upload-preview
                    src="<?= Formatter::h($qrUrl) ?>"
                    alt="QR chuyển khoản"
                    <?= $qrUrl === '' ? 'hidden' : '' ?>
                >
                <p class="form-hint" data-payment-qr-path><?= Formatter::h($qrPath) ?></p>
                <p class="field-error" data-upload-error hidden></p>
            </div>
        <?php endif; ?>
        <div class="table-wrap compact">
            <table>
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Value</th>
                        <th>Ghi chú</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($settings as $setting): ?>
                        <tr>
                            <td><?= Formatter::h((string) $setting['setting_key']) ?></td>
                            <td><?= Formatter::h((string) $setting['setting_value']) ?></td>
                            <td><?= Formatter::h((string) ($setting['note'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div>
        <h2>Shipping zones</h2>
        <div class="table-wrap compact">
            <table>
                <thead>
                    <tr>
                        <th>Zone</th>
                        <th>Phí</th>
                        <th>Default</th>
                        <th>Active</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['shipping_zones'] as $zone): ?>
                        <tr>
                            <td><?= Formatter::h((string) $zone['zone_name']) ?></td>
                            <td><?= Formatter::moneyVnd($zone['fee_vnd']) ?></td>
                            <td><?= ((int) $zone['is_default'] === 1) ? 'Có' : '-' ?></td>
                            <td><?= ((int) $zone['is_active'] === 1) ? 'Có' : 'Không' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
