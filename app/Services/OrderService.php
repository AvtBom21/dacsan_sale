<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\OrderRepository;
use PDO;
use Throwable;

final class OrderService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        'new' => ['confirmed', 'cancelled'],
        'confirmed' => ['ordered', 'cancelled'],
        'ordered' => ['received', 'cancelled'],
        'received' => ['ready', 'cancelled'],
        'ready' => ['done', 'cancelled'],
        'done' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private PDO $pdo,
        private OrderRepository $orders,
        private InventoryService $inventory
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedTransitions(string $status): array
    {
        return self::allowedTransitionsFor($status);
    }

    /**
     * @return array<int, string>
     */
    public static function allowedTransitionsFor(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public function isTransitionAllowed(string $from, string $to): bool
    {
        return in_array($to, $this->getAllowedTransitions($from), true);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function changeStatus(string $orderId, string $newStatus, array $options = []): array
    {
        unset($options);

        if (!array_key_exists($newStatus, self::TRANSITIONS)) {
            throw new AppException('Trạng thái mới không hợp lệ.', 422);
        }

        $this->pdo->beginTransaction();

        try {
            $order = $this->orders->findOrderForUpdate($orderId);

            if ($order === null) {
                throw new AppException('Không tìm thấy đơn hàng.', 404);
            }

            $currentStatus = (string) $order['status'];
            if (!$this->isTransitionAllowed($currentStatus, $newStatus)) {
                throw new AppException('Chuyển trạng thái đơn hàng không hợp lệ.', 409);
            }

            if ($newStatus === 'done') {
                $items = $this->orders->getOrderItemsForUpdate($orderId);
                $this->inventory->completeOrderFefo($orderId, $items);
            }

            $this->orders->updateOrderStatus($orderId, $newStatus);
            $this->pdo->commit();

            return [
                'order_id' => $orderId,
                'from_status' => $currentStatus,
                'status' => $newStatus,
                'allowed_next_statuses' => $this->getAllowedTransitions($newStatus),
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
