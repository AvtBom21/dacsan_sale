<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

$canManage = ($capabilities['inventory_manage'] ?? false) === true;
?>
<?php if ($canManage): ?>
<section class="panel no-print">
    <div class="section-heading">
        <div><span class="eyebrow">Giao dịch kho</span><h2>Nhập kho thủ công</h2></div>
    </div>
    <form class="form-grid" data-inventory-receive>
        <label>Sản phẩm / UOM
            <select name="uom_key" required>
                <option value="">Chọn sản phẩm</option>
                <?php foreach (($data['purchasable_uoms'] ?? []) as $uom): ?>
                    <option
                        value="<?= Formatter::h((string) $uom['product_id'] . '|' . (string) $uom['uom_id']) ?>"
                        data-source="<?= Formatter::h((string) $uom['default_source']) ?>"
                        data-cost="<?= (int) $uom['cost_price_vnd'] ?>"
                    >
                        <?= Formatter::h((string) $uom['product_name'] . ' — ' . (string) $uom['uom_label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Số lượng<input name="qty_uom" type="number" min="0.001" step="0.001" required></label>
        <label>Nguồn
            <select name="source_location">
                <option>Binh Dinh</option><option>Gia Lai</option><option>Unknown</option>
            </select>
        </label>
        <label>Ngày nhập<input name="received_date" type="date" value="<?= date('Y-m-d') ?>" required></label>
        <label>Hạn sử dụng<input name="expiry_date" type="date"></label>
        <label>Nhà cung cấp<input name="supplier_name" maxlength="160"></label>
        <label>Giá nhập / UOM<input name="cost_per_uom_vnd" type="number" min="0" step="1" value="0"></label>
        <label class="full">Ghi chú<input name="note" maxlength="2000"></label>
        <div class="full page-actions">
            <button class="button" type="submit">Tạo lot nhập kho</button>
            <p class="field-error" data-inventory-error hidden></p>
        </div>
    </form>
</section>
<?php endif; ?>
<section class="split">
    <div>
        <h2>Tồn kho</h2>
        <div class="table-wrap compact">
            <table>
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Nguồn</th>
                        <th>On hand</th>
                        <th>Reserved</th>
                        <th>Available</th>
                        <th>HSD gần nhất</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['summary'] as $row): ?>
                        <tr>
                            <td><?= Formatter::h((string) $row['product_name']) ?></td>
                            <td><?= Formatter::h((string) $row['default_source']) ?></td>
                            <td><?= Formatter::decimalDisplay($row['qty_base_on_hand']) ?></td>
                            <td><?= Formatter::decimalDisplay($row['qty_base_reserved']) ?></td>
                            <td><?= Formatter::decimalDisplay($row['qty_base_available']) ?></td>
                            <td><?= Formatter::h((string) ($row['nearest_expiry_date'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div>
        <h2>Lot</h2>
        <div class="table-wrap compact">
            <table>
                <thead>
                    <tr>
                        <th>Lot</th>
                        <th>Sản phẩm</th>
                        <th>SL</th>
                        <th>HSD</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['lots'] as $lot): ?>
                        <tr>
                            <td><a href="./?page=inventory-lot&amp;id=<?= rawurlencode((string) $lot['lot_id']) ?>"><?= Formatter::h((string) $lot['lot_id']) ?></a></td>
                            <td><?= Formatter::h((string) $lot['product_name']) ?></td>
                            <td><?= Formatter::decimalDisplay($lot['qty_base_on_hand']) ?></td>
                            <td><?= Formatter::h((string) ($lot['expiry_date'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<section>
    <h2>Movement gần đây</h2>
    <div class="table-wrap compact">
        <table>
            <thead>
                <tr>
                    <th>Mã</th>
                    <th>Loại</th>
                    <th>Ref</th>
                    <th>Sản phẩm</th>
                    <th>SL base</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['movements'] as $movement): ?>
                    <tr>
                        <td><?= Formatter::h((string) $movement['movement_id']) ?></td>
                        <td><?= Formatter::h((string) $movement['movement_type']) ?></td>
                        <td><?= Formatter::h((string) $movement['ref_type'] . ':' . (string) $movement['ref_id']) ?></td>
                        <td><?= Formatter::h((string) $movement['product_id']) ?></td>
                        <td><?= Formatter::decimalDisplay($movement['qty_base']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
