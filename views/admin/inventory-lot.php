<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

$canManage = ($capabilities['inventory_manage'] ?? false) === true;
?>
<section class="page-actions">
    <a class="button-secondary" href="./?page=inventory">Quay lại kho</a>
</section>

<section class="panel">
    <div class="section-heading">
        <div><span class="eyebrow"><?= Formatter::h((string) $data['product_name']) ?></span><h2><?= Formatter::h((string) $data['lot_id']) ?></h2></div>
        <span class="status-pill" data-status="received"><?= Formatter::h((string) $data['source_location']) ?></span>
    </div>
    <dl class="detail-grid">
        <div><dt>On hand</dt><dd><?= Formatter::decimalDisplay($data['qty_base_on_hand']) ?> <?= Formatter::h((string) $data['base_uom_label']) ?></dd></div>
        <div><dt>Reserved</dt><dd><?= Formatter::decimalDisplay($data['qty_base_reserved']) ?></dd></div>
        <div><dt>Available</dt><dd><?= Formatter::decimalDisplay((float) $data['qty_base_on_hand'] - (float) $data['qty_base_reserved']) ?></dd></div>
        <div><dt>Ngày nhập</dt><dd><?= Formatter::dateDisplay($data['received_date']) ?></dd></div>
        <div><dt>Hạn sử dụng</dt><dd><?= Formatter::dateDisplay($data['expiry_date']) ?: '—' ?></dd></div>
        <div><dt>Nhà cung cấp</dt><dd><?= Formatter::h((string) ($data['supplier_name'] ?? '—')) ?></dd></div>
        <div><dt>UOM nhập</dt><dd><?= Formatter::h((string) ($data['received_uom_label'] ?? '—')) ?></dd></div>
        <div><dt>SL nhập</dt><dd><?= Formatter::decimalDisplay($data['received_qty_uom']) ?></dd></div>
        <div><dt>Giá vốn / base</dt><dd><?= Formatter::moneyVnd($data['cost_per_base_unit_vnd']) ?></dd></div>
    </dl>
</section>

<?php if ($canManage): ?>
<section class="panel no-print">
    <h2>Điều chỉnh tồn lot</h2>
    <form class="form-grid" data-inventory-adjust data-lot-id="<?= Formatter::h((string) $data['lot_id']) ?>">
        <label>Số lượng base tăng/giảm
            <input name="delta_base" type="number" step="0.001" required placeholder="Ví dụ: 2 hoặc -1">
        </label>
        <label class="full">Lý do
            <input name="reason" minlength="3" maxlength="500" required>
        </label>
        <div class="full page-actions">
            <button class="button" type="submit">Ghi điều chỉnh</button>
            <p class="field-error" data-inventory-error hidden></p>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="table-wrap">
    <h2>Lịch sử movement</h2>
    <table>
        <thead><tr><th>Thời điểm</th><th>Mã</th><th>Loại</th><th>Ref</th><th>SL base</th><th>Ghi chú</th></tr></thead>
        <tbody>
        <?php foreach (($data['movements'] ?? []) as $movement): ?>
            <tr>
                <td><?= Formatter::h((string) $movement['created_at']) ?></td>
                <td><?= Formatter::h((string) $movement['movement_id']) ?></td>
                <td><?= Formatter::h((string) $movement['movement_type']) ?></td>
                <td><?= Formatter::h((string) $movement['ref_type'] . ':' . (string) ($movement['ref_id'] ?? '')) ?></td>
                <td><?= Formatter::decimalDisplay($movement['qty_base']) ?></td>
                <td><?= Formatter::h((string) ($movement['note'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (($data['movements'] ?? []) === []): ?><tr><td colspan="6">Chưa có movement.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
