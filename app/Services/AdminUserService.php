<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\AdminUserRepository;
use PDO;
use Throwable;

final class AdminUserService
{
    private const ROLES = ['owner', 'admin', 'staff'];

    public function __construct(
        private PDO $pdo,
        private AdminUserRepository $users
    ) {
    }

    /** @return array<string, mixed> */
    public function page(): array
    {
        return ['users' => $this->users->listUsers(), 'roles' => self::ROLES];
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload): array
    {
        $username = $this->username($payload['username'] ?? '');
        if ($this->users->usernameExists($username)) {
            throw new AppException('Tên đăng nhập đã tồn tại.', 409);
        }
        $password = $this->password($payload['password'] ?? '');
        $id = $this->users->create([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'full_name' => $this->fullName($payload['full_name'] ?? ''),
            'role' => $this->role($payload['role'] ?? 'staff'),
            'is_active' => 1,
        ]);
        return $this->requiredUser($id);
    }

    /** @param array<string, mixed> $payload */
    public function update(array $payload): array
    {
        $adminId = $this->adminId($payload['admin_id'] ?? null);
        $role = $this->role($payload['role'] ?? '');
        $active = ((int) ($payload['is_active'] ?? 0)) === 1;
        $fullName = $this->fullName($payload['full_name'] ?? '');

        $this->pdo->beginTransaction();
        try {
            $current = $this->users->findByIdForUpdate($adminId);
            if ($current === null) throw new AppException('Không tìm thấy tài khoản.', 404);
            $activeOwnerIds = $this->users->activeOwnerIdsForUpdate();
            if (
                (string) $current['role'] === 'owner'
                && (int) $current['is_active'] === 1
                && (!$active || $role !== 'owner')
                && count($activeOwnerIds) <= 1
            ) {
                throw new AppException('Không thể vô hiệu hóa hoặc hạ cấp owner đang hoạt động cuối cùng.', 409);
            }
            $this->users->updateProfile($adminId, $fullName, $role, $active);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
        return $this->requiredUser($adminId);
    }

    public function resetPassword(mixed $adminIdValue, mixed $passwordValue): array
    {
        $adminId = $this->adminId($adminIdValue);
        $password = $this->password($passwordValue);
        $this->pdo->beginTransaction();
        try {
            if ($this->users->findByIdForUpdate($adminId) === null) {
                throw new AppException('Không tìm thấy tài khoản.', 404);
            }
            $this->users->updatePassword($adminId, password_hash($password, PASSWORD_DEFAULT));
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
        return $this->requiredUser($adminId);
    }

    /** @return array<string, mixed> */
    private function requiredUser(int $adminId): array
    {
        foreach ($this->users->listUsers() as $user) {
            if ((int) $user['admin_id'] === $adminId) return $user;
        }
        throw new AppException('Không tìm thấy tài khoản.', 404);
    }

    private function adminId(mixed $value): int
    {
        $valid = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($valid === false) throw new AppException('Mã tài khoản không hợp lệ.', 422);
        return (int) $valid;
    }

    private function username(mixed $value): string
    {
        if (!is_string($value)) throw new AppException('Tên đăng nhập không hợp lệ.', 422);
        $value = trim($value);
        if (strlen($value) < 3 || strlen($value) > 80 || preg_match('/^[A-Za-z0-9._-]+$/', $value) !== 1) {
            throw new AppException('Tên đăng nhập phải dài 3–80 ký tự và chỉ gồm chữ, số, . _ -.', 422);
        }
        return $value;
    }

    private function password(mixed $value): string
    {
        if (!is_string($value) || mb_strlen($value, 'UTF-8') < 10 || mb_strlen($value, 'UTF-8') > 200) {
            throw new AppException('Mật khẩu phải dài từ 10 đến 200 ký tự.', 422);
        }
        return $value;
    }

    private function fullName(mixed $value): string
    {
        if (!is_scalar($value)) throw new AppException('Họ tên không hợp lệ.', 422);
        $value = trim((string) $value);
        if (mb_strlen($value, 'UTF-8') > 160) throw new AppException('Họ tên quá dài.', 422);
        return $value;
    }

    private function role(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, self::ROLES, true)) {
            throw new AppException('Vai trò không hợp lệ.', 422);
        }
        return $value;
    }
}
