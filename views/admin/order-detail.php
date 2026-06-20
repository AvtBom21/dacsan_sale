<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;
use DacSanNhaDan\Services\UploadService;

$statusLabels = [
    'new' => 'Mới',
    'confirmed' => 'Đã xác nhận',
    'ordered' => 'Đã đặt hàng',
    'received' => 'Đã nhận hàng',
    'ready' => 'Sẵn sàng giao',
    'done' => 'Hoàn tất',
    'cancelled' => 'Đã hủy',
];
$shippingLabels = [
    'delivery' => 'Giao hàng',
    'pickup' => 'Khách đến lấy',
];
$allowedTransitions = is_array($data['allowed_next_statuses'] ?? null)
    ? $data['allowed_next_statuses']
    : [];
$store = is_array($data['store'] ?? null) ? $data['store'] : [];
$payment = is_array($data['payment'] ?? null) ? $data['payment'] : [];
$canTransition = ($capabilities['orders_transition'] ?? false) === true;
$canPrint = ($capabilities['orders_print'] ?? false) === true;
$qrPath = trim((string) ($payment['bank_qr_image_path'] ?? ''));
$qrUrl = !UploadService::isSafeLocalImagePath($qrPath)
    ? ''
    : rtrim((string) ($appBase ?? ''), '/') . '/' . ltrim($qrPath, '/');

?>
<section class="order-operations panel no-print">
    <div>
        <h2>Vận hành đơn hàng</h2>
        <p>
            Trạng thái:
            <span class="status-pill" data-status="<?= Formatter::h((string) $data['status']) ?>">
                <?= Formatter::h($statusLabels[(string) $data['status']] ?? (string) $data['status']) ?>
            </span>
        </p>
    </div>
    <div class="page-actions">
        <?php if ($canTransition): ?>
            <?php foreach ($allowedTransitions as $nextStatus): ?>
                <button
                    type="button"
                    class="<?= $nextStatus === 'cancelled' ? 'button-danger' : 'button' ?>"
                    data-order-status="<?= Formatter::h((string) $nextStatus) ?>"
                    data-order-id="<?= Formatter::h((string) $data['order_id']) ?>"
                >
                    <?= Formatter::h($statusLabels[(string) $nextStatus] ?? (string) $nextStatus) ?>
                </button>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($canPrint): ?>
            <button type="button" class="button-secondary" data-print-invoice>In hóa đơn</button>
        <?php endif; ?>
        <a class="button-secondary" href="./?page=orders">Quay lại danh sách</a>
    </div>
    <p class="field-error" data-order-action-error hidden></p>
</section>

<article class="invoice" id="invoice">
    <header class="invoice-header">
        <div>
            <p class="invoice-kicker">HÓA ĐƠN BÁN HÀNG</p>
            <h2><?= Formatter::h((string) ($store['store_name'] ?? 'Đặc Sản Nhà Dân')) ?></h2>
            <?php if (trim((string) ($store['store_phone'] ?? '')) !== ''): ?>
                <p>Điện thoại: <?= Formatter::h((string) $store['store_phone']) ?></p>
            <?php endif; ?>
        </div>
        <div class="invoice-meta">
            <strong>#<?= Formatter::h((string) $data['order_id']) ?></strong>
            <span><?= Formatter::h((string) $data['created_at']) ?></span>
            <span><?= Formatter::h($statusLabels[(string) $data['status']] ?? (string) $data['status']) ?></span>
        </div>
    </header>

    <section class="detail-grid invoice-parties">
        <div>
            <h3>Khách hàng</h3>
            <p><strong><?= Formatter::h((string) $data['customer_name']) ?></strong></p>
            <p><?= Formatter::h((string) $data['customer_phone']) ?></p>
            <?php if (trim((string) ($data['customer_address'] ?? '')) !== ''): ?>
                <p><?= Formatter::h((string) $data['customer_address']) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <h3>Nhận hàng</h3>
            <p><?= Formatter::h($shippingLabels[(string) $data['shipping_method']] ?? (string) $data['shipping_method']) ?></p>
            <?php if (!empty($data['receive_date'])): ?>
                <p>Ngày nhận: <?= Formatter::h((string) $data['receive_date']) ?></p>
            <?php endif; ?>
            <?php if (trim((string) ($data['note'] ?? '')) !== ''): ?>
                <p>Ghi chú: <?= Formatter::h((string) $data['note']) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <div class="table-wrap invoice-items">
        <table>
            <thead>
                <tr>
                    <th>Sản phẩm / UOM</th>
                    <th>Số lượng</th>
                    <th>Đơn giá</th>
                    <th>Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['items'] as $item): ?>
                    <tr>
                        <td>
                            <strong><?= Formatter::h((string) $item['product_name_snapshot']) ?></strong>
                            <span><?= Formatter::h((string) $item['uom_label_snapshot']) ?></span>
                        </td>
                        <td><?= Formatter::decimalDisplay($item['qty_uom']) ?></td>
                        <td><?= Formatter::moneyVnd($item['unit_price_vnd']) ?></td>
                        <td><?= Formatter::moneyVnd($item['line_total_vnd']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <section class="invoice-summary">
        <div><span>Tạm tính</span><strong><?= Formatter::moneyVnd($data['subtotal_vnd']) ?></strong></div>
        <div><span>Phí giao hàng</span><strong><?= Formatter::moneyVnd($data['shipping_fee_vnd']) ?></strong></div>
        <div class="invoice-total"><span>Tổng thanh toán</span><strong><?= Formatter::moneyVnd($data['total_vnd']) ?></strong></div>
    </section>

    <?php if (
        trim((string) ($payment['bank_name'] ?? '')) !== ''
        || trim((string) ($payment['bank_account_number'] ?? '')) !== ''
        || $qrUrl !== ''
    ): ?>
        <section class="invoice-payment">
            <div>
                <h3>Thông tin chuyển khoản</h3>
                <?php if (trim((string) ($payment['bank_name'] ?? '')) !== ''): ?>
                    <p>Ngân hàng: <strong><?= Formatter::h((string) $payment['bank_name']) ?></strong></p>
                <?php endif; ?>
                <?php if (trim((string) ($payment['bank_account_number'] ?? '')) !== ''): ?>
                    <p>Số tài khoản: <strong><?= Formatter::h((string) $payment['bank_account_number']) ?></strong></p>
                <?php endif; ?>
                <?php if (trim((string) ($payment['bank_account_holder'] ?? '')) !== ''): ?>
                    <p>Chủ tài khoản: <strong><?= Formatter::h((string) $payment['bank_account_holder']) ?></strong></p>
                <?php endif; ?>
                <?php if (trim((string) ($payment['bank_transfer_content'] ?? '')) !== ''): ?>
                    <p>Nội dung: <strong><?= Formatter::h((string) $payment['bank_transfer_content']) ?></strong></p>
                <?php endif; ?>
            </div>
            <?php if ($qrUrl !== ''): ?>
                <img class="payment-qr" src="<?= Formatter::h($qrUrl) ?>" alt="QR chuyển khoản">
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <footer class="invoice-footer">
        Cảm ơn quý khách đã ủng hộ <?= Formatter::h((string) ($store['store_name'] ?? 'Đặc Sản Nhà Dân')) ?>.
    </footer>
</article>
