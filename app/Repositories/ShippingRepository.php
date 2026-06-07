<?php

declare(strict_types=1);

namespace DacSanNhaDan\Repositories;

use PDO;

final class ShippingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeZones(): array
    {
        $statement = $this->pdo->query(
            'SELECT zone_id, zone_name, fee_vnd, is_default
             FROM shipping_zones
             WHERE is_active = 1
             ORDER BY is_default DESC, fee_vnd, zone_name'
        );

        return array_map([$this, 'formatZone'], $statement->fetchAll());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function defaultZone(): ?array
    {
        $statement = $this->pdo->query(
            'SELECT zone_id, zone_name, fee_vnd, is_default
             FROM shipping_zones
             WHERE is_active = 1
             ORDER BY is_default DESC, fee_vnd, zone_name
             LIMIT 1'
        );

        $row = $statement->fetch();

        return $row === false ? null : $this->formatZone($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveZone(string $zoneId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT zone_id, zone_name, fee_vnd, is_default
             FROM shipping_zones
             WHERE zone_id = :zone_id
               AND is_active = 1
             LIMIT 1'
        );
        $statement->execute(['zone_id' => $zoneId]);

        $row = $statement->fetch();

        return $row === false ? null : $this->formatZone($row);
    }

    public function freeShipThreshold(): int
    {
        $statement = $this->pdo->prepare(
            "SELECT setting_value
             FROM settings
             WHERE setting_key = 'free_ship_threshold'
             LIMIT 1"
        );
        $statement->execute();

        return max(0, (int) $statement->fetchColumn());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatZone(array $row): array
    {
        return [
            'zone_id' => (string) $row['zone_id'],
            'zone_name' => (string) $row['zone_name'],
            'fee_vnd' => (int) $row['fee_vnd'],
            'is_default' => (int) $row['is_default'] === 1,
        ];
    }
}
