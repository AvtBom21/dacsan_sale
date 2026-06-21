<?php

declare(strict_types=1);

namespace DacSanNhaDan\Repositories;

use PDO;

final class AdminDashboardRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<string, int>
     */
    public function dashboardMetrics(): array
    {
        return [
            'orders_today' => $this->count("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()"),
            'revenue_today_vnd' => $this->count(
                "SELECT COALESCE(SUM(total_vnd), 0)
                 FROM orders
                 WHERE DATE(created_at) = CURDATE()
                   AND STATUS <> 'cancelled'"
            ),
            'pending_orders' => $this->count(
                "SELECT COUNT(*)
                 FROM orders
                 WHERE STATUS IN ('new','confirmed','ordered','received','ready')"
            ),
            'low_stock_count' => $this->count(
                'SELECT COUNT(*)
                 FROM v_inventory_summary
                 WHERE qty_base_available > 0
                   AND qty_base_available <= 3'
            ),
            'expiring_lots_count' => $this->count(
                'SELECT COUNT(*)
                 FROM inventory_lots
                 WHERE expiry_date IS NOT NULL
                   AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                   AND (qty_base_on_hand - qty_base_reserved) > 0'
            ),
            'open_purchase_plans' => $this->count(
                "SELECT COUNT(*)
                 FROM purchase_plans
                 WHERE STATUS IN ('draft','ordered','partial_received')"
            ),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listOrders(array $filters = []): array
    {
        $where = [];
        $params = [];
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            $where[] = 'o.STATUS = :status';
            $params['status'] = $status;
        }

        $dateFrom = (string) ($filters['date_from'] ?? '');
        if ($dateFrom !== '') {
            $where[] = 'DATE(o.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = (string) ($filters['date_to'] ?? '');
        if ($dateTo !== '') {
            $where[] = 'DATE(o.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $search = (string) ($filters['q'] ?? '');
        if ($search !== '') {
            $where[] = '(o.order_id LIKE :q_order OR o.customer_name LIKE :q_name OR o.customer_phone LIKE :q_phone)';
            $params['q_order'] = '%' . $search . '%';
            $params['q_name'] = '%' . $search . '%';
            $params['q_phone'] = '%' . $search . '%';
        }

        $limit = $this->limit($filters['per_page'] ?? $filters['limit'] ?? 50);
        $offset = $this->offset($filters['offset'] ?? 0);
        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);
        $statement = $this->pdo->prepare(
            "SELECT o.order_id, o.created_at, o.STATUS AS status, o.customer_name,
                    o.customer_phone, o.receive_date, o.shipping_method,
                    o.total_vnd, o.source_summary,
                    (
                        SELECT COUNT(*)
                        FROM order_items oi
                        WHERE oi.order_id = o.order_id
                          AND oi.planned_plan_id IS NULL
                    ) AS unplanned_item_count,
                    (
                        SELECT GROUP_CONCAT(
                            DISTINCT CONCAT(ppo.plan_id, '|', pp.STATUS)
                            ORDER BY ppo.plan_id
                            SEPARATOR ','
                        )
                        FROM purchase_plan_orders ppo
                        JOIN purchase_plans pp ON pp.plan_id = ppo.plan_id
                        WHERE ppo.order_id = o.order_id
                          AND pp.STATUS <> 'cancelled'
                    ) AS linked_plans
             FROM orders o
             WHERE $whereSql
             ORDER BY o.created_at DESC, o.order_id DESC
             LIMIT $limit OFFSET $offset"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $filters */
    public function countOrders(array $filters = []): int
    {
        [$whereSql, $params] = $this->orderFilterSql($filters);
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM orders o WHERE $whereSql");
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function orderDetail(string $orderId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT order_id, customer_id, created_at, STATUS AS status, customer_name,
                    customer_phone, customer_address, receive_date, note, shipping_method,
                    shipping_zone_id, shipping_fee_vnd, subtotal_vnd, total_vnd,
                    source_summary, updated_at
             FROM orders
             WHERE order_id = :order_id
             LIMIT 1'
        );
        $statement->execute(['order_id' => $orderId]);
        $order = $statement->fetch();

        if ($order === false) {
            return null;
        }

        $items = $this->pdo->prepare(
            'SELECT *
             FROM order_items
             WHERE order_id = :order_id
             ORDER BY line_no'
        );
        $items->execute(['order_id' => $orderId]);

        $allocations = $this->pdo->prepare(
            'SELECT *
             FROM order_item_allocations
             WHERE order_id = :order_id
             ORDER BY allocation_id'
        );
        $allocations->execute(['order_id' => $orderId]);

        $movements = $this->pdo->prepare(
            "SELECT *
             FROM inventory_movements
             WHERE ref_type = 'ORDER'
               AND ref_id = :order_id
             ORDER BY created_at, movement_id"
        );
        $movements->execute(['order_id' => $orderId]);

        $order['items'] = $items->fetchAll();
        $order['allocations'] = $allocations->fetchAll();
        $order['movements'] = $movements->fetchAll();

        return $order;
    }

    /**
     * @return array<string, string>
     */
    public function settingValues(): array
    {
        $settings = [];
        $statement = $this->pdo->query(
            'SELECT setting_key, setting_value
             FROM settings
             ORDER BY setting_key'
        );

        foreach ($statement->fetchAll() as $row) {
            $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }

        return $settings;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listProducts(array $filters = []): array
    {
        $where = [];
        $params = [];
        $search = (string) ($filters['q'] ?? '');
        if ($search !== '') {
            $where[] = '(p.product_id LIKE :q_id OR p.product_name LIKE :q_name OR p.category_label LIKE :q_category)';
            $params['q_id'] = '%' . $search . '%';
            $params['q_name'] = '%' . $search . '%';
            $params['q_category'] = '%' . $search . '%';
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '') {
            $where[] = 'p.is_active = :is_active';
            $params['is_active'] = (int) $filters['is_active'];
        }

        $limit = $this->limit($filters['per_page'] ?? $filters['limit'] ?? 50);
        $offset = $this->offset($filters['offset'] ?? 0);
        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);
        $statement = $this->pdo->prepare(
            "SELECT p.product_id, p.product_name, p.product_slug, p.category_id,
                    p.category_label, p.default_source, p.base_uom_label,
                    p.shelf_life_value, p.shelf_life_unit, p.is_active,
                    COALESCE(u.uom_count, 0) AS uom_count,
                    COALESCE(i.image_count, 0) AS image_count,
                    p.updated_at
             FROM products p
             LEFT JOIN (
                SELECT product_id, COUNT(*) AS uom_count
                FROM product_uoms
                GROUP BY product_id
             ) u ON u.product_id = p.product_id
             LEFT JOIN (
                SELECT product_id, COUNT(*) AS image_count
                FROM product_images
                GROUP BY product_id
             ) i ON i.product_id = p.product_id
             WHERE $whereSql
             ORDER BY p.is_active DESC, p.category_label, p.product_name
             LIMIT $limit OFFSET $offset"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $filters */
    public function countProducts(array $filters = []): int
    {
        $where = [];
        $params = [];
        $search = (string) ($filters['q'] ?? '');
        if ($search !== '') {
            $where[] = '(p.product_id LIKE :q_id OR p.product_name LIKE :q_name OR p.category_label LIKE :q_category)';
            $params['q_id'] = '%' . $search . '%';
            $params['q_name'] = '%' . $search . '%';
            $params['q_category'] = '%' . $search . '%';
        }
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '') {
            $where[] = 'p.is_active = :is_active';
            $params['is_active'] = (int) $filters['is_active'];
        }
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM products p WHERE ' . ($where === [] ? '1=1' : implode(' AND ', $where))
        );
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->pdo->query(
            'SELECT category_id, category_name, category_slug, sort_order, is_active
             FROM categories
             ORDER BY sort_order, category_name'
        )->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function inventory(array $filters = []): array
    {
        $limit = $this->limit($filters['per_page'] ?? $filters['limit'] ?? 50);
        $offset = $this->offset($filters['offset'] ?? 0);
        $summary = $this->pdo->query(
            'SELECT product_id, product_name, base_uom_label, default_source,
                    qty_base_on_hand, qty_base_reserved, qty_base_available,
                    nearest_expiry_date
             FROM v_inventory_summary
             ORDER BY qty_base_available ASC, product_name
             LIMIT ' . $limit
        )->fetchAll();

        $lots = $this->pdo->query(
            'SELECT l.lot_id, l.product_id, p.product_name, l.source_location,
                    l.qty_base_on_hand, l.qty_base_reserved, l.received_date,
                    l.expiry_date, l.supplier_name, l.cost_per_base_unit_vnd
             FROM inventory_lots l
             JOIN products p ON p.product_id = l.product_id
             ORDER BY l.expiry_date IS NULL ASC, l.expiry_date ASC,
                      l.received_date ASC, l.lot_id ASC
             LIMIT ' . $limit
        )->fetchAll();

        $movements = $this->pdo->query(
            'SELECT movement_id, created_at, movement_type, ref_type, ref_id,
                    lot_id, product_id, source_location, qty_uom, qty_base, note
             FROM inventory_movements
             ORDER BY created_at DESC, movement_id DESC
             LIMIT ' . $limit
        )->fetchAll();

        $purchasableUoms = $this->pdo->query(
            'SELECT p.product_id, p.product_name, p.default_source,
                    u.uom_id, u.uom_label, u.conversion_to_base,
                    u.cost_price_vnd
             FROM products p
             JOIN product_uoms u ON u.product_id = p.product_id
             WHERE p.is_active = 1
               AND u.is_active = 1
               AND u.is_purchasable = 1
             ORDER BY p.product_name, u.sort_order, u.uom_label'
        )->fetchAll();

        return [
            'summary' => $summary,
            'lots' => $lots,
            'movements' => $movements,
            'purchasable_uoms' => $purchasableUoms,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listPurchasePlans(array $filters = []): array
    {
        $where = [];
        $params = [];
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            $where[] = 'pp.STATUS = :status';
            $params['status'] = $status;
        }

        $limit = $this->limit($filters['per_page'] ?? $filters['limit'] ?? 50);
        $offset = $this->offset($filters['offset'] ?? 0);
        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);
        $statement = $this->pdo->prepare(
            "SELECT pp.plan_id, pp.created_at, pp.order_from_date, pp.order_to_date,
                    pp.STATUS AS status, pp.supplier_scope, pp.note,
                    COUNT(DISTINCT pi.plan_item_id) AS item_count,
                    COUNT(DISTINCT ppo.order_id) AS order_count,
                    COALESCE(SUM(pi.qty_planned_base), 0) AS qty_planned_base,
                    COALESCE(SUM(pi.qty_received_base), 0) AS qty_received_base
             FROM purchase_plans pp
             LEFT JOIN plan_items pi ON pi.plan_id = pp.plan_id
             LEFT JOIN purchase_plan_orders ppo ON ppo.plan_id = pp.plan_id
             WHERE $whereSql
             GROUP BY pp.plan_id
             ORDER BY pp.created_at DESC, pp.plan_id DESC
             LIMIT $limit OFFSET $offset"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $filters */
    public function countPurchasePlans(array $filters = []): int
    {
        $status = (string) ($filters['status'] ?? '');
        $sql = 'SELECT COUNT(*) FROM purchase_plans pp';
        $params = [];
        if ($status !== '') {
            $sql .= ' WHERE pp.STATUS = :status';
            $params['status'] = $status;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function settings(): array
    {
        return [
            'settings' => $this->pdo->query(
                'SELECT setting_key, setting_value, note, updated_at
                 FROM settings
                 ORDER BY setting_key'
            )->fetchAll(),
            'shipping_zones' => $this->pdo->query(
                'SELECT zone_id, zone_name, fee_vnd, is_default, is_active, updated_at
                 FROM shipping_zones
                 ORDER BY is_default DESC, zone_name'
            )->fetchAll(),
        ];
    }

    public function updateProductActive(string $productId, bool $isActive): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE products
             SET is_active = :is_active
             WHERE product_id = :product_id'
        );
        $statement->execute([
            'is_active' => $isActive ? 1 : 0,
            'product_id' => $productId,
        ]);
    }

    public function updateSetting(string $key, string $value): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE settings
             SET setting_value = :setting_value
             WHERE setting_key = :setting_key'
        );
        $statement->execute([
            'setting_value' => $value,
            'setting_key' => $key,
        ]);
    }

    public function count(string $sql): int
    {
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    private function limit(mixed $value): int
    {
        $limit = (int) $value;
        if ($limit < 1) {
            return 50;
        }

        return min($limit, 100);
    }

    private function offset(mixed $value): int
    {
        return max(0, (int) $value);
    }

    /** @param array<string, mixed> $filters
     *  @return array{0: string, 1: array<string, mixed>}
     */
    private function orderFilterSql(array $filters): array
    {
        $where = [];
        $params = [];
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'o.STATUS = :status';
            $params['status'] = (string) $filters['status'];
        }
        if (($filters['date_from'] ?? '') !== '') {
            $where[] = 'DATE(o.created_at) >= :date_from';
            $params['date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '') {
            $where[] = 'DATE(o.created_at) <= :date_to';
            $params['date_to'] = (string) $filters['date_to'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(o.order_id LIKE :q_order OR o.customer_name LIKE :q_name OR o.customer_phone LIKE :q_phone)';
            $params['q_order'] = '%' . (string) $filters['q'] . '%';
            $params['q_name'] = '%' . (string) $filters['q'] . '%';
            $params['q_phone'] = '%' . (string) $filters['q'] . '%';
        }
        return [$where === [] ? '1=1' : implode(' AND ', $where), $params];
    }
}
