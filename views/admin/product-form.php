<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

$product = is_array($data['product'] ?? null) ? $data['product'] : null;
$categories = is_array($data['categories'] ?? null) ? $data['categories'] : [];
$uoms = $product['uoms'] ?? [[
    'uom_id' => '', 'uom_label' => '', 'conversion_to_base' => 1,
    'unit_price_vnd' => 0, 'cost_price_vnd' => 0, 'is_base_unit' => 1,
    'is_default' => 1, 'is_sellable' => 1, 'is_purchasable' => 1,
    'is_active' => 1, 'sort_order' => 0, 'note' => '',
]];
$images = $product['images'] ?? [];
?>
<form class="product-form" data-product-form>
    <input type="hidden" data-field="original_product_id" value="<?= Formatter::h((string) ($product['product_id'] ?? '')) ?>">
    <section class="panel form-section">
        <div class="section-heading">
            <div><span class="eyebrow">Thông tin chính</span><h2>Sản phẩm</h2></div>
            <a href="./?page=products">Hủy</a>
        </div>
        <div class="form-grid">
            <label>Mã sản phẩm
                <input data-field="product_id" required maxlength="40" value="<?= Formatter::h((string) ($product['product_id'] ?? '')) ?>" <?= $product ? 'readonly' : '' ?>>
            </label>
            <label>Tên sản phẩm
                <input data-field="product_name" required maxlength="200" value="<?= Formatter::h((string) ($product['product_name'] ?? '')) ?>">
            </label>
            <label>Slug
                <input data-field="product_slug" required maxlength="220" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?= Formatter::h((string) ($product['product_slug'] ?? '')) ?>">
            </label>
            <label>Nhóm
                <select data-field="category_id" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= Formatter::h((string) $category['category_id']) ?>" <?= (($product['category_id'] ?? '') === $category['category_id']) ? 'selected' : '' ?>>
                            <?= Formatter::h((string) $category['category_name']) ?><?= ((int) $category['is_active'] === 0) ? ' (ngừng)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Nguồn
                <select data-field="default_source">
                    <?php foreach (['Binh Dinh', 'Gia Lai', 'Unknown'] as $source): ?>
                        <option <?= (($product['default_source'] ?? 'Unknown') === $source) ? 'selected' : '' ?>><?= Formatter::h($source) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Đơn vị tồn kho
                <input data-field="base_uom_label" required maxlength="80" value="<?= Formatter::h((string) ($product['base_uom_label'] ?? '')) ?>">
            </label>
            <label>Hạn sử dụng
                <input data-field="shelf_life_value" type="number" min="0" step="1" value="<?= (int) ($product['shelf_life_value'] ?? 0) ?>">
            </label>
            <label>Đơn vị hạn
                <select data-field="shelf_life_unit">
                    <?php foreach (['' => 'Không áp dụng', 'days' => 'Ngày', 'months' => 'Tháng'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= (($product['shelf_life_unit'] ?? '') === $value) ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="check-field"><input data-field="is_active" type="checkbox" <?= ((int) ($product['is_active'] ?? 1) === 1) ? 'checked' : '' ?>> Đang hoạt động</label>
            <label class="full">Mô tả ngắn
                <input data-field="short_description" maxlength="255" value="<?= Formatter::h((string) ($product['short_description'] ?? '')) ?>">
            </label>
            <label class="full">Mô tả đầy đủ
                <textarea data-field="full_description" rows="5"><?= Formatter::h((string) ($product['full_description'] ?? '')) ?></textarea>
            </label>
            <label class="full">Thành phần
                <textarea data-field="ingredients" rows="3"><?= Formatter::h((string) ($product['ingredients'] ?? '')) ?></textarea>
            </label>
        </div>
    </section>

    <section class="panel form-section">
        <div class="section-heading">
            <div><span class="eyebrow">Quy đổi và giá</span><h2>Đơn vị tính</h2></div>
            <button type="button" data-add-uom>Thêm UOM</button>
        </div>
        <div class="repeat-list" data-uom-list>
            <?php foreach ($uoms as $uom): ?>
                <div class="repeat-row" data-uom-row>
                    <input data-uom="uom_id" placeholder="Mã UOM" required value="<?= Formatter::h((string) $uom['uom_id']) ?>">
                    <input data-uom="uom_label" placeholder="Tên UOM" required value="<?= Formatter::h((string) $uom['uom_label']) ?>">
                    <label>Quy đổi<input data-uom="conversion_to_base" type="number" min="0.001" step="0.001" value="<?= Formatter::h((string) $uom['conversion_to_base']) ?>"></label>
                    <label>Giá bán<input data-uom="unit_price_vnd" type="number" min="0" step="1" value="<?= (int) $uom['unit_price_vnd'] ?>"></label>
                    <label>Giá vốn<input data-uom="cost_price_vnd" type="number" min="0" step="1" value="<?= (int) $uom['cost_price_vnd'] ?>"></label>
                    <label><input data-uom="is_base_unit" type="checkbox" <?= ((int) $uom['is_base_unit'] === 1) ? 'checked' : '' ?>> Gốc</label>
                    <label><input data-uom="is_default" type="checkbox" <?= ((int) $uom['is_default'] === 1) ? 'checked' : '' ?>> Mặc định</label>
                    <label><input data-uom="is_sellable" type="checkbox" <?= ((int) $uom['is_sellable'] === 1) ? 'checked' : '' ?>> Bán</label>
                    <label><input data-uom="is_purchasable" type="checkbox" <?= ((int) $uom['is_purchasable'] === 1) ? 'checked' : '' ?>> Nhập</label>
                    <label><input data-uom="is_active" type="checkbox" <?= ((int) $uom['is_active'] === 1) ? 'checked' : '' ?>> Hoạt động</label>
                    <input data-uom="sort_order" type="number" min="0" step="1" placeholder="Thứ tự" value="<?= (int) $uom['sort_order'] ?>">
                    <input data-uom="note" placeholder="Ghi chú" value="<?= Formatter::h((string) $uom['note']) ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <p class="form-hint">UOM bỏ khỏi payload sẽ được chuyển sang ngừng hoạt động, không bị xóa.</p>
    </section>

    <section class="panel form-section">
        <div class="section-heading">
            <div><span class="eyebrow">Metadata</span><h2>Hình ảnh</h2></div>
            <button type="button" data-add-image>Thêm ảnh</button>
        </div>
        <div class="repeat-list" data-image-list>
            <?php foreach ($images as $image): ?>
                <div class="repeat-row image-row" data-image-row>
                    <input type="hidden" data-image="image_id" value="<?= (int) $image['image_id'] ?>">
                    <input data-image="image_path" placeholder="products_image/ten-file.jpg" required value="<?= Formatter::h((string) $image['image_path']) ?>">
                    <input data-image="source_url" placeholder="URL nguồn" value="<?= Formatter::h((string) $image['source_url']) ?>">
                    <input data-image="image_alt" placeholder="Mô tả ảnh" value="<?= Formatter::h((string) $image['image_alt']) ?>">
                    <label><input data-image="is_base" type="checkbox" <?= ((int) $image['is_base'] === 1) ? 'checked' : '' ?>> Ảnh chính</label>
                    <label><input data-image="is_active" type="checkbox" <?= ((int) $image['is_active'] === 1) ? 'checked' : '' ?>> Hoạt động</label>
                    <input data-image="sort_order" type="number" min="0" step="1" placeholder="Thứ tự" value="<?= (int) $image['sort_order'] ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <p class="form-hint">Task này chỉ quản lý đường dẫn/URL. Tải file trực tiếp được triển khai ở Task 5.</p>
    </section>

    <section class="form-submit">
        <p data-product-form-error class="action-error" hidden></p>
        <button class="primary" type="submit">Lưu sản phẩm</button>
    </section>
</form>
