<?php

declare(strict_types=1);

namespace DacSanNhaDan\Core;

final class Csrf
{
    private const CHECKOUT_TOKEN_KEY = 'checkout_token';
    private const ADMIN_TOKEN_KEY = 'admin_csrf_token';

    public static function checkoutToken(bool $refresh = false): string
    {
        self::ensureSession();

        if ($refresh || empty($_SESSION[self::CHECKOUT_TOKEN_KEY])) {
            $_SESSION[self::CHECKOUT_TOKEN_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::CHECKOUT_TOKEN_KEY];
    }

    public static function verifyCheckoutToken(string $token): bool
    {
        self::ensureSession();

        if ($token === '' || empty($_SESSION[self::CHECKOUT_TOKEN_KEY])) {
            return false;
        }

        return hash_equals((string) $_SESSION[self::CHECKOUT_TOKEN_KEY], $token);
    }

    public static function requireCheckoutToken(string $token): void
    {
        if (!self::verifyCheckoutToken($token)) {
            throw new AppException('Phiên checkout không hợp lệ hoặc đã hết hạn.', 419);
        }
    }

    public static function regenerateCheckoutToken(): string
    {
        return self::checkoutToken(true);
    }

    public static function adminToken(bool $refresh = false): string
    {
        self::ensureSession();

        if ($refresh || empty($_SESSION[self::ADMIN_TOKEN_KEY])) {
            $_SESSION[self::ADMIN_TOKEN_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::ADMIN_TOKEN_KEY];
    }

    public static function verifyAdminToken(string $token): bool
    {
        self::ensureSession();

        if ($token === '' || empty($_SESSION[self::ADMIN_TOKEN_KEY])) {
            return false;
        }

        return hash_equals((string) $_SESSION[self::ADMIN_TOKEN_KEY], $token);
    }

    public static function requireAdminToken(string $token): void
    {
        if (!self::verifyAdminToken($token)) {
            throw new AppException('Phiên quản trị không hợp lệ hoặc đã hết hạn.', 419);
        }
    }

    public static function regenerateAdminToken(): string
    {
        return self::adminToken(true);
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
