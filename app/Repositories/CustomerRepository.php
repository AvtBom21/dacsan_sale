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
            'SELECT customer_id, customer_name, customer_phone, customer_address, note, created_at, updated_at
             FROM customers
             WHERE customer_phone = :phone
             LIMIT 1'
        );
        $statement->execute(['phone' => $phone]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
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
