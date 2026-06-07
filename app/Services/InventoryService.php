<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\InventoryRepository;
use DacSanNhaDan\Support\Formatter;
use DacSanNhaDan\Support\IdGenerator;

final class InventoryService
{
    public function __construct(private InventoryRepository $inventory)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function completeOrderFefo(string $orderId, array $items): void
    {
        if ($items === []) {
            throw new AppException('Đơn hàng không có dòng sản phẩm.', 422);
        }

        foreach ($items as $item) {
            $this->deductOrderItemFefo($orderId, $item);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertMovement(array $data): string
    {
        return $this->inventory->createMovement($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function receiveStock(array $data): string
    {
        $qtyBase = round((float) ($data['qty_base'] ?? 0), 3);
        $qtyUom = round((float) ($data['qty_uom'] ?? 0), 3);
        $conversion = round((float) ($data['conversion_to_base_snapshot'] ?? 1), 3);

        if ($qtyBase <= 0 || $qtyUom <= 0 || $conversion <= 0) {
            throw new AppException('Số lượng nhập kho không hợp lệ.', 422);
        }

        $lotId = $this->uniqueLotId();
        $productId = (string) $data['product_id'];
        $sourceLocation = (string) ($data['source_location'] ?? 'Unknown');
        $uomId = (string) ($data['uom_id'] ?? '');
        $receivedDate = (string) ($data['received_date'] ?? date('Y-m-d'));
        $costPerBase = (int) round((float) ($data['cost_per_base_unit_vnd'] ?? 0));

        $this->inventory->createLot([
            'lot_id' => $lotId,
            'product_id' => $productId,
            'source_location' => $sourceLocation,
            'qty_base_on_hand' => $qtyBase,
            'qty_base_reserved' => 0,
            'received_date' => $receivedDate,
            'expiry_date' => $data['expiry_date'] ?? null,
            'supplier_name' => $data['supplier_name'] ?? null,
            'cost_per_base_unit_vnd' => $costPerBase,
            'received_uom_id' => $uomId === '' ? null : $uomId,
            'received_qty_uom' => $qtyUom,
            'conversion_to_base_snapshot' => $conversion,
            'note' => $data['note'] ?? null,
        ]);

        $this->insertMovement([
            'movement_type' => 'IN',
            'ref_type' => 'PLAN',
            'ref_id' => $data['plan_id'] ?? null,
            'lot_id' => $lotId,
            'product_id' => $productId,
            'source_location' => $sourceLocation,
            'uom_id' => $uomId === '' ? null : $uomId,
            'qty_uom' => $qtyUom,
            'conversion_to_base_snapshot' => $conversion,
            'qty_base' => $qtyBase,
            'cost_per_base_unit_vnd' => $costPerBase,
            'note' => $data['movement_note'] ?? ('Nhập kho từ PO ' . (string) ($data['plan_id'] ?? '')),
        ]);

        return $lotId;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function deductOrderItemFefo(string $orderId, array $item): void
    {
        $remaining = round((float) $item['qty_base'], 3);
        $productId = (string) $item['product_id'];
        $sourceLocation = (string) ($item['source_location'] ?? 'Unknown');
        $lots = $this->lotsForFefo($productId, $sourceLocation);

        foreach ($lots as $lot) {
            if ($remaining <= 0.0001) {
                break;
            }

            $available = round(
                (float) $lot['qty_base_on_hand'] - (float) $lot['qty_base_reserved'],
                3
            );

            if ($available <= 0) {
                continue;
            }

            $take = min($remaining, $available);
            $take = round($take, 3);

            $this->inventory->deductLot((string) $lot['lot_id'], $take);
            $qtyUomOut = round($take / max((float) $item['conversion_to_base_snapshot'], 0.001), 3);
            $movementId = $this->insertMovement([
                'movement_type' => 'OUT',
                'ref_type' => 'ORDER',
                'ref_id' => $orderId,
                'lot_id' => (string) $lot['lot_id'],
                'product_id' => $productId,
                'source_location' => (string) $lot['source_location'],
                'uom_id' => (string) $item['uom_id'],
                'qty_uom' => $qtyUomOut,
                'conversion_to_base_snapshot' => (float) $item['conversion_to_base_snapshot'],
                'qty_base' => $take,
                'cost_per_base_unit_vnd' => (int) $lot['cost_per_base_unit_vnd'],
                'note' => 'Xuất kho FEFO cho đơn ' . $orderId . ', dòng ' . (string) $item['line_no'],
            ]);

            $this->inventory->createOrderItemAllocation([
                'order_item_id' => (int) $item['order_item_id'],
                'order_id' => $orderId,
                'lot_id' => (string) $lot['lot_id'],
                'product_id' => $productId,
                'qty_base' => $take,
                'movement_id' => $movementId,
            ]);

            $remaining = round($remaining - $take, 3);
        }

        if ($remaining > 0.0001) {
            throw new AppException(
                'Không đủ tồn kho cho ' . (string) $item['product_name_snapshot']
                . '. Thiếu ' . Formatter::decimalDisplay($remaining) . ' base unit.',
                422
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lotsForFefo(string $productId, string $sourceLocation): array
    {
        if ($sourceLocation === 'Unknown') {
            return $this->inventory->lockAvailableLotsForFefo($productId);
        }

        $sourceLots = $this->inventory->lockAvailableLotsForFefo($productId, $sourceLocation);
        $sourceLotIds = array_fill_keys(array_map(
            static fn (array $lot): string => (string) $lot['lot_id'],
            $sourceLots
        ), true);
        $allLots = $this->inventory->lockAvailableLotsForFefo($productId);

        foreach ($allLots as $lot) {
            $lotId = (string) $lot['lot_id'];
            if (!isset($sourceLotIds[$lotId])) {
                $sourceLots[] = $lot;
            }
        }

        return $sourceLots;
    }

    private function uniqueLotId(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $lotId = IdGenerator::lotId();

            if ($this->inventory->findLot($lotId) === null) {
                return $lotId;
            }
        }

        throw new AppException('Không tạo được mã lot duy nhất. Vui lòng thử lại.', 500);
    }
}
