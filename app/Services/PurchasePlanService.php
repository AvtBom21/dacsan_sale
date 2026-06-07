<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\PurchasePlanRepository;
use DacSanNhaDan\Support\Formatter;
use DacSanNhaDan\Support\IdGenerator;
use PDO;
use Throwable;

final class PurchasePlanService
{
    public function __construct(
        private PDO $pdo,
        private PurchasePlanRepository $plans,
        private OrderService $orders,
        private ?InventoryService $inventory = null
    ) {
    }

    /**
     * @param array<int, string> $orderIds
     * @param array<string, mixed> $options
     */
    public function createFromSelectedOrders(array $orderIds, array $options = []): string
    {
        $orderIds = $this->normalizeOrderIds($orderIds);
        if ($orderIds === []) {
            throw new AppException('Vui lòng chọn ít nhất một đơn hàng để tạo PO.', 422);
        }

        $note = trim((string) ($options['note'] ?? ''));

        $this->pdo->beginTransaction();
        try {
            $rows = $this->plans->eligibleOrderItemRows($orderIds, true);
            if ($rows === []) {
                throw new AppException('Các đơn đã chọn không còn item confirmed chưa gom PO.', 422);
            }

            $actualOrderIds = array_values(array_unique(array_map(
                static fn (array $row): string => (string) $row['order_id'],
                $rows
            )));
            if (count($actualOrderIds) !== count($orderIds)) {
                throw new AppException('Một số đơn đã chọn không ở trạng thái confirmed hoặc đã được gom PO.', 422);
            }

            $planId = $this->uniquePlanId();
            $groups = $this->groupRows($rows);
            $dates = array_column($rows, 'order_date');

            $this->plans->createPlan([
                'plan_id' => $planId,
                'order_from_date' => min($dates),
                'order_to_date' => max($dates),
                'status' => 'ordered',
                'supplier_scope' => $this->sourceScope(array_column($rows, 'source_location')),
                'note' => $note === '' ? null : $note,
                'created_by' => null,
            ]);
            $this->plans->createPlanItems($planId, $groups);
            $this->plans->stampOrderItems($planId, array_map(
                static fn (array $row): int => (int) $row['order_item_id'],
                $rows
            ));
            $this->plans->linkOrders($planId, $actualOrderIds);
            $this->plans->markOrdersOrderedIfFullyPlanned($actualOrderIds);

            $this->pdo->commit();

            return $planId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<int, string> $orderIds
     * @return array<string, mixed>
     */
    public function previewFromSelectedOrders(array $orderIds): array
    {
        $orderIds = $this->normalizeOrderIds($orderIds);
        if ($orderIds === []) {
            throw new AppException('Vui lòng chọn ít nhất một đơn hàng để xem trước PO.', 422);
        }

        $orders = $this->plans->eligibleOrders($orderIds);
        $rows = $this->plans->eligibleOrderItemRows($orderIds);

        if ($rows === []) {
            throw new AppException('Các đơn đã chọn không còn item confirmed chưa gom PO.', 422);
        }

        return [
            'orders' => $orders,
            'items' => $this->groupRows($rows),
            'supplier_scope' => $this->sourceScope(array_column($rows, 'source_location')),
            'order_count' => count($orders),
        ];
    }

    public function copyPurchasePlanText(string $planId): string
    {
        $detail = $this->getDetail($planId);

        if ($detail === null) {
            throw new AppException('Không tìm thấy PO.', 404);
        }

        $lines = [
            'PHIẾU ĐẶT HÀNG ' . $planId,
            'Trạng thái: ' . $this->statusLabel((string) $detail['status']),
            'Nguồn hàng: ' . (string) $detail['supplier_scope'],
            'Khoảng đơn: ' . (string) $detail['order_from_date'] . ' đến ' . (string) $detail['order_to_date'],
            '',
            'Danh sách cần đặt:',
        ];

        foreach ($detail['items'] as $item) {
            $lines[] = '- ' . (string) $item['source_location']
                . ' | ' . (string) $item['product_name_snapshot']
                . ' | ' . Formatter::decimalDisplay($item['qty_planned_uom'])
                . ' ' . (string) $item['uom_label_snapshot'];
        }

        return implode(PHP_EOL, $lines);
    }

    public function cancelPlan(string $planId): void
    {
        $this->pdo->beginTransaction();

        try {
            $plan = $this->plans->findPlanForUpdate($planId);
            if ($plan === null) {
                throw new AppException('Không tìm thấy PO.', 404);
            }

            if (in_array((string) $plan['status'], ['partial_received', 'received', 'closed'], true)) {
                throw new AppException('PO đã nhận hàng hoặc đã khóa nên không thể hủy.', 422);
            }

            if ($this->plans->receiptCount($planId) > 0) {
                throw new AppException('PO đã có phiếu nhận hàng nên không thể hủy.', 422);
            }

            $linkedOrders = $this->plans->getPlanOrders($planId);
            $orderIds = array_map(
                static fn (array $order): string => (string) $order['order_id'],
                $linkedOrders
            );
            $this->plans->unstampOrderItems($planId);
            $this->plans->markOrdersConfirmedIfUnplanned($orderIds);
            $this->plans->cancelPlan($planId);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    public function receivePlan(string $planId, array $input): string
    {
        $planId = $this->normalizePlanId($planId);
        if ($this->inventory === null) {
            throw new AppException('Dịch vụ nhập kho chưa sẵn sàng.', 500);
        }

        $rawItems = $input['items'] ?? [];
        if (!is_array($rawItems)) {
            throw new AppException('Danh sách hàng nhận không hợp lệ.', 422);
        }

        $this->pdo->beginTransaction();

        try {
            $plan = $this->plans->findPlanForUpdate($planId);
            if ($plan === null) {
                throw new AppException('Không tìm thấy PO.', 404);
            }

            $status = (string) $plan['status'];
            if (in_array($status, ['cancelled', 'closed'], true)) {
                throw new AppException('PO đã hủy hoặc đã khóa nên không thể nhận hàng.', 422);
            }
            if ($status === 'received') {
                throw new AppException('PO đã nhận đủ nên không thể nhận thêm.', 422);
            }

            $planItems = $this->plans->getPlanItemsForUpdate($planId);
            $itemsById = [];
            foreach ($planItems as $item) {
                $itemsById[(int) $item['plan_item_id']] = $item;
            }

            $receiptId = $this->uniqueReceiptId();
            $receiptNote = trim((string) ($input['note'] ?? ''));
            $this->plans->createReceipt([
                'receipt_id' => $receiptId,
                'plan_id' => $planId,
                'received_at' => $this->normalizeDateTime($input['received_at'] ?? null),
                'received_by' => null,
                'note' => $receiptNote === '' ? null : $receiptNote,
            ]);

            $processed = 0;
            foreach ($rawItems as $rawItem) {
                if (!is_array($rawItem)) {
                    throw new AppException('Dòng hàng nhận không hợp lệ.', 422);
                }

                $planItemId = (int) ($rawItem['plan_item_id'] ?? 0);
                if ($planItemId <= 0 || !isset($itemsById[$planItemId])) {
                    throw new AppException('Dòng PO nhận hàng không tồn tại.', 422);
                }

                $qtyUom = round((float) ($rawItem['qty_received_uom'] ?? 0), 3);
                if ($qtyUom <= 0) {
                    continue;
                }

                $item = $itemsById[$planItemId];
                $remainingUom = round((float) $item['qty_planned_uom'] - (float) $item['qty_received_uom'], 3);
                if ($remainingUom <= 0.0001) {
                    throw new AppException('Dòng PO đã nhận đủ.', 422);
                }
                if ($qtyUom > $remainingUom + 0.0001) {
                    throw new AppException('Số lượng nhận vượt số lượng còn lại của PO.', 422);
                }

                $conversion = round((float) $item['conversion_to_base_snapshot'], 3);
                if ($conversion <= 0) {
                    throw new AppException('Quy đổi UOM của dòng PO không hợp lệ.', 422);
                }

                $qtyBase = round($qtyUom * $conversion, 3);
                $receivedDate = $this->normalizeDate($rawItem['received_date'] ?? date('Y-m-d'), 'Ngày nhận hàng không hợp lệ.');
                $expiryDate = $this->normalizeOptionalDate($rawItem['expiry_date'] ?? null, 'Hạn dùng không hợp lệ.');
                $supplierName = trim((string) ($rawItem['supplier_name'] ?? ''));
                $lineNote = trim((string) ($rawItem['note'] ?? ''));
                $costPerUom = $this->normalizeMoney(
                    $rawItem['cost_per_uom_vnd'] ?? $item['cost_per_uom_vnd'],
                    'Giá nhập không hợp lệ.'
                );
                $costPerBase = (int) round($costPerUom / $conversion);

                $lotId = $this->inventory->receiveStock([
                    'plan_id' => $planId,
                    'product_id' => (string) $item['product_id'],
                    'source_location' => (string) $item['source_location'],
                    'uom_id' => (string) $item['uom_id'],
                    'qty_uom' => $qtyUom,
                    'conversion_to_base_snapshot' => $conversion,
                    'qty_base' => $qtyBase,
                    'received_date' => $receivedDate,
                    'expiry_date' => $expiryDate,
                    'supplier_name' => $supplierName === '' ? null : $supplierName,
                    'cost_per_base_unit_vnd' => $costPerBase,
                    'note' => $lineNote === '' ? ('Nhập kho từ PO ' . $planId) : $lineNote,
                    'movement_note' => 'Nhập kho từ phiếu nhận ' . $receiptId . ' / PO ' . $planId,
                ]);

                $this->plans->incrementPlanItemReceived($planItemId, $qtyUom, $qtyBase);
                $this->plans->createReceiptItem([
                    'receipt_id' => $receiptId,
                    'plan_id' => $planId,
                    'plan_item_id' => $planItemId,
                    'lot_id' => $lotId,
                    'product_id' => (string) $item['product_id'],
                    'uom_id' => (string) $item['uom_id'],
                    'qty_received_uom' => $qtyUom,
                    'conversion_to_base_snapshot' => $conversion,
                    'qty_received_base' => $qtyBase,
                    'cost_per_uom_vnd' => $costPerUom,
                ]);

                $itemsById[$planItemId]['qty_received_uom'] = round((float) $item['qty_received_uom'] + $qtyUom, 3);
                $itemsById[$planItemId]['qty_received_base'] = round((float) $item['qty_received_base'] + $qtyBase, 3);
                $processed++;
            }

            if ($processed === 0) {
                throw new AppException('Vui lòng nhập ít nhất một dòng hàng có số lượng nhận.', 422);
            }

            $newStatus = $this->recomputePlanStatus($planId);
            if ($newStatus === 'received') {
                $this->plans->markLinkedOrdersReceived($planId);
            }

            $this->pdo->commit();

            return $receiptId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function recomputePlanStatus(string $planId): string
    {
        $plan = $this->plans->findPlan($planId);
        if ($plan === null) {
            throw new AppException('Không tìm thấy PO.', 404);
        }

        $items = $this->plans->getPlanItems($planId);
        $receivedBase = 0.0;
        $remainingBase = 0.0;

        foreach ($items as $item) {
            $planned = (float) $item['qty_planned_base'];
            $received = (float) $item['qty_received_base'];
            $receivedBase += $received;
            $remainingBase += max(0.0, $planned - $received);
        }

        if ($receivedBase <= 0.0001) {
            $status = in_array((string) $plan['status'], ['draft', 'ordered'], true)
                ? (string) $plan['status']
                : 'ordered';
        } elseif ($remainingBase <= 0.0001) {
            $status = 'received';
        } else {
            $status = 'partial_received';
        }

        $this->plans->updatePlanStatus($planId, $status);

        return $status;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDetail(string $planId): ?array
    {
        return $this->plans->getPlanDetail($planId);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function groupRows(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $key = (string) $row['product_id'] . '|'
                . (string) $row['uom_id'] . '|'
                . (string) $row['source_location'];

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'product_id' => (string) $row['product_id'],
                    'product_name_snapshot' => (string) $row['product_name_snapshot'],
                    'uom_id' => (string) $row['uom_id'],
                    'uom_label_snapshot' => (string) $row['uom_label_snapshot'],
                    'source_location' => (string) $row['source_location'],
                    'qty_needed_uom' => 0.0,
                    'qty_planned_uom' => 0.0,
                    'conversion_to_base_snapshot' => (float) $row['conversion_to_base_snapshot'],
                    'qty_needed_base' => 0.0,
                    'qty_planned_base' => 0.0,
                    'cost_per_uom_vnd' => (int) $row['cost_price_vnd'],
                    'orders_count' => 0,
                    'order_ids' => [],
                    'note' => null,
                ];
            }

            $groups[$key]['qty_needed_uom'] = round($groups[$key]['qty_needed_uom'] + (float) $row['qty_uom'], 3);
            $groups[$key]['qty_planned_uom'] = $groups[$key]['qty_needed_uom'];
            $groups[$key]['qty_needed_base'] = round($groups[$key]['qty_needed_base'] + (float) $row['qty_base'], 3);
            $groups[$key]['qty_planned_base'] = $groups[$key]['qty_needed_base'];
            $groups[$key]['order_ids'][(string) $row['order_id']] = (string) $row['order_id'];
        }

        foreach ($groups as &$group) {
            $group['order_ids'] = array_values($group['order_ids']);
            $group['orders_count'] = count($group['order_ids']);
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * @param array<int, string> $sources
     */
    private function sourceScope(array $sources): string
    {
        $sources = array_values(array_unique(array_filter(
            array_map('strval', $sources),
            static fn (string $source): bool => $source !== ''
        )));

        if ($sources === []) {
            return 'Unknown';
        }

        return count($sources) === 1 ? $sources[0] : 'Mixed';
    }

    /**
     * @param array<int, mixed> $orderIds
     * @return array<int, string>
     */
    private function normalizeOrderIds(array $orderIds): array
    {
        $clean = [];
        foreach ($orderIds as $orderId) {
            $orderId = trim((string) $orderId);
            if ($orderId === '') {
                continue;
            }
            if (preg_match('/^[A-Za-z0-9_-]{1,80}$/', $orderId) !== 1) {
                throw new AppException('Mã đơn hàng không hợp lệ.', 422);
            }
            $clean[$orderId] = $orderId;
        }

        return array_values($clean);
    }

    private function normalizePlanId(string $planId): string
    {
        $planId = trim($planId);
        if ($planId === '' || preg_match('/^[A-Za-z0-9_-]{1,80}$/', $planId) !== 1) {
            throw new AppException('Mã PO không hợp lệ.', 422);
        }

        return $planId;
    }

    private function normalizeDate(mixed $value, string $message): string
    {
        $date = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new AppException($message, 422);
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));
        if (!checkdate($month, $day, $year)) {
            throw new AppException($message, 422);
        }

        return $date;
    }

    private function normalizeOptionalDate(mixed $value, string $message): ?string
    {
        $date = trim((string) ($value ?? ''));
        if ($date === '') {
            return null;
        }

        return $this->normalizeDate($date, $message);
    }

    private function normalizeDateTime(mixed $value): string
    {
        $dateTime = trim((string) ($value ?? ''));
        if ($dateTime === '') {
            return date('Y-m-d H:i:s');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTime) === 1) {
            return $this->normalizeDate($dateTime, 'Thời điểm nhận hàng không hợp lệ.') . ' 00:00:00';
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(:\d{2})?$/', $dateTime, $matches) !== 1) {
            throw new AppException('Thời điểm nhận hàng không hợp lệ.', 422);
        }

        $date = $this->normalizeDate($matches[1], 'Thời điểm nhận hàng không hợp lệ.');
        $seconds = $matches[3] ?? ':00';

        return $date . ' ' . $matches[2] . $seconds;
    }

    private function normalizeMoney(mixed $value, string $message): int
    {
        if (!is_numeric($value)) {
            throw new AppException($message, 422);
        }

        $money = (int) round((float) $value);
        if ($money < 0) {
            throw new AppException($message, 422);
        }

        return $money;
    }

    private function uniquePlanId(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $planId = IdGenerator::planId();

            if ($this->plans->findPlan($planId) === null) {
                return $planId;
            }
        }

        throw new AppException('Không tạo được mã PO duy nhất. Vui lòng thử lại.', 500);
    }

    private function uniqueReceiptId(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $receiptId = IdGenerator::receiptId();

            if ($this->plans->findReceipt($receiptId) === null) {
                return $receiptId;
            }
        }

        throw new AppException('Không tạo được mã phiếu nhận duy nhất. Vui lòng thử lại.', 500);
    }

    private function statusLabel(string $status): string
    {
        return [
            'draft' => 'Nháp',
            'ordered' => 'Đã đặt NCC',
            'partial_received' => 'Nhận một phần',
            'received' => 'Đã nhận đủ',
            'closed' => 'Đã khóa',
            'cancelled' => 'Đã hủy',
        ][$status] ?? $status;
    }
}
