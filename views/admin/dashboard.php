<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;
?>
<section class="metric-grid">
    <a href="./?page=orders"><span>Đơn hôm nay</span><strong><?= (int) $data['orders_today'] ?></strong></a>
    <a href="./?page=orders"><span>Doanh thu hôm nay</span><strong><?= Formatter::moneyVnd($data['revenue_today_vnd']) ?></strong></a>
    <a href="./?page=orders"><span>Đơn đang xử lý</span><strong><?= (int) $data['pending_orders'] ?></strong></a>
    <?php if (($capabilities['inventory'] ?? false) === true): ?>
        <a href="./?page=inventory"><span>Tồn thấp</span><strong><?= (int) $data['low_stock_count'] ?></strong></a>
        <a href="./?page=inventory"><span>Sắp hết hạn</span><strong><?= (int) $data['expiring_lots_count'] ?></strong></a>
    <?php endif; ?>
    <a href="./?page=purchase-plans"><span>PO đang mở</span><strong><?= (int) $data['open_purchase_plans'] ?></strong></a>
</section>
