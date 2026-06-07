<?php

declare(strict_types=1);

namespace DacSanNhaDan\Repositories;

use PDO;

final class OrderRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $orderData
     */
    public function createOrder(array $orderData): string
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO orders
              (order_id, customer_id, status, customer_name, customer_phone, customer_address,
               receive_date, note, shipping_method, shipping_zone_id, shipping_fee_vnd,
               subtotal_vnd, total_vnd, source_summary)
             VALUES
              (:order_id, :customer_id, :status, :customer_name, :customer_phone, :customer_address,
               :receive_date, :note, :shipping_method, :shipping_zone_id, :shipping_fee_vnd,
               :subtotal_vnd, :total_vnd, :source_summary)'
        );

        $statement->execute([
            'order_id' => $orderData['order_id'],
            'customer_id' => $orderData['customer_id'] ?? null,
            'status' => $orderData['status'] ?? 'new',
            'customer_name' => $orderData['customer_name'],
            'customer_phone' => $orderData['customer_phone'],
            'customer_address' => $orderData['customer_address'] ?? null,
            'receive_date' => $orderData['receive_date'] ?? null,
            'note' => $orderData['note'] ?? null,
            'shipping_method' => $orderData['shipping_method'],
            'shipping_zone_id' => $orderData['shipping_zone_id'] ?? null,
            'shipping_fee_vnd' => $orderData['shipping_fee_vnd'],
            'subtotal_vnd' => $orderData['subtotal_vnd'],
            'total_vnd' => $orderData['total_vnd'],
            'source_summary' => $orderData['source_summary'],
        ]);

        return (string) $orderData['order_id'];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function createOrderItems(string $orderId, array $items): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO order_items
              (order_id, line_no, product_id, product_name_snapshot, uom_id,
               uom_label_snapshot, source_location, qty_uom, conversion_to_base_snapshot,
               qty_base, unit_price_vnd, line_total_vnd)
             VALUES
              (:order_id, :line_no, :product_id, :product_name_snapshot, :uom_id,
               :uom_label_snapshot, :source_location, :qty_uom, :conversion_to_base_snapshot,
               :qty_base, :unit_price_vnd, :line_total_vnd)'
        );

        $lineNo = 1;
        foreach ($items as $item) {
            $statement->execute([
                'order_id' => $orderId,
                'line_no' => $lineNo++,
                'product_id' => $item['product_id'],
                'product_name_snapshot' => $item['product_name'],
                'uom_id' => $item['uom_id'],
                'uom_label_snapshot' => $item['uom_label'],
                'source_location' => $item['source_location'],
                'qty_uom' => $item['qty_uom'],
                'conversion_to_base_snapshot' => $item['conversion_to_base'],
                'qty_base' => $item['qty_base'],
                'unit_price_vnd' => $item['unit_price_vnd'],
                'line_total_vnd' => $item['line_total_vnd'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOrderById(string $orderId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT order_id, customer_id, created_at, STATUS AS status, customer_name,
                    customer_phone, customer_address, receive_date, note, shipping_method,
                    shipping_zone_id, shipping_fee_vnd, subtotal_vnd, total_vnd,
                    source_summary, updated_at
             FROM orders
             WHERE order_id = :order_id
             LIMIT 1'
        );
        $statement->execute(['order_id' => $orderId]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOrderForUpdate(string $orderId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT order_id, customer_id, created_at, STATUS AS status, customer_name,
                    customer_phone, customer_address, receive_date, note, shipping_method,
                    shipping_zone_id, shipping_fee_vnd, subtotal_vnd, total_vnd,
                    source_summary, updated_at
             FROM orders
             WHERE order_id = :order_id
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute(['order_id' => $orderId]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrderItems(string $orderId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM order_items
             WHERE order_id = :order_id
             ORDER BY line_no'
        );
        $statement->execute(['order_id' => $orderId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrderItemsForUpdate(string $orderId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT order_item_id, order_id, line_no, product_id, product_name_snapshot,
                    uom_id, uom_label_snapshot, source_location, qty_uom,
                    conversion_to_base_snapshot, qty_base, unit_price_vnd,
                    line_total_vnd, allocated_lot_id, planned_plan_id, planned_at,
                    created_at
             FROM order_items
             WHERE order_id = :order_id
             ORDER BY line_no
             FOR UPDATE'
        );
        $statement->execute(['order_id' => $orderId]);

        return $statement->fetchAll();
    }

    public function updateOrderStatus(string $orderId, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE orders
             SET STATUS = :status
             WHERE order_id = :order_id'
        );
        $statement->execute([
            'status' => $status,
            'order_id' => $orderId,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findOrdersByPhone(string $phone, int $limit = 10): array
    {
        $limit = max(1, min($limit, 20));
        $statement = $this->pdo->prepare(
            "SELECT o.order_id, o.created_at, o.STATUS AS status, o.customer_name,
                    o.customer_phone, o.receive_date, o.shipping_method,
                    o.total_vnd, o.source_summary,
                    COUNT(oi.order_item_id) AS item_count
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.order_id
             WHERE o.customer_phone = :phone
             GROUP BY o.order_id
             ORDER BY o.created_at DESC, o.order_id DESC
             LIMIT $limit"
        );
        $statement->execute(['phone' => $phone]);

        return $statement->fetchAll();
    }
}
