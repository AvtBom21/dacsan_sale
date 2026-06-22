<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\ReviewRepository;
use PDOException;

final class ReviewService
{
    public function __construct(private ReviewRepository $reviews)
    {
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function create(int $customerId, array $payload): array
    {
        $orderId = $this->id($payload['order_id'] ?? '', 'Mã đơn hàng');
        $productId = $this->id($payload['product_id'] ?? '', 'Mã sản phẩm');
        $rating = filter_var($payload['rating'] ?? null, FILTER_VALIDATE_INT);
        $text = trim((string) ($payload['review_text'] ?? ''));

        if ($rating === false || $rating < 1 || $rating > 5) {
            throw new AppException('Điểm đánh giá phải từ 1 đến 5.', 422);
        }
        if (mb_strlen($text, 'UTF-8') < 10 || mb_strlen($text, 'UTF-8') > 1000) {
            throw new AppException('Nội dung đánh giá phải có từ 10 đến 1000 ký tự.', 422);
        }
        if (!$this->reviews->customerCanReview($customerId, $orderId, $productId)) {
            throw new AppException('Bạn chỉ có thể đánh giá sản phẩm trong đơn đã hoàn tất.', 403);
        }

        try {
            $reviewId = $this->reviews->create($customerId, $orderId, $productId, (int) $rating, $text);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new AppException('Sản phẩm trong đơn này đã được đánh giá.', 409);
            }
            throw $exception;
        }

        return [
            'review_id' => $reviewId,
            'status' => 'pending',
            'message' => 'Đánh giá đã được gửi và đang chờ duyệt.',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function publicPositive(): array
    {
        return $this->reviews->publicPositive();
    }

    private function id(mixed $value, string $label): string
    {
        $id = trim((string) $value);
        if ($id === '' || preg_match('/^[A-Za-z0-9_-]{1,80}$/', $id) !== 1) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }
        return $id;
    }
}
