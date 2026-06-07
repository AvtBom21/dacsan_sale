<?php

declare(strict_types=1);

namespace DacSanNhaDan\Repositories;

use PDO;

final class SettingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<string, string>
     */
    public function allKeyValue(): array
    {
        $statement = $this->pdo->query('SELECT setting_key, setting_value FROM settings ORDER BY setting_key');
        $settings = [];

        foreach ($statement->fetchAll() as $row) {
            $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }

        return $settings;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT setting_value FROM settings WHERE setting_key = :setting_key LIMIT 1'
        );
        $statement->execute(['setting_key' => $key]);

        $value = $statement->fetchColumn();

        return $value === false ? $default : (string) $value;
    }
}
