<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

?>
<section class="metric-grid">
    <div><span>Đơn hôm nay</span><strong><?= (int) $data['orders_today'] ?></strong></div>
    <div><span>Doanh thu hôm nay</span><strong><?= Formatter::moneyVnd($data['revenue_today_vnd']) ?></strong></div>
    <div><span>Đơn pending</span><strong><?= (int) $data['pending_orders'] ?></strong></div>
    <div><span>Tồn thấp</span><strong><?= (int) $data['low_stock_count'] ?></strong></div>
    <div><span>Sắp hết hạn</span><strong><?= (int) $data['expiring_lots_count'] ?></strong></div>
    <div><span>PO đang mở</span><strong><?= (int) $data['open_purchase_plans'] ?></strong></div>
</section>
