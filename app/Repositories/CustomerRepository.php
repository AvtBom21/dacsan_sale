<?php

declare(strict_types=1);

namespace DacSanNhaDan\Repositories;

use PDO;

final class CustomerRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPhone(string $phone): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT customer_id, customer_name, customer_phone, customer_address,
                    password_hash, is_active, last_login_at, note, created_at, updated_at
             FROM customers
             WHERE customer_phone = :phone
             LIMIT 1'
        );
        $statement->execute(['phone' => $phone]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $customerId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT customer_id, customer_name, customer_phone, customer_address,
                    password_hash, is_active, last_login_at, note, created_at, updated_at
             FROM customers
             WHERE customer_id = :customer_id
             LIMIT 1'
        );
        $statement->execute(['customer_id' => $customerId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @param array{customer_name: string, customer_phone: string, customer_address: string, password_hash: string} $data */
    public function createAccount(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO customers
              (customer_name, customer_phone, customer_address, password_hash, is_active)
             VALUES
              (:customer_name, :customer_phone, :customer_address, :password_hash, 1)'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array{customer_name: string, customer_address: string, password_hash: string} $data */
    public function claimGuestAccount(int $customerId, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE customers
             SET customer_name = :customer_name,
                 customer_address = :customer_address,
                 password_hash = :password_hash,
                 is_active = 1
             WHERE customer_id = :customer_id
               AND (password_hash IS NULL OR password_hash = \'\')'
        );
        $statement->execute(array_merge(
            ['customer_id' => $customerId],
            $data
        ));
    }

    public function updateProfile(int $customerId, string $name, string $address): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE customers
             SET customer_name = :customer_name,
                 customer_address = :customer_address
             WHERE customer_id = :customer_id'
        );
        $statement->execute([
            'customer_id' => $customerId,
            'customer_name' => $name,
            'customer_address' => $address,
        ]);
    }

    public function touchLastLogin(int $customerId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE customers SET last_login_at = CURRENT_TIMESTAMP WHERE customer_id = :customer_id'
        );
        $statement->execute(['customer_id' => $customerId]);
    }

    /**
     * @param array{customer_name: string, customer_phone: string, customer_address?: string|null, note?: string|null} $data
     */
    public function upsertCustomer(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO customers (customer_name, customer_phone, customer_address, note)
             VALUES (:customer_name, :customer_phone, :customer_address, :note)
             ON DUPLICATE KEY UPDATE
               customer_id = LAST_INSERT_ID(customer_id),
               customer_name = VALUES(customer_name),
               customer_address = VALUES(customer_address),
               note = COALESCE(VALUES(note), note),
               updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_address' => $data['customer_address'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
