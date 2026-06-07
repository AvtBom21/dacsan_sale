<?php

declare(strict_types=1);

namespace DacSanNhaDan\Repositories;

use DacSanNhaDan\Support\IdGenerator;
use PDO;

final class InventoryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<int, string> $productIds
     * @return array<string, array<string, mixed>>
     */
    public function summariesByProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT product_id, product_name, base_uom_label, default_source,
                    qty_base_on_hand, qty_base_reserved, qty_base_available, nearest_expiry_date
             FROM v_inventory_summary
             WHERE product_id IN ($placeholders)"
        );
        $statement->execute(array_values($productIds));

        $summaries = [];
        foreach ($statement->fetchAll() as $row) {
            $summaries[(string) $row['product_id']] = $this->formatSummary($row);
        }

        return $summaries;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function summaryForProduct(string $productId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT product_id, product_name, base_uom_label, default_source,
                    qty_base_on_hand, qty_base_reserved, qty_base_available, nearest_expiry_date
             FROM v_inventory_summary
             WHERE product_id = :product_id
             LIMIT 1'
        );
        $statement->execute(['product_id' => $productId]);

        $row = $statement->fetch();

        return $row === false ? null : $this->formatSummary($row);
    }

    public function getAvailableBaseQty(string $productId, ?string $sourceLocation = null): float
    {
        if ($sourceLocation !== null && $sourceLocation !== 'Unknown') {
            $statement = $this->pdo->prepare(
                'SELECT COALESCE(SUM(qty_base_on_hand - qty_base_reserved), 0)
                 FROM inventory_lots
                 WHERE product_id = :product_id
                   AND source_location = :source_location'
            );
            $statement->execute([
                'product_id' => $productId,
                'source_location' => $sourceLocation,
            ]);

            return (float) $statement->fetchColumn();
        }

        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(qty_base_on_hand - qty_base_reserved), 0)
             FROM inventory_lots
             WHERE product_id = :product_id'
        );
        $statement->execute(['product_id' => $productId]);

        return (float) $statement->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lockAvailableLotsForFefo(string $productId, ?string $sourceLocation = null): array
    {
        $params = ['product_id' => $productId];
        $sourceClause = '';

        if ($sourceLocation !== null && $sourceLocation !== 'Unknown') {
            $sourceClause = ' AND source_location = :source_location';
            $params['source_location'] = $sourceLocation;
        }

        $statement = $this->pdo->prepare(
            'SELECT lot_id, product_id, source_location, qty_base_on_hand,
                    qty_base_reserved, cost_per_base_unit_vnd, received_date,
                    expiry_date
             FROM inventory_lots
             WHERE product_id = :product_id
               AND (qty_base_on_hand - qty_base_reserved) > 0'
               . $sourceClause .
            ' ORDER BY expiry_date IS NULL ASC, expiry_date ASC,
                     received_date ASC, lot_id ASC
              FOR UPDATE'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function deductLot(string $lotId, float $qtyBase): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE inventory_lots
             SET qty_base_on_hand = qty_base_on_hand - :qty_base
             WHERE lot_id = :lot_id
               AND (qty_base_on_hand - qty_base_reserved) + 0.0001 >= :qty_base_check'
        );
        $statement->execute([
            'qty_base' => $qtyBase,
            'qty_base_check' => $qtyBase,
            'lot_id' => $lotId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('Không thể trừ tồn kho từ lot đã chọn.');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLot(string $lotId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM inventory_lots
             WHERE lot_id = :lot_id
             LIMIT 1'
        );
        $statement->execute(['lot_id' => $lotId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createLot(array $data): string
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO inventory_lots
              (lot_id, product_id, source_location, qty_base_on_hand, qty_base_reserved,
               received_date, expiry_date, supplier_name, cost_per_base_unit_vnd,
               received_uom_id, received_qty_uom, conversion_to_base_snapshot, note)
             VALUES
              (:lot_id, :product_id, :source_location, :qty_base_on_hand, :qty_base_reserved,
               :received_date, :expiry_date, :supplier_name, :cost_per_base_unit_vnd,
               :received_uom_id, :received_qty_uom, :conversion_to_base_snapshot, :note)'
        );
        $statement->execute([
            'lot_id' => $data['lot_id'],
            'product_id' => $data['product_id'],
            'source_location' => $data['source_location'] ?? 'Unknown',
            'qty_base_on_hand' => $data['qty_base_on_hand'],
            'qty_base_reserved' => $data['qty_base_reserved'] ?? 0,
            'received_date' => $data['received_date'],
            'expiry_date' => $data['expiry_date'] ?? null,
            'supplier_name' => $data['supplier_name'] ?? null,
            'cost_per_base_unit_vnd' => $data['cost_per_base_unit_vnd'] ?? 0,
            'received_uom_id' => $data['received_uom_id'] ?? null,
            'received_qty_uom' => $data['received_qty_uom'] ?? 0,
            'conversion_to_base_snapshot' => $data['conversion_to_base_snapshot'] ?? 1,
            'note' => $data['note'] ?? null,
        ]);

        return (string) $data['lot_id'];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createMovement(array $data): string
    {
        $movementId = (string) ($data['movement_id'] ?? IdGenerator::movementId());
        $statement = $this->pdo->prepare(
            'INSERT INTO inventory_movements
              (movement_id, movement_type, ref_type, ref_id, lot_id, product_id,
               source_location, uom_id, qty_uom, conversion_to_base_snapshot,
               qty_base, cost_per_base_unit_vnd, note)
             VALUES
              (:movement_id, :movement_type, :ref_type, :ref_id, :lot_id, :product_id,
               :source_location, :uom_id, :qty_uom, :conversion_to_base_snapshot,
               :qty_base, :cost_per_base_unit_vnd, :note)'
        );
        $statement->execute([
            'movement_id' => $movementId,
            'movement_type' => $data['movement_type'],
            'ref_type' => $data['ref_type'],
            'ref_id' => $data['ref_id'] ?? null,
            'lot_id' => $data['lot_id'] ?? null,
            'product_id' => $data['product_id'],
            'source_location' => $data['source_location'] ?? 'Unknown',
            'uom_id' => $data['uom_id'] ?? null,
            'qty_uom' => $data['qty_uom'] ?? 0,
            'conversion_to_base_snapshot' => $data['conversion_to_base_snapshot'] ?? 1,
            'qty_base' => $data['qty_base'],
            'cost_per_base_unit_vnd' => $data['cost_per_base_unit_vnd'] ?? 0,
            'note' => $data['note'] ?? null,
        ]);

        return $movementId;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createOrderItemAllocation(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO order_item_allocations
              (order_item_id, order_id, lot_id, product_id, qty_base, movement_id)
             VALUES
              (:order_item_id, :order_id, :lot_id, :product_id, :qty_base, :movement_id)'
        );
        $statement->execute([
            'order_item_id' => $data['order_item_id'],
            'order_id' => $data['order_id'],
            'lot_id' => $data['lot_id'],
            'product_id' => $data['product_id'],
            'qty_base' => $data['qty_base'],
            'movement_id' => $data['movement_id'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array{status: string, label: string}
     */
    public function stockBadge(float $availableQty): array
    {
        if ($availableQty <= 0) {
            return ['status' => 'out_of_stock', 'label' => 'Tạm hết hàng'];
        }

        if ($availableQty <= 3) {
            return ['status' => 'low_stock', 'label' => 'Sắp hết hàng'];
        }

        return ['status' => 'in_stock', 'label' => 'Còn hàng'];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatSummary(array $row): array
    {
        $available = (float) ($row['qty_base_available'] ?? 0);
        $badge = $this->stockBadge($available);

        return [
            'product_id' => (string) $row['product_id'],
            'product_name' => (string) $row['product_name'],
            'base_uom_label' => (string) ($row['base_uom_label'] ?? ''),
            'source' => (string) ($row['default_source'] ?? 'Unknown'),
            'qty_base_on_hand' => (float) ($row['qty_base_on_hand'] ?? 0),
            'qty_base_reserved' => (float) ($row['qty_base_reserved'] ?? 0),
            'qty_base_available' => $available,
            'nearest_expiry_date' => $row['nearest_expiry_date'] === null
                ? null
                : (string) $row['nearest_expiry_date'],
            'status' => $badge['status'],
            'badge' => $badge['label'],
        ];
    }
}
