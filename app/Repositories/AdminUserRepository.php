<?php

declare(strict_types=1);

namespace DacSanNhaDan\Repositories;

use PDO;

final class AdminUserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByUsername(string $username): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT admin_id, username, password_hash, full_name, role, is_active,
                    created_at, updated_at
             FROM admin_users
             WHERE username = :username
               AND is_active = 1
             LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveById(int $adminId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT admin_id, username, full_name, role, is_active,
                    created_at, updated_at
             FROM admin_users
             WHERE admin_id = :admin_id
               AND is_active = 1
             LIMIT 1'
        );
        $statement->execute(['admin_id' => $adminId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
