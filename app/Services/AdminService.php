<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\AdminDashboardRepository;

final class AdminService
{
    private const ORDER_STATUSES = ['new', 'confirmed', 'ordered', 'received', 'ready', 'done', 'cancelled'];
    private const PLAN_STATUSES = ['draft', 'ordered', 'partial_received', 'received', 'closed', 'cancelled'];

    public function __construct(private AdminDashboardRepository $dashboard)
    {
    }

    /**
     * @return array<string, int>
     */
    public function dashboard(): array
    {
        return $this->dashboard->dashboardMetrics();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function orders(array $filters = []): array
    {
        $clean = $this->cleanListFilters($filters);
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            if (!in_array($status, self::ORDER_STATUSES, true)) {
                throw new AppException('Trạng thái đơn hàng không hợp lệ.', 422);
            }
            $clean['status'] = $status;
        }

        foreach (['date_from', 'date_to'] as $key) {
            $date = trim((string) ($filters[$key] ?? ''));
            if ($date !== '') {
                $clean[$key] = $this->cleanDate($date, 'Ngày lọc đơn hàng không hợp lệ.');
            }
        }

        return $this->paginated(
            $this->dashboard->listOrders($clean),
            $this->dashboard->countOrders($clean),
            $clean
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function orderDetail(string $orderId): ?array
    {
        $orderId = $this->cleanId($orderId, 'Mã đơn hàng không hợp lệ.');
        $detail = $this->dashboard->orderDetail($orderId);
        if ($detail === null) {
            return null;
        }

        $settings = $this->dashboard->settingValues();
        $transferTemplate = $settings['bank_transfer_content'] ?? $orderId;

        $detail['allowed_next_statuses'] = OrderService::allowedTransitionsFor((string) $detail['status']);
        $detail['store'] = [
            'store_name' => $settings['store_name'] ?? '',
            'store_phone' => $settings['store_phone'] ?? '',
            'zalo_link' => $settings['zalo_link'] ?? '',
        ];
        $detail['payment'] = [
            'bank_name' => $settings['bank_name'] ?? '',
            'bank_account_number' => $settings['bank_account_number'] ?? '',
            'bank_account_holder' => $settings['bank_account_holder'] ?? '',
            'bank_transfer_content' => str_replace('{order_id}', $orderId, $transferTemplate),
            'bank_qr_image_path' => $settings['bank_qr_image_path'] ?? '',
        ];

        return $detail;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function products(array $filters = []): array
    {
        $clean = $this->cleanListFilters($filters);
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '') {
            $clean['is_active'] = ((int) $filters['is_active']) === 1 ? 1 : 0;
        }

        return $this->paginated(
            $this->dashboard->listProducts($clean),
            $this->dashboard->countProducts($clean),
            $clean
        ) + ['categories' => $this->dashboard->categories()];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function inventory(array $filters = []): array
    {
        return $this->dashboard->inventory([
            'limit' => $this->cleanLimit($filters['limit'] ?? 100),
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function purchasePlans(array $filters = []): array
    {
        $clean = $this->cleanListFilters($filters);
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            if (!in_array($status, self::PLAN_STATUSES, true)) {
                throw new AppException('Trạng thái PO không hợp lệ.', 422);
            }
            $clean['status'] = $status;
        }

        return $this->paginated(
            $this->dashboard->listPurchasePlans($clean),
            $this->dashboard->countPurchasePlans($clean),
            $clean
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->dashboard->settings();
    }

    public function updateProductActive(string $productId, bool $isActive): void
    {
        $this->dashboard->updateProductActive($this->cleanId($productId, 'Mã sản phẩm không hợp lệ.'), $isActive);
    }

    public function updateSetting(string $key, string $value): void
    {
        $key = trim($key);
        if ($key === '' || preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $key) !== 1) {
            throw new AppException('Mã setting không hợp lệ.', 422);
        }
        if (mb_strlen($value, 'UTF-8') > 1000) {
            throw new AppException('Giá trị setting quá dài.', 422);
        }

        $this->dashboard->updateSetting($key, $value);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function cleanListFilters(array $filters): array
    {
        $page = max(1, (int) ($filters['p'] ?? $filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? $filters['limit'] ?? 50);
        if (!in_array($perPage, [20, 50, 100], true)) {
            $perPage = 50;
        }
        $clean = [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            if (mb_strlen($query, 'UTF-8') > 80 || preg_match('/[\x00-\x1F\x7F]/', $query) === 1) {
                throw new AppException('Từ khóa tìm kiếm không hợp lệ.', 422);
            }
            $clean['q'] = $query;
        }

        return $clean;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function paginated(array $items, int $total, array $filters): array
    {
        $perPage = (int) $filters['per_page'];
        return [
            'items' => $items,
            'pagination' => [
                'page' => (int) $filters['page'],
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
            ],
            'filters' => $filters,
        ];
    }

    private function cleanLimit(mixed $value): int
    {
        $limit = (int) $value;
        if ($limit < 1) {
            return 50;
        }

        return min($limit, 200);
    }

    private function cleanId(string $value, string $message): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]{1,80}$/', $value) !== 1) {
            throw new AppException($message, 422);
        }

        return $value;
    }

    private function cleanDate(string $date, string $message): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new AppException($message, 422);
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));
        if (!checkdate($month, $day, $year)) {
            throw new AppException($message, 422);
        }

        return $date;
    }
}
