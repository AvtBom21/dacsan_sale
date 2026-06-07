<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\AdminUserRepository;

final class AdminAuthService
{
    private const SESSION_KEY = 'admin_user';

    public function __construct(private AdminUserRepository $users)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function login(string $username, string $password): array
    {
        $this->ensureSession();
        $username = trim($username);

        if ($username === '' || $password === '') {
            throw new AppException('Vui lòng nhập tài khoản và mật khẩu.', 422);
        }

        $user = $this->users->findActiveByUsername($username);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            throw new AppException('Tài khoản hoặc mật khẩu không đúng.', 401);
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $this->publicUser($user);

        return $_SESSION[self::SESSION_KEY];
    }

    public function logout(): void
    {
        $this->ensureSession();
        unset($_SESSION[self::SESSION_KEY]);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        $this->ensureSession();

        if (empty($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            return null;
        }

        $adminId = (int) ($_SESSION[self::SESSION_KEY]['admin_id'] ?? 0);
        if ($adminId <= 0) {
            unset($_SESSION[self::SESSION_KEY]);
            return null;
        }

        $fresh = $this->users->findActiveById($adminId);
        if ($fresh === null) {
            unset($_SESSION[self::SESSION_KEY]);
            return null;
        }

        $_SESSION[self::SESSION_KEY] = $this->publicUser($fresh);

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * @return array<string, mixed>
     */
    public function requireUser(): array
    {
        $user = $this->user();
        if ($user === null) {
            throw new AppException('Vui lòng đăng nhập quản trị.', 401);
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function publicUser(array $user): array
    {
        return [
            'admin_id' => (int) $user['admin_id'],
            'username' => (string) $user['username'],
            'full_name' => (string) ($user['full_name'] ?? ''),
            'role' => (string) $user['role'],
            'is_superadmin' => in_array((string) $user['role'], ['owner', 'superadmin'], true),
        ];
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
