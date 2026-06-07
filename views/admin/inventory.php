<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

?>
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
                            <td><?= Formatter::h((string) $lot['lot_id']) ?></td>
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
