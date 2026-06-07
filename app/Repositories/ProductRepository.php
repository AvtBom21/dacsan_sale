<?php

declare(strict_types=1);

namespace DacSanNhaDan\Repositories;

use PDO;

final class ProductRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array{category_id?: string, source?: string, q?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function productCards(array $filters = []): array
    {
        $sql = 'SELECT *
                FROM v_product_cards
                WHERE 1 = 1';
        $params = [];

        if (($filters['category_id'] ?? '') !== '') {
            $sql .= ' AND category_id = :category_id';
            $params['category_id'] = $filters['category_id'];
        }

        if (($filters['source'] ?? '') !== '') {
            $sql .= ' AND default_source = :source';
            $params['source'] = $filters['source'];
        }

        if (($filters['q'] ?? '') !== '') {
            $sql .= ' AND (
                product_name LIKE :q_name
                OR product_slug LIKE :q_slug
                OR category_label LIKE :q_category
                OR short_description LIKE :q_description
            )';
            $search = '%' . $filters['q'] . '%';
            $params['q_name'] = $search;
            $params['q_slug'] = $search;
            $params['q_category'] = $search;
            $params['q_description'] = $search;
        }

        $sql .= ' ORDER BY category_id, product_name';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeProductDetail(string $productId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM products
             WHERE product_id = :product_id
               AND is_active = 1
             LIMIT 1'
        );
        $statement->execute(['product_id' => $productId]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSellableUom(string $productId, string $uomId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.product_id, p.product_name, p.product_slug, p.category_id,
                    p.category_label, p.default_source, p.base_uom_label,
                    img.image_path AS base_image_path,
                    u.uom_id, u.uom_label, u.conversion_to_base, u.unit_price_vnd,
                    u.cost_price_vnd, u.is_base_unit, u.is_default
             FROM products p
             JOIN product_uoms u ON u.product_id = p.product_id
             LEFT JOIN product_images img
               ON img.product_id = p.product_id
              AND img.is_base = 1
              AND img.is_active = 1
             WHERE p.product_id = :product_id
               AND u.uom_id = :uom_id
               AND p.is_active = 1
               AND u.is_active = 1
               AND u.is_sellable = 1
             LIMIT 1'
        );
        $statement->execute([
            'product_id' => $productId,
            'uom_id' => $uomId,
        ]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<int, string> $productIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function sellableUomsByProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT product_id, uom_id, uom_label, conversion_to_base, unit_price_vnd,
                    cost_price_vnd, is_base_unit, is_default, is_sellable, is_purchasable,
                    is_active, sort_order, note
             FROM product_uoms
             WHERE product_id IN ($placeholders)
               AND is_active = 1
               AND is_sellable = 1
             ORDER BY product_id, is_default DESC, sort_order, conversion_to_base, uom_label"
        );
        $statement->execute(array_values($productIds));

        return $this->groupRowsByProduct($statement->fetchAll());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sellableUomsForProduct(string $productId): array
    {
        return $this->sellableUomsByProductIds([$productId])[$productId] ?? [];
    }

    /**
     * @param array<int, string> $productIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function activeImagesByProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT image_id, product_id, image_path, source_url, image_alt, is_base, sort_order
             FROM product_images
             WHERE product_id IN ($placeholders)
               AND is_active = 1
             ORDER BY product_id, sort_order, image_id"
        );
        $statement->execute(array_values($productIds));

        return $this->groupRowsByProduct($statement->fetchAll());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeImagesForProduct(string $productId): array
    {
        return $this->activeImagesByProductIds([$productId])[$productId] ?? [];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupRowsByProduct(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $productId = (string) $row['product_id'];
            $grouped[$productId][] = $row;
        }

        return $grouped;
    }
}
