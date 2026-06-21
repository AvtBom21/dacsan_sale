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

    /** @return array<int, array<string, mixed>> */
    public function listUsers(): array
    {
        return $this->pdo->query(
            'SELECT admin_id, username, full_name, role, is_active, created_at, updated_at
             FROM admin_users ORDER BY is_active DESC, role, username'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findByIdForUpdate(int $adminId): ?array
    {
        $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $statement = $this->pdo->prepare(
            'SELECT admin_id, username, full_name, role, is_active
             FROM admin_users WHERE admin_id = :admin_id LIMIT 1' . $lock
        );
        $statement->execute(['admin_id' => $adminId]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function usernameExists(string $username, ?int $exceptAdminId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM admin_users WHERE username = :username';
        $params = ['username' => $username];
        if ($exceptAdminId !== null) {
            $sql .= ' AND admin_id <> :admin_id';
            $params['admin_id'] = $exceptAdminId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return (int) $statement->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $user */
    public function create(array $user): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO admin_users
              (username, password_hash, full_name, role, is_active)
             VALUES (:username, :password_hash, :full_name, :role, :is_active)'
        );
        $statement->execute($user);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateProfile(int $adminId, string $fullName, string $role, bool $active): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE admin_users
             SET full_name = :full_name, role = :role, is_active = :is_active
             WHERE admin_id = :admin_id'
        );
        $statement->execute([
            'full_name' => $fullName,
            'role' => $role,
            'is_active' => $active ? 1 : 0,
            'admin_id' => $adminId,
        ]);
    }

    public function updatePassword(int $adminId, string $hash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE admin_users SET password_hash = :hash WHERE admin_id = :admin_id'
        );
        $statement->execute(['hash' => $hash, 'admin_id' => $adminId]);
    }

    public function countActiveOwners(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM admin_users WHERE role = 'owner' AND is_active = 1"
        )->fetchColumn();
    }

    /** @return array<int, int> */
    public function activeOwnerIdsForUpdate(): array
    {
        $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $rows = $this->pdo->query(
            "SELECT admin_id FROM admin_users
             WHERE role = 'owner' AND is_active = 1
             ORDER BY admin_id" . $lock
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $rows);
    }
}
