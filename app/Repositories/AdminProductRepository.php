<?php

declare(strict_types=1);

namespace DacSanNhaDan\Repositories;

use PDO;

final class AdminProductRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function category(string $categoryId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT category_id, category_name, category_slug, is_active
             FROM categories WHERE category_id = :category_id LIMIT 1'
        );
        $statement->execute(['category_id' => $categoryId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function categories(): array
    {
        return $this->pdo->query(
            'SELECT category_id, category_name, category_slug, sort_order, is_active
             FROM categories ORDER BY sort_order, category_name'
        )->fetchAll();
    }

    public function productExists(string $productId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM products WHERE product_id = :product_id'
        );
        $statement->execute(['product_id' => $productId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function slugExists(string $slug, ?string $exceptProductId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM products WHERE product_slug = :slug';
        $params = ['slug' => $slug];
        if ($exceptProductId !== null) {
            $sql .= ' AND product_id <> :product_id';
            $params['product_id'] = $exceptProductId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @return array<string, mixed>|null */
    public function detail(string $productId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM products WHERE product_id = :product_id LIMIT 1'
        );
        $statement->execute(['product_id' => $productId]);
        $product = $statement->fetch();
        if ($product === false) {
            return null;
        }

        $uoms = $this->pdo->prepare(
            'SELECT * FROM product_uoms
             WHERE product_id = :product_id
             ORDER BY is_active DESC, sort_order, conversion_to_base, uom_id'
        );
        $uoms->execute(['product_id' => $productId]);

        $images = $this->pdo->prepare(
            'SELECT * FROM product_images
             WHERE product_id = :product_id
             ORDER BY is_active DESC, sort_order, image_id'
        );
        $images->execute(['product_id' => $productId]);

        $inventory = $this->pdo->prepare(
            'SELECT COALESCE(SUM(qty_base_on_hand), 0) AS qty_base_on_hand,
                    COALESCE(SUM(qty_base_reserved), 0) AS qty_base_reserved,
                    COALESCE(SUM(qty_base_on_hand - qty_base_reserved), 0) AS qty_base_available,
                    MIN(CASE WHEN qty_base_on_hand - qty_base_reserved > 0 THEN expiry_date END) AS nearest_expiry_date
             FROM inventory_lots WHERE product_id = :product_id'
        );
        $inventory->execute(['product_id' => $productId]);

        $product['uoms'] = $uoms->fetchAll();
        $product['images'] = $images->fetchAll();
        $product['inventory'] = $inventory->fetch() ?: [
            'qty_base_on_hand' => 0,
            'qty_base_reserved' => 0,
            'qty_base_available' => 0,
            'nearest_expiry_date' => null,
        ];

        return $product;
    }

    /** @param array<string, mixed> $product */
    public function insertProduct(array $product): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO products (
                product_id, product_name, product_slug, category_id, category_label,
                default_source, short_description, full_description, ingredients,
                base_uom_label, shelf_life_value, shelf_life_unit, is_active
             ) VALUES (
                :product_id, :product_name, :product_slug, :category_id, :category_label,
                :default_source, :short_description, :full_description, :ingredients,
                :base_uom_label, :shelf_life_value, :shelf_life_unit, :is_active
             )'
        );
        $statement->execute($product);
    }

    /** @param array<string, mixed> $product */
    public function updateProduct(array $product): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE products SET
                product_name = :product_name,
                product_slug = :product_slug,
                category_id = :category_id,
                category_label = :category_label,
                default_source = :default_source,
                short_description = :short_description,
                full_description = :full_description,
                ingredients = :ingredients,
                base_uom_label = :base_uom_label,
                shelf_life_value = :shelf_life_value,
                shelf_life_unit = :shelf_life_unit,
                is_active = :is_active
             WHERE product_id = :product_id'
        );
        $statement->execute($product);
    }

    /** @return array<int, string> */
    public function uomIds(string $productId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT uom_id FROM product_uoms WHERE product_id = :product_id'
        );
        $statement->execute(['product_id' => $productId]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function uomOwner(string $uomId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT product_id FROM product_uoms WHERE uom_id = :uom_id LIMIT 1'
        );
        $statement->execute(['uom_id' => $uomId]);
        $owner = $statement->fetchColumn();

        return $owner === false ? null : (string) $owner;
    }

    /** @param array<string, mixed> $uom */
    public function insertUom(array $uom): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO product_uoms (
                uom_id, product_id, uom_label, conversion_to_base, unit_price_vnd,
                cost_price_vnd, is_base_unit, is_default, is_sellable,
                is_purchasable, is_active, sort_order, note
             ) VALUES (
                :uom_id, :product_id, :uom_label, :conversion_to_base, :unit_price_vnd,
                :cost_price_vnd, :is_base_unit, :is_default, :is_sellable,
                :is_purchasable, :is_active, :sort_order, :note
             )'
        );
        $statement->execute($uom);
    }

    /** @param array<string, mixed> $uom */
    public function updateUom(array $uom): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE product_uoms SET
                uom_label = :uom_label,
                conversion_to_base = :conversion_to_base,
                unit_price_vnd = :unit_price_vnd,
                cost_price_vnd = :cost_price_vnd,
                is_base_unit = :is_base_unit,
                is_default = :is_default,
                is_sellable = :is_sellable,
                is_purchasable = :is_purchasable,
                is_active = :is_active,
                sort_order = :sort_order,
                note = :note
             WHERE uom_id = :uom_id AND product_id = :product_id'
        );
        $statement->execute($uom);
    }

    /** @param array<int, string> $keptIds */
    public function deactivateMissingUoms(string $productId, array $keptIds): void
    {
        $sql = 'UPDATE product_uoms SET is_active = 0, is_default = 0, is_base_unit = 0
                WHERE product_id = :product_id';
        $params = ['product_id' => $productId];
        if ($keptIds !== []) {
            $placeholders = [];
            foreach ($keptIds as $index => $id) {
                $key = 'uom_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }
            $sql .= ' AND uom_id NOT IN (' . implode(', ', $placeholders) . ')';
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    /** @return array<int, int> */
    public function imageIds(string $productId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT image_id FROM product_images WHERE product_id = :product_id'
        );
        $statement->execute(['product_id' => $productId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param array<string, mixed> $image */
    public function insertImage(array $image): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO product_images (
                product_id, image_path, source_url, image_alt, is_base, is_active, sort_order
             ) VALUES (
                :product_id, :image_path, :source_url, :image_alt, :is_base, :is_active, :sort_order
             )'
        );
        $statement->execute($image);

        return (int) $this->pdo->lastInsertId();
    }

    public function clearActiveBaseImages(string $productId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE product_images
             SET is_base = 0
             WHERE product_id = :product_id AND is_active = 1'
        );
        $statement->execute(['product_id' => $productId]);
    }

    /** @return array<string, mixed>|null */
    public function image(int $imageId, string $productId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM product_images
             WHERE image_id = :image_id AND product_id = :product_id
             LIMIT 1'
        );
        $statement->execute([
            'image_id' => $imageId,
            'product_id' => $productId,
        ]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $image */
    public function updateImage(array $image): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE product_images SET
                image_path = :image_path,
                source_url = :source_url,
                image_alt = :image_alt,
                is_base = :is_base,
                is_active = :is_active,
                sort_order = :sort_order
             WHERE image_id = :image_id AND product_id = :product_id'
        );
        $statement->execute($image);
    }

    /** @param array<int, int> $keptIds */
    public function deactivateMissingImages(string $productId, array $keptIds): void
    {
        $sql = 'UPDATE product_images SET is_active = 0, is_base = 0
                WHERE product_id = :product_id';
        $params = ['product_id' => $productId];
        if ($keptIds !== []) {
            $placeholders = [];
            foreach ($keptIds as $index => $id) {
                $key = 'image_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }
            $sql .= ' AND image_id NOT IN (' . implode(', ', $placeholders) . ')';
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    public function setActive(string $productId, bool $isActive): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE products SET is_active = :is_active WHERE product_id = :product_id'
        );
        $statement->execute([
            'is_active' => $isActive ? 1 : 0,
            'product_id' => $productId,
        ]);

        return $statement->rowCount();
    }
}
