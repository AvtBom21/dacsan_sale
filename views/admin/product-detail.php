<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;
use DacSanNhaDan\Services\UploadService;

$inventory = $data['inventory'] ?? [];
?>
<section class="page-actions">
    <a class="button ghost" href="./?page=products">Quay lại</a>
    <?php if (($capabilities['products_manage'] ?? false) === true): ?>
        <a class="button primary" href="./?page=product-form&amp;id=<?= rawurlencode((string) $data['product_id']) ?>">Sửa sản phẩm</a>
        <button
            type="button"
            data-product-active="<?= ((int) $data['is_active'] === 1) ? '0' : '1' ?>"
            data-product-id="<?= Formatter::h((string) $data['product_id']) ?>"
        ><?= ((int) $data['is_active'] === 1) ? 'Ẩn sản phẩm' : 'Bật sản phẩm' ?></button>
    <?php endif; ?>
</section>

<section class="panel product-summary">
    <div>
        <span class="eyebrow"><?= Formatter::h((string) $data['category_label']) ?></span>
        <h2><?= Formatter::h((string) $data['product_name']) ?></h2>
        <p><?= Formatter::h((string) $data['short_description']) ?></p>
    </div>
    <dl class="detail-grid">
        <div><dt>Mã</dt><dd><?= Formatter::h((string) $data['product_id']) ?></dd></div>
        <div><dt>Slug</dt><dd><?= Formatter::h((string) $data['product_slug']) ?></dd></div>
        <div><dt>Nguồn</dt><dd><?= Formatter::h((string) $data['default_source']) ?></dd></div>
        <div><dt>Đơn vị tồn</dt><dd><?= Formatter::h((string) $data['base_uom_label']) ?></dd></div>
        <div><dt>Hạn dùng</dt><dd><?= (int) $data['shelf_life_value'] ?> <?= Formatter::h((string) $data['shelf_life_unit']) ?></dd></div>
        <div><dt>Trạng thái</dt><dd><?= ((int) $data['is_active'] === 1) ? 'Đang bán' : 'Đã ẩn' ?></dd></div>
    </dl>
</section>

<section class="metric-grid product-metrics">
    <article><span>Tồn thực tế</span><strong><?= Formatter::decimalDisplay($inventory['qty_base_on_hand'] ?? 0) ?></strong></article>
    <article><span>Đã giữ</span><strong><?= Formatter::decimalDisplay($inventory['qty_base_reserved'] ?? 0) ?></strong></article>
    <article><span>Có thể bán</span><strong><?= Formatter::decimalDisplay($inventory['qty_base_available'] ?? 0) ?></strong></article>
    <article><span>Hạn gần nhất</span><strong><?= Formatter::dateDisplay($inventory['nearest_expiry_date'] ?? '') ?: '—' ?></strong></article>
</section>

<section class="split">
    <div class="table-wrap">
        <h2>Đơn vị tính</h2>
        <table>
            <thead><tr><th>UOM</th><th>Quy đổi</th><th>Giá bán</th><th>Giá vốn</th><th>Vai trò</th><th>Trạng thái</th></tr></thead>
            <tbody>
            <?php foreach ($data['uoms'] as $uom): ?>
                <tr>
                    <td><?= Formatter::h((string) $uom['uom_label']) ?><small><?= Formatter::h((string) $uom['uom_id']) ?></small></td>
                    <td><?= Formatter::decimalDisplay($uom['conversion_to_base']) ?></td>
                    <td><?= Formatter::moneyVnd($uom['unit_price_vnd']) ?></td>
                    <td><?= Formatter::moneyVnd($uom['cost_price_vnd']) ?></td>
                    <td><?= ((int) $uom['is_base_unit'] === 1) ? 'Gốc ' : '' ?><?= ((int) $uom['is_default'] === 1) ? 'Mặc định' : '' ?></td>
                    <td><?= ((int) $uom['is_active'] === 1) ? 'Hoạt động' : 'Ngừng' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="panel">
        <h2>Hình ảnh</h2>
        <div class="product-gallery">
            <?php foreach ($data['images'] as $image): ?>
                <figure class="<?= ((int) $image['is_active'] === 1) ? '' : 'is-inactive' ?>">
                    <?php if (UploadService::isSafeLocalImagePath((string) $image['image_path'])): ?>
                        <img src="<?= Formatter::h($appBase . '/' . $image['image_path']) ?>" alt="<?= Formatter::h((string) $image['image_alt']) ?>">
                    <?php else: ?>
                        <div class="field-error">Đường dẫn ảnh không hợp lệ.</div>
                    <?php endif; ?>
                    <figcaption>
                        <?= Formatter::h((string) $image['image_alt']) ?>
                        <?= ((int) $image['is_base'] === 1) ? ' · Ảnh chính' : '' ?>
                        <?= ((int) $image['is_active'] === 0) ? ' · Ngừng' : '' ?>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
            <?php if ($data['images'] === []): ?><p>Chưa có metadata ảnh.</p><?php endif; ?>
        </div>
    </div>
</section>

<section class="panel prose">
    <h2>Mô tả</h2>
    <p><?= nl2br(Formatter::h((string) $data['full_description'])) ?></p>
    <h3>Thành phần</h3>
    <p><?= nl2br(Formatter::h((string) $data['ingredients'])) ?></p>
</section>
