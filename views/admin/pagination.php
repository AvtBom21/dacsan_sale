<?php

declare(strict_types=1);

$pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
$currentPage = (int) ($pagination['page'] ?? 1);
$totalPages = (int) ($pagination['total_pages'] ?? 0);
if ($totalPages > 1):
    $query = $_GET;
?>
<nav class="pagination" aria-label="Phân trang">
    <?php if ($currentPage > 1): $query['p'] = $currentPage - 1; ?>
        <a class="button-secondary" href="?<?= htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8') ?>">Trang trước</a>
    <?php endif; ?>
    <span>Trang <?= $currentPage ?> / <?= $totalPages ?> · <?= (int) $pagination['total'] ?> kết quả</span>
    <?php if ($currentPage < $totalPages): $query['p'] = $currentPage + 1; ?>
        <a class="button-secondary" href="?<?= htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8') ?>">Trang sau</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
