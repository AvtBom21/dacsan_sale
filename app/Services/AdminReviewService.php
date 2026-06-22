<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\ReviewRepository;

final class AdminReviewService
{
    public function __construct(private ReviewRepository $reviews)
    {
    }

    /** @return array<string, mixed> */
    public function page(string $status = ''): array
    {
        return [
            'reviews' => $this->reviews->moderationList($status),
            'status' => $status,
        ];
    }

    public function moderate(mixed $reviewId, string $status, int $adminId): array
    {
        $id = filter_var($reviewId, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new AppException('Mã đánh giá không hợp lệ.', 422);
        }
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new AppException('Trạng thái đánh giá không hợp lệ.', 422);
        }
        if (!$this->reviews->moderate((int) $id, $status, $adminId)) {
            throw new AppException('Không tìm thấy đánh giá.', 404);
        }

        return ['message' => 'Đã cập nhật trạng thái đánh giá.'];
    }
}
