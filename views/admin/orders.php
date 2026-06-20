<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

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
<section class="table-wrap">
    <table>
        <thead>
            <tr>
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
                <tr><td colspan="6">Chưa có đơn phù hợp.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
