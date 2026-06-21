<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

$canManagePlans = ($capabilities['purchase_plans_manage'] ?? false) === true;
$statusLabels = [
    'new' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'ordered' => 'Đang đặt hàng',
    'received' => 'Đã nhận hàng',
    'ready' => 'Sẵn sàng giao',
    'done' => 'Hoàn tất',
    'cancelled' => 'Đã hủy',
];
$planStatusLabels = [
    'draft' => 'Nháp',
    'ordered' => 'Đã đặt hàng',
    'partial_received' => 'Nhận một phần',
    'received' => 'Đã nhận đủ',
    'closed' => 'Đã đóng',
    'cancelled' => 'Đã hủy',
];
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
        <div>
            <span class="eyebrow">Purchase Plan</span>
            <h2>Tạo kế hoạch đặt hàng</h2>
            <p class="section-description">Chỉ chọn đơn đã xác nhận và còn sản phẩm chưa thuộc PO. Đơn đã có PO sẽ hiển thị liên kết để tiếp tục xử lý.</p>
        </div>
        <div class="page-actions">
            <span class="selection-count" data-po-selected-count>Đã chọn 0 đơn</span>
            <button type="button" class="button-secondary" data-po-preview disabled>Xem trước (0)</button>
            <button type="button" class="button" data-po-create disabled>Tạo PO (0)</button>
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
                <th>Tình trạng PO</th>
                <th>Tổng</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['items'] as $order): ?>
                <?php
                $status = (string) $order['status'];
                $isEligible = $status === 'confirmed' && (int) ($order['unplanned_item_count'] ?? 0) > 0;
                $linkedPlans = [];
                foreach (array_filter(explode(',', (string) ($order['linked_plans'] ?? ''))) as $linkedPlan) {
                    [$linkedPlanId, $linkedPlanStatus] = array_pad(explode('|', $linkedPlan, 2), 2, '');
                    if ($linkedPlanId !== '') {
                        $linkedPlans[] = ['id' => $linkedPlanId, 'status' => $linkedPlanStatus];
                    }
                }
                $disabledReason = '';
                if (!$isEligible) {
                    if ($linkedPlans !== []) {
                        $disabledReason = 'Đơn đã thuộc PO';
                    } elseif ($status === 'new') {
                        $disabledReason = 'Cần xác nhận đơn trước';
                    } elseif ($status !== 'confirmed') {
                        $disabledReason = 'Trạng thái hiện tại không thể tạo PO';
                    } else {
                        $disabledReason = 'Không còn sản phẩm chưa gom PO';
                    }
                }
                ?>
                <tr>
                    <?php if ($canManagePlans): ?>
                        <td class="selection-cell">
                            <input
                                type="checkbox"
                                data-po-order
                                value="<?= Formatter::h((string) $order['order_id']) ?>"
                                <?= $isEligible ? '' : 'disabled' ?>
                                title="<?= Formatter::h($disabledReason) ?>"
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
                    <td><span class="status-pill" data-status="<?= Formatter::h($status) ?>"><?= Formatter::h($statusLabels[$status] ?? $status) ?></span></td>
                    <td>
                        <?php if ($linkedPlans !== []): ?>
                            <div class="linked-plan-list">
                                <?php foreach ($linkedPlans as $linkedPlan): ?>
                                    <a href="./?page=purchase-plan-detail&amp;id=<?= rawurlencode($linkedPlan['id']) ?>">
                                        <?= Formatter::h($linkedPlan['id']) ?>
                                    </a>
                                    <small><?= Formatter::h($planStatusLabels[(string) $linkedPlan['status']] ?? (string) $linkedPlan['status']) ?></small>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($isEligible): ?>
                            <span class="po-eligibility po-eligibility-ready">Sẵn sàng tạo PO</span>
                        <?php else: ?>
                            <span class="po-eligibility"><?= Formatter::h($disabledReason) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= Formatter::moneyVnd($order['total_vnd']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($data['items'] === []): ?>
                <tr><td colspan="<?= $canManagePlans ? 8 : 7 ?>">Chưa có đơn phù hợp.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
<?php require __DIR__ . '/pagination.php'; ?>
