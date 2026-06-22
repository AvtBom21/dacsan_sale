<?php

declare(strict_types=1);

namespace DacSanNhaDan\Repositories;

use PDO;

final class ReviewRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function customerCanReview(int $customerId, string $orderId, string $productId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.order_id
             WHERE o.customer_id = :customer_id
               AND o.order_id = :order_id
               AND oi.product_id = :product_id
               AND o.STATUS = 'done'"
        );
        $statement->execute([
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'product_id' => $productId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function create(int $customerId, string $orderId, string $productId, int $rating, string $text): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO product_reviews
              (customer_id, order_id, product_id, rating, review_text, status)
             VALUES
              (:customer_id, :order_id, :product_id, :rating, :review_text, \'pending\')'
        );
        $statement->execute([
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'product_id' => $productId,
            'rating' => $rating,
            'review_text' => $text,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public function publicPositive(int $limit = 6): array
    {
        $limit = max(1, min($limit, 12));
        return $this->pdo->query(
            "SELECT r.review_id, r.rating, r.review_text, r.created_at,
                    c.customer_name, p.product_name
             FROM product_reviews r
             JOIN customers c ON c.customer_id = r.customer_id
             JOIN products p ON p.product_id = r.product_id
             WHERE r.status = 'approved'
               AND r.rating >= 4
             ORDER BY r.created_at DESC, r.review_id DESC
             LIMIT $limit"
        )->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function moderationList(string $status = ''): array
    {
        $where = '';
        $params = [];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $where = 'WHERE r.status = :status';
            $params['status'] = $status;
        }
        $statement = $this->pdo->prepare(
            "SELECT r.review_id, r.order_id, r.product_id, r.rating, r.review_text,
                    r.status, r.created_at, r.moderated_at,
                    c.customer_name, c.customer_phone, p.product_name
             FROM product_reviews r
             JOIN customers c ON c.customer_id = r.customer_id
             JOIN products p ON p.product_id = r.product_id
             $where
             ORDER BY FIELD(r.status, 'pending', 'approved', 'rejected'), r.created_at DESC"
        );
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function moderate(int $reviewId, string $status, int $adminId): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE product_reviews
             SET status = :status, moderated_by = :admin_id, moderated_at = CURRENT_TIMESTAMP
             WHERE review_id = :review_id'
        );
        $statement->execute([
            'status' => $status,
            'admin_id' => $adminId,
            'review_id' => $reviewId,
        ]);

        return $statement->rowCount() > 0;
    }
}
