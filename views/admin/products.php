<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

?>
<section class="toolbar product-toolbar">
    <form method="get">
        <input type="hidden" name="page" value="products">
        <input name="q" placeholder="Tìm sản phẩm" value="<?= Formatter::h((string) ($data['filters']['q'] ?? '')) ?>">
        <select name="is_active">
            <option value="">Tất cả</option>
            <option value="1" <?= (($data['filters']['is_active'] ?? '') === 1) ? 'selected' : '' ?>>Đang bán</option>
            <option value="0" <?= (($data['filters']['is_active'] ?? '') === 0) ? 'selected' : '' ?>>Đã ẩn</option>
        </select>
        <button>Lọc</button>
    </form>
    <?php if (($capabilities['products_manage'] ?? false) === true): ?>
        <a class="button primary" href="./?page=product-form">Thêm sản phẩm</a>
    <?php endif; ?>
</section>
<section class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Mã</th>
                <th>Sản phẩm</th>
                <th>Nhóm</th>
                <th>Nguồn</th>
                <th>UOM</th>
                <th>Ảnh</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['items'] as $product): ?>
                <tr>
                    <td><?= Formatter::h((string) $product['product_id']) ?></td>
                    <td>
                        <a href="./?page=product-detail&amp;id=<?= rawurlencode((string) $product['product_id']) ?>">
                            <?= Formatter::h((string) $product['product_name']) ?>
                        </a>
                    </td>
                    <td><?= Formatter::h((string) $product['category_label']) ?></td>
                    <td><?= Formatter::h((string) $product['default_source']) ?></td>
                    <td><?= (int) $product['uom_count'] ?></td>
                    <td><?= (int) $product['image_count'] ?></td>
                    <td><span class="pill"><?= ((int) $product['is_active'] === 1) ? 'Đang bán' : 'Đã ẩn' ?></span></td>
                    <td class="row-actions">
                        <a href="./?page=product-detail&amp;id=<?= rawurlencode((string) $product['product_id']) ?>">Chi tiết</a>
                        <?php if (($capabilities['products_manage'] ?? false) === true): ?>
                            <a href="./?page=product-form&amp;id=<?= rawurlencode((string) $product['product_id']) ?>">Sửa</a>
                            <button
                                type="button"
                                class="link-button"
                                data-product-active="<?= ((int) $product['is_active'] === 1) ? '0' : '1' ?>"
                                data-product-id="<?= Formatter::h((string) $product['product_id']) ?>"
                            ><?= ((int) $product['is_active'] === 1) ? 'Ẩn' : 'Bật' ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($data['items'] === []): ?>
                <tr><td colspan="8">Chưa có sản phẩm phù hợp.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
