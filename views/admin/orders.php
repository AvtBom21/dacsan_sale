<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

$canManagePlans = ($capabilities['purchase_plans_manage'] ?? false) === true;
?>
<section class="toolbar">
    <form method="get">
        <input type="hidden" name="page" value="orders">
        <input name="q" placeholder="Tìm mã đơn, tên, SĐT" value="<?= Formatter::h((string) ($data['filters']['q'] ?? '')) ?>">
        <select name="status">
            <option value="">Tất cả trạng thái</option>
            <?php foreach (['new', 'confirmed', 'ordered', 'received', 'ready', 'done', 'cancelled'] as $status): ?>
                <option value="<?= $status ?>" <?= (($data['filters']['status'] ?? '') === $status) ? 'selected' : '' ?>><?= $status ?></option>
            <?php endforeach; ?>
        </select>
        <button>Lọc</button>
    </form>
</section>
<?php if ($canManagePlans): ?>
<section class="panel no-print" data-po-builder>
    <div class="section-heading">
        <div><span class="eyebrow">Purchase Plan</span><h2>Gom đơn đã xác nhận</h2></div>
        <div class="page-actions">
            <button type="button" class="button-secondary" data-po-preview>Xem trước PO</button>
            <button type="button" class="button" data-po-create>Tạo PO</button>
        </div>
    </div>
    <label>Ghi chú PO<input data-po-note maxlength="2000"></label>
    <p class="field-error" data-po-error hidden></p>
    <div class="po-preview" data-po-preview-panel hidden></div>
</section>
<?php endif; ?>
<section class="table-wrap">
    <table>
        <thead>
            <tr>
                <?php if ($canManagePlans): ?><th>Chọn</th><?php endif; ?>
                <th>Mã đơn</th>
                <th>Thời điểm</th>
                <th>Khách</th>
                <th>SĐT</th>
                <th>Trạng thái</th>
                <th>Tổng</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['items'] as $order): ?>
                <tr>
                    <?php if ($canManagePlans): ?>
                        <td>
                            <input
                                type="checkbox"
                                data-po-order
                                value="<?= Formatter::h((string) $order['order_id']) ?>"
                                <?= ((string) $order['status'] === 'confirmed' && (int) ($order['unplanned_item_count'] ?? 0) > 0) ? '' : 'disabled' ?>
                                aria-label="Chọn đơn <?= Formatter::h((string) $order['order_id']) ?>"
                            >
                        </td>
                    <?php endif; ?>
                    <td>
                        <a href="./?page=order-detail&amp;id=<?= rawurlencode((string) $order['order_id']) ?>">
                            <?= Formatter::h((string) $order['order_id']) ?>
                        </a>
                    </td>
                    <td><?= Formatter::h((string) $order['created_at']) ?></td>
                    <td><?= Formatter::h((string) $order['customer_name']) ?></td>
                    <td><?= Formatter::h((string) $order['customer_phone']) ?></td>
                    <td><span class="pill"><?= Formatter::h((string) $order['status']) ?></span></td>
                    <td><?= Formatter::moneyVnd($order['total_vnd']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($data['items'] === []): ?>
                <tr><td colspan="<?= $canManagePlans ? 7 : 6 ?>">Chưa có đơn phù hợp.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
<?php require __DIR__ . '/pagination.php'; ?>
