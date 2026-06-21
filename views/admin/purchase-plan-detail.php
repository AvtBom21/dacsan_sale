<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

$canManage = ($capabilities['purchase_plans_manage'] ?? false) === true;
?>
<section class="page-actions no-print">
    <a class="button-secondary" href="./?page=purchase-plans">Quay lại danh sách</a>
    <button type="button" class="button-secondary" data-po-copy data-plan-id="<?= Formatter::h((string) $data['plan_id']) ?>">Sao chép nội dung</button>
    <?php if ($canManage && ($data['can_mark_ordered'] ?? false)): ?>
        <button type="button" class="button" data-po-mark-ordered data-plan-id="<?= Formatter::h((string) $data['plan_id']) ?>">Đã đặt hàng</button>
    <?php endif; ?>
    <?php if ($canManage && ($data['can_cancel'] ?? false)): ?>
        <button type="button" class="button-danger" data-po-cancel data-plan-id="<?= Formatter::h((string) $data['plan_id']) ?>">Hủy PO</button>
    <?php endif; ?>
</section>
<p class="field-error no-print" data-po-error hidden></p>

<section class="panel">
    <div class="section-heading">
        <div><span class="eyebrow">Purchase Plan</span><h2><?= Formatter::h((string) $data['plan_id']) ?></h2></div>
        <span class="status-pill" data-status="<?= Formatter::h((string) $data['status']) ?>"><?= Formatter::h((string) $data['status']) ?></span>
    </div>
    <dl class="detail-grid">
        <div><dt>Khoảng đơn</dt><dd><?= Formatter::dateDisplay($data['order_from_date']) ?> → <?= Formatter::dateDisplay($data['order_to_date']) ?></dd></div>
        <div><dt>Nguồn</dt><dd><?= Formatter::h((string) $data['supplier_scope']) ?></dd></div>
        <div><dt>Số đơn</dt><dd><?= count($data['orders']) ?></dd></div>
        <div><dt>Số dòng</dt><dd><?= count($data['items']) ?></dd></div>
        <div><dt>Ghi chú</dt><dd><?= Formatter::h((string) ($data['note'] ?? '—')) ?></dd></div>
    </dl>
</section>

<?php if ($canManage && ($data['can_receive'] ?? false)): ?>
<form class="panel no-print" data-po-receive data-plan-id="<?= Formatter::h((string) $data['plan_id']) ?>">
    <div class="section-heading"><div><span class="eyebrow">Nhập kho</span><h2>Nhận hàng PO</h2></div></div>
    <label>Ghi chú phiếu nhận<input name="note" maxlength="2000"></label>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Sản phẩm</th><th>Còn lại</th><th>Nhận</th><th>Giá/UOM</th><th>Ngày nhận</th><th>HSD</th><th>NCC</th><th>Ghi chú</th></tr></thead>
            <tbody>
            <?php foreach ($data['items'] as $item): ?>
                <?php if ($item['can_receive']): ?>
                <tr data-po-receive-row data-plan-item-id="<?= (int) $item['plan_item_id'] ?>">
                    <td><?= Formatter::h((string) $item['product_name_snapshot']) ?><small><?= Formatter::h((string) $item['uom_label_snapshot']) ?></small></td>
                    <td><?= Formatter::decimalDisplay($item['qty_remaining_uom']) ?></td>
                    <td><input data-receive="qty_received_uom" type="number" min="0" max="<?= Formatter::h((string) $item['qty_remaining_uom']) ?>" step="0.001" value="0"></td>
                    <td><input data-receive="cost_per_uom_vnd" type="number" min="0" step="1" value="<?= (int) $item['cost_per_uom_vnd'] ?>"></td>
                    <td><input data-receive="received_date" type="date" value="<?= date('Y-m-d') ?>"></td>
                    <td><input data-receive="expiry_date" type="date"></td>
                    <td><input data-receive="supplier_name" maxlength="160"></td>
                    <td><input data-receive="note" maxlength="500"></td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="page-actions"><button class="button" type="submit">Ghi nhận hàng</button></div>
</form>
<?php endif; ?>

<section class="table-wrap">
    <h2>Dòng PO</h2>
    <table>
        <thead><tr><th>Sản phẩm</th><th>Nguồn</th><th>Kế hoạch</th><th>Đã nhận</th><th>Còn lại</th><th>Giá/UOM</th></tr></thead>
        <tbody>
        <?php foreach ($data['items'] as $item): ?>
            <tr>
                <td><?= Formatter::h((string) $item['product_name_snapshot']) ?><small><?= Formatter::h((string) $item['uom_label_snapshot']) ?></small></td>
                <td><?= Formatter::h((string) $item['source_location']) ?></td>
                <td><?= Formatter::decimalDisplay($item['qty_planned_uom']) ?></td>
                <td><?= Formatter::decimalDisplay($item['qty_received_uom']) ?></td>
                <td><?= Formatter::decimalDisplay($item['qty_remaining_uom']) ?></td>
                <td><?= Formatter::moneyVnd($item['cost_per_uom_vnd']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="split">
    <div class="table-wrap">
        <h2>Đơn liên kết</h2>
        <table><thead><tr><th>Đơn</th><th>Khách</th><th>Trạng thái</th></tr></thead><tbody>
        <?php foreach ($data['orders'] as $order): ?>
            <tr><td><a href="./?page=order-detail&amp;id=<?= rawurlencode((string) $order['order_id']) ?>"><?= Formatter::h((string) $order['order_id']) ?></a></td><td><?= Formatter::h((string) $order['customer_name']) ?></td><td><?= Formatter::h((string) $order['status']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <div class="panel">
        <h2>Phiếu nhận</h2>
        <?php foreach ($data['receipts'] as $receipt): ?>
            <article class="receipt-card">
                <strong><?= Formatter::h((string) $receipt['receipt_id']) ?></strong>
                <span><?= Formatter::h((string) $receipt['received_at']) ?></span>
                <ul>
                    <?php foreach ($receipt['items'] as $item): ?>
                        <li><?= Formatter::h((string) $item['product_id']) ?>: <?= Formatter::decimalDisplay($item['qty_received_uom']) ?> — lot <?= Formatter::h((string) $item['lot_id']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php endforeach; ?>
        <?php if ($data['receipts'] === []): ?><p>Chưa có phiếu nhận.</p><?php endif; ?>
    </div>
</section>
