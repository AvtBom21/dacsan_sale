<?php

declare(strict_types=1);

namespace DacSanNhaDan\Repositories;

use PDO;

final class AdminSettingsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, string> */
    public function settingValues(): array
    {
        $values = [];
        foreach ($this->pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll() as $row) {
            $values[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
        return $values;
    }

    /** @return array<int, array<string, mixed>> */
    public function settings(): array
    {
        return $this->pdo->query(
            'SELECT setting_key, setting_value, note, updated_at
             FROM settings ORDER BY setting_key'
        )->fetchAll();
    }

    public function upsertSetting(string $key, string $value, ?string $note = null): void
    {
        $exists = $this->pdo->prepare('SELECT COUNT(*) FROM settings WHERE setting_key = :key');
        $exists->execute(['key' => $key]);
        if ((int) $exists->fetchColumn() === 0) {
            $insert = $this->pdo->prepare(
                'INSERT INTO settings (setting_key, setting_value, note)
                 VALUES (:key, :value, :note)'
            );
            $insert->execute(['key' => $key, 'value' => $value, 'note' => $note]);
            return;
        }
        $statement = $this->pdo->prepare(
            'UPDATE settings SET setting_value = :value WHERE setting_key = :key'
        );
        $statement->execute(['value' => $value, 'key' => $key]);
    }

    /** @return array<int, array<string, mixed>> */
    public function zones(): array
    {
        return $this->pdo->query(
            'SELECT zone_id, zone_name, fee_vnd, is_default, is_active,
                    created_at, updated_at
             FROM shipping_zones
             ORDER BY is_default DESC, is_active DESC, zone_name'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function zone(string $zoneId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM shipping_zones WHERE zone_id = :zone_id LIMIT 1'
        );
        $statement->execute(['zone_id' => $zoneId]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $zone */
    public function saveZone(array $zone): void
    {
        if ($this->zone((string) $zone['zone_id']) === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO shipping_zones
                  (zone_id, zone_name, fee_vnd, is_default, is_active)
                 VALUES (:zone_id, :zone_name, :fee_vnd, :is_default, :is_active)'
            );
        } else {
            $statement = $this->pdo->prepare(
                'UPDATE shipping_zones
                 SET zone_name = :zone_name, fee_vnd = :fee_vnd,
                     is_default = :is_default, is_active = :is_active
                 WHERE zone_id = :zone_id'
            );
        }
        $statement->execute($zone);
    }

    public function clearDefaults(): void
    {
        $this->pdo->exec('UPDATE shipping_zones SET is_default = 0');
    }

    public function setDefault(string $zoneId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE shipping_zones SET is_default = 1, is_active = 1 WHERE zone_id = :zone_id'
        );
        $statement->execute(['zone_id' => $zoneId]);
    }

    public function setActive(string $zoneId, bool $active): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE shipping_zones SET is_active = :active WHERE zone_id = :zone_id'
        );
        $statement->execute(['active' => $active ? 1 : 0, 'zone_id' => $zoneId]);
    }

    /** @return array<string, mixed>|null */
    public function firstOtherActiveZone(string $exceptZoneId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM shipping_zones
             WHERE zone_id <> :zone_id AND is_active = 1
             ORDER BY zone_name LIMIT 1'
        );
        $statement->execute(['zone_id' => $exceptZoneId]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }
}
