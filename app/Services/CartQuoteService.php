<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\InventoryRepository;
use DacSanNhaDan\Repositories\ProductRepository;
use DacSanNhaDan\Repositories\SettingRepository;
use DacSanNhaDan\Repositories\ShippingRepository;
use DacSanNhaDan\Support\Formatter;

final class CartQuoteService
{
    private string $legacyRootPath;

    public function __construct(
        private ProductRepository $products,
        private InventoryRepository $inventory,
        private ShippingRepository $shipping,
        private SettingRepository $settings,
        ?string $legacyRootPath = null
    ) {
        $this->legacyRootPath = $legacyRootPath ?? $this->defaultLegacyRootPath();
    }

    private function defaultLegacyRootPath(): string
    {
        $projectRoot = dirname(__DIR__, 2);
        if (is_dir($projectRoot . '/products_image')) {
            return $projectRoot;
        }

        return dirname(__DIR__, 3);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function quote(array $payload): array
    {
        $shippingMethod = $this->validateShippingMethod($payload['shipping_method'] ?? 'delivery');
        $shippingZoneId = $this->optionalId((string) ($payload['shipping_zone_id'] ?? ''), 'shipping_zone_id');
        $allowBackorder = $this->allowBackorder();
        $items = $this->normalizeItems($payload['items'] ?? []);

        $quoteItems = [];
        $subtotal = 0;
        $sources = [];
        $errors = [];

        foreach ($items as $item) {
            $line = $this->quoteItem($item, $allowBackorder);
            $quoteItems[] = $line;

            if ($line['errors'] !== []) {
                $errors = array_merge($errors, $line['errors']);
                continue;
            }

            $subtotal += (int) $line['line_total_vnd'];
            $sources[] = (string) $line['source_location'];
        }

        $shippingQuote = $this->shippingQuote($shippingMethod, $shippingZoneId, $subtotal);
        if ($shippingQuote['errors'] !== []) {
            $errors = array_merge($errors, $shippingQuote['errors']);
        }

        $total = $subtotal + (int) $shippingQuote['shipping_fee_vnd'];

        return [
            'can_checkout' => $errors === [] && $subtotal > 0,
            'errors' => array_values(array_unique($errors)),
            'items' => $quoteItems,
            'item_count' => count($quoteItems),
            'subtotal_vnd' => $subtotal,
            'subtotal_display' => Formatter::moneyVnd($subtotal),
            'shipping_method' => $shippingMethod,
            'shipping_zone' => $shippingQuote['shipping_zone'],
            'shipping_fee_vnd' => (int) $shippingQuote['shipping_fee_vnd'],
            'shipping_fee_display' => Formatter::moneyVnd($shippingQuote['shipping_fee_vnd']),
            'free_ship_threshold_vnd' => (int) $shippingQuote['free_ship_threshold_vnd'],
            'free_ship_remaining_vnd' => (int) $shippingQuote['free_ship_remaining_vnd'],
            'is_free_ship' => (bool) $shippingQuote['is_free_ship'],
            'total_vnd' => $total,
            'total_display' => Formatter::moneyVnd($total),
            'source_summary' => $this->sourceSummary($sources),
            'allow_backorder' => $allowBackorder,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function quoteItem(array $item, bool $allowBackorder): array
    {
        $errors = [];
        $productId = (string) $item['product_id'];
        $uomId = (string) $item['uom_id'];
        $qtyUom = (float) $item['qty'];
        $dbLine = $this->products->findSellableUom($productId, $uomId);

        if ($dbLine === null) {
            return [
                'product_id' => $productId,
                'uom_id' => $uomId,
                'qty_uom' => $qtyUom,
                'errors' => ['Sản phẩm hoặc đơn vị bán không còn hợp lệ.'],
            ];
        }

        $conversion = (float) $dbLine['conversion_to_base'];
        if ($conversion <= 0) {
            return [
                'product_id' => $productId,
                'uom_id' => $uomId,
                'qty_uom' => $qtyUom,
                'errors' => ['Cấu hình quy đổi đơn vị bán chưa hợp lệ.'],
            ];
        }

        $source = (string) ($dbLine['default_source'] ?: 'Unknown');
        $stockSource = $source === 'Unknown' ? null : $source;
        $qtyBase = round($qtyUom * $conversion, 3);
        $availableBaseQty = $this->inventory->getAvailableBaseQty($productId, $stockSource);
        $unitPrice = (int) $dbLine['unit_price_vnd'];
        $lineTotal = (int) round($unitPrice * $qtyUom);
        $stockWarning = null;

        if (!$allowBackorder && $qtyBase > $availableBaseQty + 0.0001) {
            $errors[] = sprintf(
                'Sản phẩm %s không đủ tồn kho. Còn %s %s.',
                (string) $dbLine['product_name'],
                Formatter::decimalDisplay($availableBaseQty),
                (string) $dbLine['base_uom_label']
            );
        } elseif ($availableBaseQty <= 3) {
            $stockWarning = 'Sản phẩm sắp hết hàng.';
        }

        return [
            'product_id' => $productId,
            'product_name' => (string) $dbLine['product_name'],
            'uom_id' => $uomId,
            'uom_label' => (string) $dbLine['uom_label'],
            'source_location' => $source,
            'base_uom_label' => (string) $dbLine['base_uom_label'],
            'qty_uom' => $qtyUom,
            'conversion_to_base' => $conversion,
            'qty_base' => $qtyBase,
            'unit_price_vnd' => $unitPrice,
            'unit_price_display' => Formatter::moneyVnd($unitPrice),
            'line_total_vnd' => $lineTotal,
            'line_total_display' => Formatter::moneyVnd($lineTotal),
            'price_per_base_unit_vnd' => (int) round($unitPrice / $conversion),
            'stock_available_base_qty' => $availableBaseQty,
            'stock_warning' => $stockWarning,
            'stock_error' => $errors[0] ?? null,
            'image' => $this->imagePayload((string) ($dbLine['base_image_path'] ?? ''), (string) $dbLine['product_name']),
            'errors' => $errors,
        ];
    }

    /**
     * @param mixed $rawItems
     * @return array<int, array{product_id: string, uom_id: string, qty: float}>
     */
    private function normalizeItems(mixed $rawItems): array
    {
        if (!is_array($rawItems) || $rawItems === []) {
            throw new AppException('Giỏ hàng chưa có sản phẩm.', 422);
        }

        if (count($rawItems) > 40) {
            throw new AppException('Giỏ hàng có quá nhiều dòng sản phẩm.', 422);
        }

        $combined = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                throw new AppException('Dòng sản phẩm không hợp lệ.', 422);
            }

            $productId = $this->requiredId((string) ($rawItem['product_id'] ?? ''), 'product_id');
            $uomId = $this->requiredId((string) ($rawItem['uom_id'] ?? ''), 'uom_id');
            $qty = $this->validateQty($rawItem['qty'] ?? 0);
            $key = $productId . '|' . $uomId;

            if (!isset($combined[$key])) {
                $combined[$key] = [
                    'product_id' => $productId,
                    'uom_id' => $uomId,
                    'qty' => 0.0,
                ];
            }

            $combined[$key]['qty'] = round($combined[$key]['qty'] + $qty, 3);
        }

        return array_values($combined);
    }

    private function validateShippingMethod(mixed $value): string
    {
        $method = trim((string) $value);

        if (!in_array($method, ['delivery', 'pickup'], true)) {
            throw new AppException('Phương thức nhận hàng không hợp lệ.', 422);
        }

        return $method;
    }

    private function validateQty(mixed $value): float
    {
        if (!is_numeric($value)) {
            throw new AppException('Số lượng sản phẩm không hợp lệ.', 422);
        }

        $qty = round((float) $value, 3);

        if ($qty <= 0 || $qty > 99) {
            throw new AppException('Số lượng sản phẩm không hợp lệ.', 422);
        }

        return $qty;
    }

    private function requiredId(string $value, string $field): string
    {
        $id = $this->optionalId($value, $field);

        if ($id === null) {
            throw new AppException('Thiếu tham số ' . $field . '.', 422);
        }

        return $id;
    }

    private function optionalId(string $value, string $field): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_-]{1,80}$/', $value) !== 1) {
            throw new AppException('Tham số ' . $field . ' không hợp lệ.', 422);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function shippingQuote(string $shippingMethod, ?string $shippingZoneId, int $subtotal): array
    {
        $threshold = $this->shipping->freeShipThreshold();

        if ($shippingMethod === 'pickup') {
            return [
                'shipping_zone' => null,
                'shipping_fee_vnd' => 0,
                'free_ship_threshold_vnd' => $threshold,
                'free_ship_remaining_vnd' => max(0, $threshold - $subtotal),
                'is_free_ship' => false,
                'errors' => [],
            ];
        }

        $zone = $shippingZoneId === null
            ? $this->shipping->defaultZone()
            : $this->shipping->findActiveZone($shippingZoneId);

        if ($zone === null) {
            return [
                'shipping_zone' => null,
                'shipping_fee_vnd' => 0,
                'free_ship_threshold_vnd' => $threshold,
                'free_ship_remaining_vnd' => max(0, $threshold - $subtotal),
                'is_free_ship' => false,
                'errors' => ['Vùng giao hàng không hợp lệ.'],
            ];
        }

        $isFreeShip = $threshold > 0 && $subtotal >= $threshold;

        return [
            'shipping_zone' => $zone,
            'shipping_fee_vnd' => $isFreeShip ? 0 : (int) $zone['fee_vnd'],
            'free_ship_threshold_vnd' => $threshold,
            'free_ship_remaining_vnd' => max(0, $threshold - $subtotal),
            'is_free_ship' => $isFreeShip,
            'errors' => [],
        ];
    }

    private function allowBackorder(): bool
    {
        $value = strtolower((string) $this->settings->get('allow_backorder', '0'));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<int, string> $sources
     */
    private function sourceSummary(array $sources): string
    {
        if ($sources === []) {
            return 'Unknown';
        }

        $unique = array_values(array_unique($sources));

        return count($unique) === 1 ? $unique[0] : 'Mixed';
    }

    /**
     * @return array<string, mixed>
     */
    private function imagePayload(string $path, string $alt): array
    {
        $placeholder = 'products_image/placeholder.svg';
        $safePath = $this->safeImagePath($path);
        $urlPath = $this->existingImagePath($safePath);
        $exists = is_file($this->legacyRootPath . '/' . $urlPath);

        return [
            'path' => $urlPath,
            'url' => '../' . $urlPath,
            'alt' => $alt,
            'exists' => $exists,
            'fallback_used' => $urlPath === $placeholder && $safePath !== $placeholder,
        ];
    }

    private function existingImagePath(string $safePath): string
    {
        if (is_file($this->legacyRootPath . '/' . $safePath)) {
            return $safePath;
        }

        $flatPath = 'products_image/' . basename($safePath);
        if ($flatPath !== $safePath && is_file($this->legacyRootPath . '/' . $flatPath)) {
            return $flatPath;
        }

        return 'products_image/placeholder.svg';
    }

    private function safeImagePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if (preg_match('#^products_image/[A-Za-z0-9._/\-]+$#', $path) !== 1) {
            return 'products_image/placeholder.svg';
        }

        return $path;
    }
}
