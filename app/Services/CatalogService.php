<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Repositories\InventoryRepository;
use DacSanNhaDan\Repositories\ProductRepository;
use DacSanNhaDan\Support\Formatter;

final class CatalogService
{
    private string $legacyRootPath;

    public function __construct(
        private ProductRepository $products,
        private InventoryRepository $inventory,
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
     * @param array{category_id?: string, source?: string, q?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function catalog(array $filters = []): array
    {
        $cards = $this->products->productCards($filters);
        $productIds = array_values(array_unique(array_map(
            static fn (array $row): string => (string) $row['product_id'],
            $cards
        )));

        $uomsByProduct = $this->products->sellableUomsByProductIds($productIds);
        $imagesByProduct = $this->products->activeImagesByProductIds($productIds);
        $stockByProduct = $this->inventory->summariesByProductIds($productIds);

        $catalog = [];
        foreach ($cards as $card) {
            $productId = (string) $card['product_id'];
            $uoms = $this->formatUoms($uomsByProduct[$productId] ?? []);

            if ($uoms === []) {
                continue;
            }

            $catalog[] = $this->buildProductPayload(
                $card,
                $uoms,
                $imagesByProduct[$productId] ?? [],
                $stockByProduct[$productId] ?? null
            );
        }

        return $catalog;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function productDetail(string $productId): ?array
    {
        $product = $this->products->activeProductDetail($productId);

        if ($product === null) {
            return null;
        }

        $uoms = $this->formatUoms($this->products->sellableUomsForProduct($productId));

        if ($uoms === []) {
            return null;
        }

        $images = $this->products->activeImagesForProduct($productId);
        $stock = $this->inventory->summaryForProduct($productId);

        return $this->buildProductPayload($product, $uoms, $images, $stock);
    }

    /**
     * @return array<string, mixed>|array<int, array<string, mixed>>|null
     */
    public function stockSummary(?string $productId = null): array|null
    {
        if ($productId !== null && $productId !== '') {
            $product = $this->products->activeProductDetail($productId);

            if ($product === null) {
                return null;
            }

            return $this->inventory->summaryForProduct($productId);
        }

        $cards = $this->products->productCards();
        $productIds = array_values(array_unique(array_map(
            static fn (array $row): string => (string) $row['product_id'],
            $cards
        )));

        return array_values($this->inventory->summariesByProductIds($productIds));
    }

    /**
     * @param array<string, mixed> $product
     * @param array<int, array<string, mixed>> $uoms
     * @param array<int, array<string, mixed>> $images
     * @param array<string, mixed>|null $stock
     * @return array<string, mixed>
     */
    private function buildProductPayload(array $product, array $uoms, array $images, ?array $stock): array
    {
        $defaultUom = $this->defaultUom($uoms);
        $gallery = $this->formatImages($images, (string) ($product['product_name'] ?? ''));

        if ($gallery === []) {
            $gallery[] = $this->resolveImage(
                (string) ($product['base_image_path'] ?? ''),
                (string) ($product['product_name'] ?? '')
            );
        }

        $baseImage = $this->baseImage($gallery, (string) ($product['base_image_path'] ?? ''));

        return [
            'product_id' => (string) $product['product_id'],
            'product_name' => (string) $product['product_name'],
            'product_slug' => (string) ($product['product_slug'] ?? ''),
            'category_id' => (string) ($product['category_id'] ?? ''),
            'category_label' => (string) ($product['category_label'] ?? ''),
            'source' => (string) ($product['default_source'] ?? 'Unknown'),
            'region' => $this->sourceToRegion((string) ($product['default_source'] ?? 'Unknown')),
            'short_description' => (string) ($product['short_description'] ?? ''),
            'full_description' => (string) ($product['full_description'] ?? ''),
            'ingredients' => (string) ($product['ingredients'] ?? ''),
            'base_uom_label' => (string) ($product['base_uom_label'] ?? ''),
            'shelf_life_value' => (int) ($product['shelf_life_value'] ?? 0),
            'shelf_life_unit' => (string) ($product['shelf_life_unit'] ?? ''),
            'base_image' => $baseImage,
            'gallery_images' => $gallery,
            'sellable_uoms' => $uoms,
            'default_uom' => $defaultUom,
            'price_vnd' => $defaultUom['unit_price_vnd'],
            'price_display' => $defaultUom['unit_price_display'],
            'price_per_base_unit_vnd' => $defaultUom['price_per_base_unit_vnd'],
            'price_per_base_unit_display' => $defaultUom['price_per_base_unit_display'],
            'stock' => $stock ?? $this->emptyStock((string) $product['product_id']),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatUoms(array $rows): array
    {
        $uoms = [];

        foreach ($rows as $row) {
            $conversion = max(0.001, (float) $row['conversion_to_base']);
            $price = (int) $row['unit_price_vnd'];
            $pricePerBase = (int) round($price / $conversion);

            $uoms[] = [
                'uom_id' => (string) $row['uom_id'],
                'uom_label' => (string) $row['uom_label'],
                'conversion_to_base' => $conversion,
                'unit_price_vnd' => $price,
                'unit_price_display' => Formatter::moneyVnd($price),
                'price_per_base_unit_vnd' => $pricePerBase,
                'price_per_base_unit_display' => Formatter::moneyVnd($pricePerBase),
                'is_base_unit' => (int) $row['is_base_unit'] === 1,
                'is_default' => (int) $row['is_default'] === 1,
                'note' => (string) ($row['note'] ?? ''),
            ];
        }

        return $uoms;
    }

    /**
     * @param array<int, array<string, mixed>> $uoms
     * @return array<string, mixed>
     */
    private function defaultUom(array $uoms): array
    {
        foreach ($uoms as $uom) {
            if (($uom['is_default'] ?? false) === true) {
                return $uom;
            }
        }

        return $uoms[0];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatImages(array $rows, string $fallbackAlt): array
    {
        $images = [];
        $seen = [];

        foreach ($rows as $row) {
            $image = $this->resolveImage(
                (string) ($row['image_path'] ?? ''),
                (string) ($row['image_alt'] ?? $fallbackAlt),
                (int) ($row['is_base'] ?? 0) === 1
            );

            $key = $image['path'] . '|' . $image['url'];
            if (!isset($seen[$key])) {
                $images[] = $image;
                $seen[$key] = true;
            }
        }

        return $images;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveImage(string $path, string $alt, bool $isBase = false): array
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
            'is_base' => $isBase,
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

    /**
     * @param array<int, array<string, mixed>> $gallery
     * @return array<string, mixed>
     */
    private function baseImage(array $gallery, string $basePath): array
    {
        foreach ($gallery as $image) {
            if (($image['is_base'] ?? false) === true) {
                return $image;
            }
        }

        $safeBasePath = $this->safeImagePath($basePath);
        foreach ($gallery as $image) {
            if (($image['path'] ?? '') === $safeBasePath) {
                return $image;
            }
        }

        return $gallery[0];
    }

    private function sourceToRegion(string $source): string
    {
        return match ($source) {
            'Binh Dinh' => 'binh-dinh',
            'Gia Lai' => 'gia-lai',
            default => 'unknown',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStock(string $productId): array
    {
        return [
            'product_id' => $productId,
            'qty_base_on_hand' => 0.0,
            'qty_base_reserved' => 0.0,
            'qty_base_available' => 0.0,
            'nearest_expiry_date' => null,
            'status' => 'out_of_stock',
            'badge' => 'Tạm hết hàng',
        ];
    }
}
