<?php

declare(strict_types=1);

namespace DacSanNhaDan\Support;

use PDO;

final class DatabaseBaseline
{
    public const CORE_TABLES = [
        'settings',
        'shipping_zones',
        'categories',
        'products',
        'product_uoms',
        'product_images',
        'customers',
        'orders',
        'order_items',
        'inventory_lots',
        'inventory_movements',
        'order_item_allocations',
        'purchase_plans',
        'purchase_plan_orders',
        'plan_items',
        'purchase_plan_receipts',
        'purchase_plan_receipt_items',
        'admin_users',
    ];

    public const CORE_VIEWS = [
        'v_product_cards',
        'v_inventory_summary',
    ];

    public const SEEDED_TABLES = [
        'settings',
        'shipping_zones',
        'categories',
        'products',
        'product_uoms',
        'product_images',
        'inventory_lots',
        'inventory_movements',
        'admin_users',
    ];

    /**
     * @return array{
     *     database: string,
     *     table_count: int,
     *     view_count: int,
     *     expected_table_count: int,
     *     expected_view_count: int,
     *     missing_tables: array<int, string>,
     *     missing_views: array<int, string>,
     *     seed_counts: array<string, int>,
     *     empty_seed_tables: array<int, string>
     * }
     */
    public static function inspect(PDO $pdo, string $database): array
    {
        $tables = self::objectNames($pdo, $database, 'BASE TABLE');
        $views = self::objectNames($pdo, $database, 'VIEW');
        $seedCounts = self::seedCounts($pdo, $tables);

        return [
            'database' => $database,
            'table_count' => count($tables),
            'view_count' => count($views),
            'expected_table_count' => count(self::CORE_TABLES),
            'expected_view_count' => count(self::CORE_VIEWS),
            'missing_tables' => self::missing(self::CORE_TABLES, $tables),
            'missing_views' => self::missing(self::CORE_VIEWS, $views),
            'seed_counts' => $seedCounts,
            'empty_seed_tables' => self::emptySeedTables($seedCounts),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function objectNames(PDO $pdo, string $database, string $type): array
    {
        $statement = $pdo->prepare(
            'SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :database
               AND TABLE_TYPE = :type
             ORDER BY TABLE_NAME'
        );

        $statement->execute([
            'database' => $database,
            'type' => $type,
        ]);

        $names = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_map('strval', $names);
    }

    /**
     * @param array<int, string> $expected
     * @param array<int, string> $actual
     * @return array<int, string>
     */
    private static function missing(array $expected, array $actual): array
    {
        $actualLookup = array_fill_keys($actual, true);
        $missing = [];

        foreach ($expected as $name) {
            if (!isset($actualLookup[$name])) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * @return array<string, int>
     */
    private static function seedCounts(PDO $pdo, array $tables): array
    {
        $counts = [];
        $tableLookup = array_fill_keys($tables, true);

        foreach (self::SEEDED_TABLES as $table) {
            if (!isset($tableLookup[$table])) {
                $counts[$table] = 0;
                continue;
            }

            $statement = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`');
            $counts[$table] = (int) $statement->fetchColumn();
        }

        return $counts;
    }

    /**
     * @param array<string, int> $seedCounts
     * @return array<int, string>
     */
    private static function emptySeedTables(array $seedCounts): array
    {
        $empty = [];

        foreach ($seedCounts as $table => $count) {
            if ($count <= 0) {
                $empty[] = $table;
            }
        }

        return $empty;
    }
}
