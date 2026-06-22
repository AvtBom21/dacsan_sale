<?php

declare(strict_types=1);

use DacSanNhaDan\Support\Formatter;

$canManage = ($capabilities['reviews_manage'] ?? false) === true;
$currentStatus = (string) ($data['status'] ?? '');
$statusLabels = [
    'pending' => 'Chờ duyệt',
    'approved' => 'Đã duyệt',
    'rejected' => 'Đã từ chối',
];
?>
<section class="toolbar">
    <form method="get">
        <input type="hidden" name="page" value="reviews">
        <select name="status">
            <option value="">Tất cả trạng thái</option>
            <?php foreach ($statusLabels as $value => $label): ?>
                <option value="<?= $value ?>" <?= $currentStatus === $value ? 'selected' : '' ?>>
                    <?= Formatter::h($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Lọc</button>
    </form>
</section>

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Khách hàng</th>
                <th>Sản phẩm / đơn hàng</th>
                <th>Điểm</th>
                <th>Nội dung</th>
                <th>Trạng thái</th>
                <?php if ($canManage): ?><th>Thao tác</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($data['reviews'] ?? []) as $review): ?>
                <tr>
                    <td>
                        <strong><?= Formatter::h((string) $review['customer_name']) ?></strong><br>
                        <small><?= Formatter::h((string) $review['customer_phone']) ?></small>
                    </td>
                    <td>
                        <?= Formatter::h((string) $review['product_name']) ?><br>
                        <a href="./?page=order-detail&amp;id=<?= rawurlencode((string) $review['order_id']) ?>">
                            <?= Formatter::h((string) $review['order_id']) ?>
                        </a>
                    </td>
                    <td class="review-rating"><?= str_repeat('★', (int) $review['rating']) ?></td>
                    <td>
                        <p class="review-content"><?= nl2br(Formatter::h((string) $review['review_text'])) ?></p>
                        <small><?= Formatter::h((string) $review['created_at']) ?></small>
                    </td>
                    <td>
                        <span class="status-pill" data-status="<?= Formatter::h((string) $review['status']) ?>">
                            <?= Formatter::h($statusLabels[(string) $review['status']] ?? (string) $review['status']) ?>
                        </span>
                    </td>
                    <?php if ($canManage): ?>
                        <td>
                            <div class="page-actions">
                                <button
                                    type="button"
                                    class="button"
                                    data-review-moderate="approved"
                                    data-review-id="<?= (int) $review['review_id'] ?>"
                                >Duyệt</button>
                                <button
                                    type="button"
                                    class="button-danger"
                                    data-review-moderate="rejected"
                                    data-review-id="<?= (int) $review['review_id'] ?>"
                                >Từ chối</button>
                            </div>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (($data['reviews'] ?? []) === []): ?>
                <tr><td colspan="<?= $canManage ? 6 : 5 ?>">Chưa có đánh giá phù hợp.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
