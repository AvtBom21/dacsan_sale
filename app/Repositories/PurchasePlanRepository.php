<?php

declare(strict_types=1);

namespace DacSanNhaDan\Repositories;

use PDO;

final class PurchasePlanRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<int, string> $orderIds
     * @return array<int, array<string, mixed>>
     */
    public function eligibleOrders(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT o.order_id, o.created_at, STATUS AS status, o.customer_name,
                    o.customer_phone, o.total_vnd, o.source_summary
             FROM orders o
             WHERE o.order_id IN ($placeholders)
               AND o.STATUS = 'confirmed'
             ORDER BY o.created_at, o.order_id"
        );
        $statement->execute($orderIds);

        return $statement->fetchAll();
    }

    /**
     * @param array<int, string> $orderIds
     * @return array<int, array<string, mixed>>
     */
    public function eligibleOrderItemRows(array $orderIds, bool $forUpdate = false): array
    {
        if ($orderIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $sql = "SELECT oi.order_item_id, oi.order_id, oi.product_id,
                       oi.product_name_snapshot, oi.uom_id, oi.uom_label_snapshot,
                       oi.source_location, oi.qty_uom, oi.qty_base,
                       oi.conversion_to_base_snapshot, u.cost_price_vnd,
                       DATE(o.created_at) AS order_date
                FROM orders o
                JOIN order_items oi ON oi.order_id = o.order_id
                JOIN product_uoms u ON u.uom_id = oi.uom_id
                WHERE o.order_id IN ($placeholders)
                  AND o.STATUS = 'confirmed'
                  AND oi.planned_plan_id IS NULL
                ORDER BY oi.source_location, oi.product_id, oi.uom_id, oi.order_id, oi.line_no";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($orderIds);

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createPlan(array $data): string
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO purchase_plans
              (plan_id, order_from_date, order_to_date, STATUS, supplier_scope, note, created_by)
             VALUES
              (:plan_id, :order_from_date, :order_to_date, :status, :supplier_scope, :note, :created_by)'
        );
        $statement->execute([
            'plan_id' => $data['plan_id'],
            'order_from_date' => $data['order_from_date'],
            'order_to_date' => $data['order_to_date'],
            'status' => $data['status'] ?? 'ordered',
            'supplier_scope' => $data['supplier_scope'],
            'note' => $data['note'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        return (string) $data['plan_id'];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function createPlanItems(string $planId, array $items): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO plan_items
              (plan_id, product_id, product_name_snapshot, uom_id, uom_label_snapshot,
               source_location, qty_needed_uom, qty_planned_uom,
               conversion_to_base_snapshot, qty_needed_base, qty_planned_base,
               cost_per_uom_vnd, note)
             VALUES
              (:plan_id, :product_id, :product_name_snapshot, :uom_id, :uom_label_snapshot,
               :source_location, :qty_needed_uom, :qty_planned_uom,
               :conversion_to_base_snapshot, :qty_needed_base, :qty_planned_base,
               :cost_per_uom_vnd, :note)'
        );

        foreach ($items as $item) {
            $statement->execute([
                'plan_id' => $planId,
                'product_id' => $item['product_id'],
                'product_name_snapshot' => $item['product_name_snapshot'],
                'uom_id' => $item['uom_id'],
                'uom_label_snapshot' => $item['uom_label_snapshot'],
                'source_location' => $item['source_location'],
                'qty_needed_uom' => $item['qty_needed_uom'],
                'qty_planned_uom' => $item['qty_planned_uom'],
                'conversion_to_base_snapshot' => $item['conversion_to_base_snapshot'],
                'qty_needed_base' => $item['qty_needed_base'],
                'qty_planned_base' => $item['qty_planned_base'],
                'cost_per_uom_vnd' => $item['cost_per_uom_vnd'],
                'note' => $item['note'] ?? null,
            ]);
        }
    }

    /**
     * @param array<int, string> $orderIds
     */
    public function linkOrders(string $planId, array $orderIds): void
    {
        $statement = $this->pdo->prepare(
            'INSERT IGNORE INTO purchase_plan_orders (plan_id, order_id)
             VALUES (:plan_id, :order_id)'
        );

        foreach ($orderIds as $orderId) {
            $statement->execute([
                'plan_id' => $planId,
                'order_id' => $orderId,
            ]);
        }
    }

    /**
     * @param array<int, int> $orderItemIds
     */
    public function stampOrderItems(string $planId, array $orderItemIds): void
    {
        if ($orderItemIds === []) {
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE order_items
             SET planned_plan_id = :plan_id,
                 planned_at = NOW()
             WHERE order_item_id = :order_item_id
               AND planned_plan_id IS NULL'
        );

        foreach ($orderItemIds as $orderItemId) {
            $statement->execute([
                'plan_id' => $planId,
                'order_item_id' => $orderItemId,
            ]);

            if ($statement->rowCount() !== 1) {
                throw new \RuntimeException('Order item đã được gom PO bởi phiên khác.');
            }
        }
    }

    /**
     * @param array<int, string> $orderIds
     */
    public function markOrdersOrderedIfFullyPlanned(array $orderIds): void
    {
        if ($orderIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $statement = $this->pdo->prepare(
            "UPDATE orders o
             SET o.STATUS = 'ordered'
             WHERE o.order_id IN ($placeholders)
               AND o.STATUS = 'confirmed'
               AND NOT EXISTS (
                   SELECT 1
                   FROM order_items oi
                   WHERE oi.order_id = o.order_id
                     AND oi.planned_plan_id IS NULL
               )"
        );
        $statement->execute($orderIds);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPlan(string $planId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT plan_id, created_at, order_from_date, order_to_date,
                    STATUS AS status, supplier_scope, note, created_by, updated_at
             FROM purchase_plans
             WHERE plan_id = :plan_id
             LIMIT 1'
        );
        $statement->execute(['plan_id' => $planId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPlanForUpdate(string $planId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT plan_id, created_at, order_from_date, order_to_date,
                    STATUS AS status, supplier_scope, note, created_by, updated_at
             FROM purchase_plans
             WHERE plan_id = :plan_id
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute(['plan_id' => $planId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPlanItems(string $planId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM plan_items
             WHERE plan_id = :plan_id
             ORDER BY source_location, product_name_snapshot, uom_label_snapshot, plan_item_id'
        );
        $statement->execute(['plan_id' => $planId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPlanItemsForUpdate(string $planId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM plan_items
             WHERE plan_id = :plan_id
             ORDER BY source_location, product_name_snapshot, uom_label_snapshot, plan_item_id
             FOR UPDATE'
        );
        $statement->execute(['plan_id' => $planId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPlanItemForUpdate(string $planId, int $planItemId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM plan_items
             WHERE plan_id = :plan_id
               AND plan_item_id = :plan_item_id
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute([
            'plan_id' => $planId,
            'plan_item_id' => $planItemId,
        ]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPlanOrders(string $planId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT o.order_id, o.created_at, o.STATUS AS status, o.customer_name,
                    o.customer_phone, o.total_vnd, o.source_summary
             FROM purchase_plan_orders ppo
             JOIN orders o ON o.order_id = ppo.order_id
             WHERE ppo.plan_id = :plan_id
             ORDER BY o.created_at, o.order_id"
        );
        $statement->execute(['plan_id' => $planId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReceipts(string $planId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT receipt_id, plan_id, received_at, received_by, note
             FROM purchase_plan_receipts
             WHERE plan_id = :plan_id
             ORDER BY received_at, receipt_id'
        );
        $statement->execute(['plan_id' => $planId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReceiptItems(string $planId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT receipt_item_id, receipt_id, plan_id, plan_item_id, lot_id,
                    product_id, uom_id, qty_received_uom,
                    conversion_to_base_snapshot, qty_received_base,
                    cost_per_uom_vnd, created_at
             FROM purchase_plan_receipt_items
             WHERE plan_id = :plan_id
             ORDER BY receipt_item_id'
        );
        $statement->execute(['plan_id' => $planId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPlanDetail(string $planId): ?array
    {
        $plan = $this->findPlan($planId);

        if ($plan === null) {
            return null;
        }

        $plan['items'] = $this->getPlanItems($planId);
        $plan['orders'] = $this->getPlanOrders($planId);
        $plan['receipts'] = $this->getReceipts($planId);
        $plan['receipt_items'] = $this->getReceiptItems($planId);

        return $plan;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findReceipt(string $receiptId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT receipt_id, plan_id, received_at, received_by, note
             FROM purchase_plan_receipts
             WHERE receipt_id = :receipt_id
             LIMIT 1'
        );
        $statement->execute(['receipt_id' => $receiptId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createReceipt(array $data): string
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO purchase_plan_receipts
              (receipt_id, plan_id, received_at, received_by, note)
             VALUES
              (:receipt_id, :plan_id, :received_at, :received_by, :note)'
        );
        $statement->execute([
            'receipt_id' => $data['receipt_id'],
            'plan_id' => $data['plan_id'],
            'received_at' => $data['received_at'] ?? date('Y-m-d H:i:s'),
            'received_by' => $data['received_by'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        return (string) $data['receipt_id'];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createReceiptItem(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO purchase_plan_receipt_items
              (receipt_id, plan_id, plan_item_id, lot_id, product_id, uom_id,
               qty_received_uom, conversion_to_base_snapshot, qty_received_base,
               cost_per_uom_vnd)
             VALUES
              (:receipt_id, :plan_id, :plan_item_id, :lot_id, :product_id, :uom_id,
               :qty_received_uom, :conversion_to_base_snapshot, :qty_received_base,
               :cost_per_uom_vnd)'
        );
        $statement->execute([
            'receipt_id' => $data['receipt_id'],
            'plan_id' => $data['plan_id'],
            'plan_item_id' => $data['plan_item_id'],
            'lot_id' => $data['lot_id'],
            'product_id' => $data['product_id'],
            'uom_id' => $data['uom_id'],
            'qty_received_uom' => $data['qty_received_uom'],
            'conversion_to_base_snapshot' => $data['conversion_to_base_snapshot'],
            'qty_received_base' => $data['qty_received_base'],
            'cost_per_uom_vnd' => $data['cost_per_uom_vnd'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function incrementPlanItemReceived(int $planItemId, float $qtyUom, float $qtyBase): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE plan_items
             SET qty_received_uom = qty_received_uom + :qty_uom,
                 qty_received_base = qty_received_base + :qty_base
             WHERE plan_item_id = :plan_item_id'
        );
        $statement->execute([
            'qty_uom' => $qtyUom,
            'qty_base' => $qtyBase,
            'plan_item_id' => $planItemId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('Không cập nhật được số lượng đã nhận của PO item.');
        }
    }

    public function updatePlanStatus(string $planId, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE purchase_plans
             SET STATUS = :status
             WHERE plan_id = :plan_id'
        );
        $statement->execute([
            'status' => $status,
            'plan_id' => $planId,
        ]);
    }

    public function markLinkedOrdersReceived(string $planId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE orders o
             JOIN purchase_plan_orders ppo ON ppo.order_id = o.order_id
             SET o.STATUS = 'received'
             WHERE ppo.plan_id = :plan_id
               AND o.STATUS = 'ordered'"
        );
        $statement->execute(['plan_id' => $planId]);
    }

    public function receiptCount(string $planId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM purchase_plan_receipts
             WHERE plan_id = :plan_id'
        );
        $statement->execute(['plan_id' => $planId]);

        return (int) $statement->fetchColumn();
    }

    public function cancelPlan(string $planId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE purchase_plans
             SET STATUS = 'cancelled'
             WHERE plan_id = :plan_id"
        );
        $statement->execute(['plan_id' => $planId]);
    }

    public function unstampOrderItems(string $planId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE order_items
             SET planned_plan_id = NULL,
                 planned_at = NULL
             WHERE planned_plan_id = :plan_id'
        );
        $statement->execute(['plan_id' => $planId]);
    }

    /**
     * @param array<int, string> $orderIds
     */
    public function markOrdersConfirmedIfUnplanned(array $orderIds): void
    {
        if ($orderIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $statement = $this->pdo->prepare(
            "UPDATE orders o
             SET o.STATUS = 'confirmed'
             WHERE o.order_id IN ($placeholders)
               AND o.STATUS = 'ordered'
               AND NOT EXISTS (
                   SELECT 1
                   FROM order_items oi
                   WHERE oi.order_id = o.order_id
                     AND oi.planned_plan_id IS NOT NULL
               )"
        );
        $statement->execute($orderIds);
    }
}
