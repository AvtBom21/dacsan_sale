<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\AdminProductRepository;
use PDO;
use PDOException;
use Throwable;

final class AdminProductService
{
    private const SOURCES = ['Binh Dinh', 'Gia Lai', 'Unknown'];
    private const SHELF_UNITS = ['', 'days', 'months'];
    private const MYSQL_SIGNED_INT_MAX = 2147483647;
    private const DECIMAL_12_3_MAX = 999999999.999;

    public function __construct(
        private PDO $pdo,
        private AdminProductRepository $products
    ) {
    }

    /** @return array<string, mixed> */
    public function form(?string $productId = null): array
    {
        return [
            'product' => $productId === null ? null : $this->requiredDetail($productId),
            'categories' => $this->products->categories(),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(string $productId): array
    {
        return $this->requiredDetail($this->id($productId, 'Mã sản phẩm không hợp lệ.'));
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function save(array $payload): array
    {
        $originalId = trim((string) ($payload['original_product_id'] ?? ''));
        $productId = $this->id((string) ($payload['product_id'] ?? ''), 'Mã sản phẩm không hợp lệ.', 40);
        $isUpdate = $originalId !== '';
        if ($isUpdate) {
            $originalId = $this->id($originalId, 'Mã sản phẩm gốc không hợp lệ.', 40);
            if ($originalId !== $productId) {
                throw new AppException('Không thể thay đổi mã sản phẩm.', 422);
            }
            if (!$this->products->productExists($productId)) {
                throw new AppException('Không tìm thấy sản phẩm.', 404);
            }
        } elseif ($this->products->productExists($productId)) {
            throw new AppException('Mã sản phẩm đã tồn tại.', 409);
        }

        $categoryId = $this->id((string) ($payload['category_id'] ?? ''), 'Nhóm sản phẩm không hợp lệ.', 40);
        $category = $this->products->category($categoryId);
        if ($category === null) {
            throw new AppException('Không tìm thấy nhóm sản phẩm.', 422);
        }

        $slug = $this->slug((string) ($payload['product_slug'] ?? ''));
        if ($this->products->slugExists($slug, $isUpdate ? $productId : null)) {
            throw new AppException('Slug sản phẩm đã tồn tại.', 409);
        }

        $product = [
            'product_id' => $productId,
            'product_name' => $this->text($payload['product_name'] ?? '', 200, 'Tên sản phẩm'),
            'product_slug' => $slug,
            'category_id' => $categoryId,
            'category_label' => (string) $category['category_name'],
            'default_source' => $this->enum($payload['default_source'] ?? 'Unknown', self::SOURCES, 'Nguồn hàng'),
            'short_description' => $this->optionalText($payload['short_description'] ?? '', 255, 'Mô tả ngắn'),
            'full_description' => $this->optionalText($payload['full_description'] ?? '', 20000, 'Mô tả đầy đủ'),
            'ingredients' => $this->optionalText($payload['ingredients'] ?? '', 10000, 'Thành phần'),
            'base_uom_label' => $this->text($payload['base_uom_label'] ?? '', 80, 'Đơn vị tồn kho'),
            'shelf_life_value' => $this->nonNegativeInt($payload['shelf_life_value'] ?? 0, 'Hạn sử dụng'),
            'shelf_life_unit' => $this->enum($payload['shelf_life_unit'] ?? '', self::SHELF_UNITS, 'Đơn vị hạn sử dụng'),
            'is_active' => $this->flag($payload['is_active'] ?? 1),
        ];
        $uoms = $this->validateUoms($productId, $payload['uoms'] ?? []);
        $images = $this->validateImages($productId, $payload['images'] ?? []);

        $this->pdo->beginTransaction();
        try {
            if ($isUpdate) {
                $this->products->updateProduct($product);
            } else {
                $this->products->insertProduct($product);
            }

            $existingUoms = $this->products->uomIds($productId);
            $keptUoms = [];
            foreach ($uoms as $uom) {
                $keptUoms[] = $uom['uom_id'];
                if (in_array($uom['uom_id'], $existingUoms, true)) {
                    $this->products->updateUom($uom);
                } else {
                    $this->products->insertUom($uom);
                }
            }
            $this->products->deactivateMissingUoms($productId, $keptUoms);

            $existingImages = $this->products->imageIds($productId);
            $keptImages = [];
            foreach ($images as $image) {
                if ($image['image_id'] !== null) {
                    if (!in_array($image['image_id'], $existingImages, true)) {
                        throw new AppException('Ảnh không thuộc sản phẩm.', 422);
                    }
                    $this->products->updateImage($image);
                    $keptImages[] = $image['image_id'];
                } else {
                    unset($image['image_id']);
                    $keptImages[] = $this->products->insertImage($image);
                }
            }
            $this->products->deactivateMissingImages($productId, $keptImages);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                throw new AppException('Slug hoặc mã UOM đã tồn tại.', 409, [], $exception);
            }
            throw $exception;
        }

        return $this->requiredDetail($productId);
    }

    public function setActive(string $productId, bool $isActive): void
    {
        $productId = $this->id($productId, 'Mã sản phẩm không hợp lệ.', 40);
        if (!$this->products->productExists($productId)) {
            throw new AppException('Không tìm thấy sản phẩm.', 404);
        }
        $this->products->setActive($productId, $isActive);
    }

    /** @return array<string, mixed> */
    private function requiredDetail(string $productId): array
    {
        $detail = $this->products->detail($productId);
        if ($detail === null) {
            throw new AppException('Không tìm thấy sản phẩm.', 404);
        }

        return $detail;
    }

    /** @return array<int, array<string, mixed>> */
    private function validateUoms(string $productId, mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new AppException('Danh sách UOM không hợp lệ.', 422);
        }
        $uoms = [];
        $ids = [];
        $baseCount = 0;
        $defaultCount = 0;
        foreach ($raw as $index => $row) {
            if (!is_array($row)) {
                throw new AppException('UOM không hợp lệ.', 422);
            }
            $active = $this->flag($row['is_active'] ?? 1);
            $base = $this->flag($row['is_base_unit'] ?? 0);
            $default = $this->flag($row['is_default'] ?? 0);
            $conversion = $this->positiveNumber($row['conversion_to_base'] ?? 0, 'Tỷ lệ quy đổi UOM');
            if ($active === 1 && $base === 1) {
                $baseCount++;
                if (abs($conversion - 1.0) > 0.0001) {
                    throw new AppException('UOM gốc phải có tỷ lệ quy đổi bằng 1.', 422);
                }
            }
            if ($active === 1 && $default === 1) {
                $defaultCount++;
            }
            $uomId = $this->id((string) ($row['uom_id'] ?? ''), 'Mã UOM không hợp lệ.', 60);
            $owner = $this->products->uomOwner($uomId);
            if ($owner !== null && $owner !== $productId) {
                throw new AppException('UOM không thuộc sản phẩm.', 422);
            }
            if (isset($ids[$uomId])) {
                throw new AppException('Mã UOM bị trùng.', 422);
            }
            $ids[$uomId] = true;
            $uoms[] = [
                'uom_id' => $uomId,
                'product_id' => $productId,
                'uom_label' => $this->text($row['uom_label'] ?? '', 120, 'Tên UOM'),
                'conversion_to_base' => $conversion,
                'unit_price_vnd' => $this->nonNegativeInt($row['unit_price_vnd'] ?? 0, 'Giá bán'),
                'cost_price_vnd' => $this->nonNegativeInt($row['cost_price_vnd'] ?? 0, 'Giá vốn'),
                'is_base_unit' => $base,
                'is_default' => $default,
                'is_sellable' => $this->flag($row['is_sellable'] ?? 1),
                'is_purchasable' => $this->flag($row['is_purchasable'] ?? 1),
                'is_active' => $active,
                'sort_order' => $this->nonNegativeInt($row['sort_order'] ?? $index, 'Thứ tự UOM'),
                'note' => $this->optionalText($row['note'] ?? '', 255, 'Ghi chú UOM'),
            ];
        }
        if ($baseCount !== 1) {
            throw new AppException('Phải có đúng một UOM gốc đang hoạt động.', 422);
        }
        if ($defaultCount > 1) {
            throw new AppException('Chỉ được có một UOM mặc định đang hoạt động.', 422);
        }

        return $uoms;
    }

    /** @return array<int, array<string, mixed>> */
    private function validateImages(string $productId, mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new AppException('Danh sách ảnh không hợp lệ.', 422);
        }
        $images = [];
        $imageIds = [];
        $baseCount = 0;
        foreach ($raw as $index => $row) {
            if (!is_array($row)) {
                throw new AppException('Thông tin ảnh không hợp lệ.', 422);
            }
            $active = $this->flag($row['is_active'] ?? 1);
            $base = $this->flag($row['is_base'] ?? 0);
            if ($active === 1 && $base === 1) {
                $baseCount++;
            }
            $imageId = $row['image_id'] ?? null;
            if ($imageId !== null && $imageId !== '') {
                if (filter_var($imageId, FILTER_VALIDATE_INT) === false || (int) $imageId < 1) {
                    throw new AppException('Mã ảnh không hợp lệ.', 422);
                }
                $imageId = (int) $imageId;
                if (isset($imageIds[$imageId])) {
                    throw new AppException('Mã ảnh bị trùng.', 422);
                }
                $imageIds[$imageId] = true;
            } else {
                $imageId = null;
            }
            $images[] = [
                'image_id' => $imageId,
                'product_id' => $productId,
                'image_path' => $this->text($row['image_path'] ?? '', 255, 'Đường dẫn ảnh'),
                'source_url' => $this->optionalText($row['source_url'] ?? '', 2000, 'URL nguồn ảnh'),
                'image_alt' => $this->optionalText($row['image_alt'] ?? '', 255, 'Mô tả ảnh'),
                'is_base' => $base,
                'is_active' => $active,
                'sort_order' => $this->nonNegativeInt($row['sort_order'] ?? $index, 'Thứ tự ảnh'),
            ];
        }
        if ($baseCount > 1) {
            throw new AppException('Chỉ được có một ảnh chính đang hoạt động.', 422);
        }

        return $images;
    }

    private function id(string $value, string $message, int $max = 80): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > $max || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            throw new AppException($message, 422);
        }

        return $value;
    }

    private function slug(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1 || strlen($value) > 220) {
            throw new AppException('Slug sản phẩm không hợp lệ.', 422);
        }

        return $value;
    }

    private function text(mixed $value, int $max, string $label): string
    {
        if (!is_scalar($value)) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }
        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > $max) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }

        return $value;
    }

    private function optionalText(mixed $value, int $max, string $label): string
    {
        if (!is_scalar($value) && $value !== null) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }
        $value = trim((string) $value);
        if (mb_strlen($value, 'UTF-8') > $max) {
            throw new AppException($label . ' quá dài.', 422);
        }

        return $value;
    }

    /** @param array<int, string> $allowed */
    private function enum(mixed $value, array $allowed, string $label): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }

        return $value;
    }

    private function flag(mixed $value): int
    {
        return ((int) $value) === 1 ? 1 : 0;
    }

    private function nonNegativeInt(mixed $value, string $label): int
    {
        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => self::MYSQL_SIGNED_INT_MAX]]
        );
        if ($validated === false) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }

        return (int) $validated;
    }

    private function positiveNumber(mixed $value, string $label): float
    {
        if (!is_numeric($value)) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }

        $number = (float) $value;
        if (!is_finite($number)) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }

        $normalized = round($number, 3);
        if ($normalized < 0.001 || $normalized > self::DECIMAL_12_3_MAX) {
            throw new AppException(
                $label . ' phải từ 0.001 đến 999999999.999.',
                422
            );
        }

        return $normalized;
    }
}
