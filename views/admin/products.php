<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

?>
<section class="toolbar">
    <form method="get">
        <input type="hidden" name="page" value="products">
        <input name="q" placeholder="Tìm sản phẩm" value="<?= Formatter::h((string) ($data['filters']['q'] ?? '')) ?>">
        <select name="is_active">
            <option value="">Tất cả</option>
            <option value="1" <?= (($data['filters']['is_active'] ?? '') === 1) ? 'selected' : '' ?>>Đang bán</option>
            <option value="0" <?= (($data['filters']['is_active'] ?? '') === 0) ? 'selected' : '' ?>>Ẩn</option>
        </select>
        <button>Lọc</button>
    </form>
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
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['items'] as $product): ?>
                <tr>
                    <td><?= Formatter::h((string) $product['product_id']) ?></td>
                    <td><?= Formatter::h((string) $product['product_name']) ?></td>
                    <td><?= Formatter::h((string) $product['category_label']) ?></td>
                    <td><?= Formatter::h((string) $product['default_source']) ?></td>
                    <td><?= (int) $product['uom_count'] ?></td>
                    <td><?= (int) $product['image_count'] ?></td>
                    <td><span class="pill"><?= ((int) $product['is_active'] === 1) ? 'Đang bán' : 'Ẩn' ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($data['items'] === []): ?>
                <tr><td colspan="7">Chưa có sản phẩm phù hợp.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
