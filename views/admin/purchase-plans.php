<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

?>
<section class="toolbar">
    <form method="get">
        <input type="hidden" name="page" value="purchase-plans">
        <select name="status">
            <option value="">Tất cả PO</option>
            <?php foreach (['draft', 'ordered', 'partial_received', 'received', 'closed', 'cancelled'] as $status): ?>
                <option value="<?= $status ?>" <?= (($data['filters']['status'] ?? '') === $status) ? 'selected' : '' ?>><?= $status ?></option>
            <?php endforeach; ?>
        </select>
        <button>Lọc</button>
    </form>
</section>
<section class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>PO</th>
                <th>Ngày tạo</th>
                <th>Khoảng đơn</th>
                <th>Nguồn</th>
                <th>Trạng thái</th>
                <th>Dòng</th>
                <th>Đơn</th>
                <th>Đã nhận/base</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['items'] as $plan): ?>
                <tr>
                    <td><a href="./?page=purchase-plan-detail&amp;id=<?= rawurlencode((string) $plan['plan_id']) ?>"><?= Formatter::h((string) $plan['plan_id']) ?></a></td>
                    <td><?= Formatter::h((string) $plan['created_at']) ?></td>
                    <td><?= Formatter::h((string) $plan['order_from_date'] . ' → ' . (string) $plan['order_to_date']) ?></td>
                    <td><?= Formatter::h((string) $plan['supplier_scope']) ?></td>
                    <td><span class="pill"><?= Formatter::h((string) $plan['status']) ?></span></td>
                    <td><?= (int) $plan['item_count'] ?></td>
                    <td><?= (int) $plan['order_count'] ?></td>
                    <td><?= Formatter::decimalDisplay($plan['qty_received_base']) ?> / <?= Formatter::decimalDisplay($plan['qty_planned_base']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($data['items'] === []): ?>
                <tr><td colspan="8">Chưa có PO phù hợp.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
<?php require __DIR__ . '/pagination.php'; ?>
