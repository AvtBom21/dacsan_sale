<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\InventoryRepository;
use DacSanNhaDan\Support\IdGenerator;
use DateTimeImmutable;
use PDO;
use Throwable;

final class AdminInventoryService
{
    private const SOURCES = ['Binh Dinh', 'Gia Lai', 'Unknown'];
    private const DECIMAL_MAX = 999999999.999;
    private const INT_MAX = 2147483647;

    public function __construct(
        private PDO $pdo,
        private InventoryRepository $inventory
    ) {
    }

    /** @return array<string, mixed> */
    public function detail(string $lotId): array
    {
        $lot = $this->inventory->lotDetail($this->id($lotId, 'Mã lot không hợp lệ.', 60));
        if ($lot === null) {
            throw new AppException('Không tìm thấy lot kho.', 404);
        }

        return $lot;
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function receiveManual(array $payload): array
    {
        $productId = $this->id($payload['product_id'] ?? '', 'Mã sản phẩm không hợp lệ.', 40);
        $uomId = $this->id($payload['uom_id'] ?? '', 'Mã UOM không hợp lệ.', 60);
        $uom = $this->inventory->purchasableUom($productId, $uomId);
        if ($uom === null) {
            throw new AppException('UOM nhập không tồn tại, không hoạt động hoặc không thuộc sản phẩm.', 422);
        }

        $qtyUom = $this->positiveDecimal($payload['qty_uom'] ?? null, 'Số lượng nhập');
        $conversion = $this->positiveDecimal($uom['conversion_to_base'], 'Tỷ lệ quy đổi');
        $qtyBase = round($qtyUom * $conversion, 3);
        if ($qtyBase < 0.001 || $qtyBase > self::DECIMAL_MAX) {
            throw new AppException('Số lượng quy đổi vượt giới hạn lưu trữ.', 422);
        }
        $costPerUom = $this->nonNegativeInt($payload['cost_per_uom_vnd'] ?? $uom['cost_price_vnd'], 'Giá nhập');
        $costPerBase = (int) round($costPerUom / $conversion);
        if ($costPerBase > self::INT_MAX) {
            throw new AppException('Giá nhập trên đơn vị tồn vượt giới hạn.', 422);
        }

        $receivedDate = $this->date($payload['received_date'] ?? date('Y-m-d'), 'Ngày nhập');
        $expiryDate = $this->optionalDate($payload['expiry_date'] ?? null, 'Hạn sử dụng');
        if ($expiryDate !== null && $expiryDate < $receivedDate) {
            throw new AppException('Hạn sử dụng không được trước ngày nhập.', 422);
        }
        $source = $this->enum($payload['source_location'] ?? $uom['default_source'], self::SOURCES, 'Nguồn hàng');
        $supplier = $this->optionalText($payload['supplier_name'] ?? '', 160, 'Nhà cung cấp');
        $note = $this->optionalText($payload['note'] ?? '', 2000, 'Ghi chú');
        $lotId = $this->uniqueLotId();

        $this->pdo->beginTransaction();
        try {
            $this->inventory->createLot([
                'lot_id' => $lotId,
                'product_id' => $productId,
                'source_location' => $source,
                'qty_base_on_hand' => $qtyBase,
                'qty_base_reserved' => 0,
                'received_date' => $receivedDate,
                'expiry_date' => $expiryDate,
                'supplier_name' => $supplier === '' ? null : $supplier,
                'cost_per_base_unit_vnd' => $costPerBase,
                'received_uom_id' => $uomId,
                'received_qty_uom' => $qtyUom,
                'conversion_to_base_snapshot' => $conversion,
                'note' => $note === '' ? null : $note,
            ]);
            $this->inventory->createMovement([
                'movement_type' => 'IN',
                'ref_type' => 'MANUAL',
                'ref_id' => $lotId,
                'lot_id' => $lotId,
                'product_id' => $productId,
                'source_location' => $source,
                'uom_id' => $uomId,
                'qty_uom' => $qtyUom,
                'conversion_to_base_snapshot' => $conversion,
                'qty_base' => $qtyBase,
                'cost_per_base_unit_vnd' => $costPerBase,
                'note' => $note === '' ? 'Nhập kho thủ công' : $note,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $this->detail($lotId);
    }

    /** @return array<string, mixed> */
    public function adjustLot(string $lotId, mixed $deltaValue, mixed $reasonValue): array
    {
        $lotId = $this->id($lotId, 'Mã lot không hợp lệ.', 60);
        $delta = $this->signedDecimal($deltaValue, 'Số lượng điều chỉnh');
        $reason = $this->text($reasonValue, 500, 'Lý do điều chỉnh', 3);

        $this->pdo->beginTransaction();
        try {
            $lot = $this->inventory->findLotForUpdate($lotId);
            if ($lot === null) {
                throw new AppException('Không tìm thấy lot kho.', 404);
            }
            try {
                $this->inventory->adjustLotOnHand($lotId, $delta);
            } catch (\RuntimeException $exception) {
                throw new AppException($exception->getMessage(), 409, [], $exception);
            }
            $this->inventory->createMovement([
                'movement_type' => 'ADJUST',
                'ref_type' => 'MANUAL',
                'ref_id' => $lotId,
                'lot_id' => $lotId,
                'product_id' => $lot['product_id'],
                'source_location' => $lot['source_location'],
                'uom_id' => null,
                'qty_uom' => $delta,
                'conversion_to_base_snapshot' => 1,
                'qty_base' => $delta,
                'cost_per_base_unit_vnd' => $lot['cost_per_base_unit_vnd'],
                'note' => $reason,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $this->detail($lotId);
    }

    private function uniqueLotId(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = IdGenerator::lotId();
            if ($this->inventory->findLot($candidate) === null) {
                return $candidate;
            }
        }
        throw new AppException('Không tạo được mã lot duy nhất.', 500);
    }

    private function id(mixed $value, string $message, int $max): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw new AppException($message, 422);
        }
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > $max || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            throw new AppException($message, 422);
        }
        return $value;
    }

    private function positiveDecimal(mixed $value, string $label): float
    {
        if (!is_numeric($value) || !is_finite((float) $value)) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }
        $number = round((float) $value, 3);
        if ($number < 0.001 || $number > self::DECIMAL_MAX) {
            throw new AppException($label . ' phải từ 0.001 đến 999999999.999.', 422);
        }
        return $number;
    }

    private function signedDecimal(mixed $value, string $label): float
    {
        if (!is_numeric($value) || !is_finite((float) $value)) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }
        $number = round((float) $value, 3);
        if (abs($number) < 0.001 || abs($number) > self::DECIMAL_MAX) {
            throw new AppException($label . ' phải khác 0 và nằm trong giới hạn lưu trữ.', 422);
        }
        return $number;
    }

    private function nonNegativeInt(mixed $value, string $label): int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => self::INT_MAX],
        ]);
        if ($validated === false) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }
        return (int) $validated;
    }

    private function date(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }
        return $value;
    }

    private function optionalDate(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return $this->date($value, $label);
    }

    /** @param list<string> $allowed */
    private function enum(mixed $value, array $allowed, string $label): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }
        return $value;
    }

    private function text(mixed $value, int $max, string $label, int $min = 1): string
    {
        if (!is_scalar($value)) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }
        $value = trim((string) $value);
        $length = mb_strlen($value, 'UTF-8');
        if ($length < $min || $length > $max) {
            throw new AppException($label . ' phải từ ' . $min . ' đến ' . $max . ' ký tự.', 422);
        }
        return $value;
    }

    private function optionalText(mixed $value, int $max, string $label): string
    {
        if ($value === null) {
            return '';
        }
        if (!is_scalar($value)) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }
        $value = trim((string) $value);
        if (mb_strlen($value, 'UTF-8') > $max) {
            throw new AppException($label . ' quá dài.', 422);
        }
        return $value;
    }
}
