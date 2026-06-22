<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\CustomerRepository;
use DacSanNhaDan\Support\Phone;

final class CustomerAuthService
{
    private const SESSION_KEY = 'customer_id';

    public function __construct(private CustomerRepository $customers)
    {
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function register(array $payload): array
    {
        $name = $this->name($payload['customer_name'] ?? '');
        $phone = $this->phone($payload['customer_phone'] ?? '');
        $address = $this->address($payload['customer_address'] ?? '');
        $password = $this->password($payload['password'] ?? '');
        $existing = $this->customers->findByPhone($phone);
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($existing !== null) {
            if (!empty($existing['password_hash'])) {
                throw new AppException('Số điện thoại này đã có tài khoản.', 409);
            }
            $customerId = (int) $existing['customer_id'];
            $this->customers->claimGuestAccount($customerId, [
                'customer_name' => $name,
                'customer_address' => $address,
                'password_hash' => $hash,
            ]);
        } else {
            $customerId = $this->customers->createAccount([
                'customer_name' => $name,
                'customer_phone' => $phone,
                'customer_address' => $address,
                'password_hash' => $hash,
            ]);
        }

        $this->startSession($customerId);

        return $this->requireCustomer();
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function login(array $payload): array
    {
        $phone = $this->phone($payload['customer_phone'] ?? '');
        $password = (string) ($payload['password'] ?? '');
        $customer = $this->customers->findByPhone($phone);

        if (
            $customer === null
            || (int) ($customer['is_active'] ?? 0) !== 1
            || empty($customer['password_hash'])
            || !password_verify($password, (string) $customer['password_hash'])
        ) {
            throw new AppException('Số điện thoại hoặc mật khẩu không đúng.', 401);
        }

        $customerId = (int) $customer['customer_id'];
        $this->customers->touchLastLogin($customerId);
        $this->startSession($customerId);

        return $this->requireCustomer();
    }

    /** @return array<string, mixed>|null */
    public function customer(): ?array
    {
        $customerId = (int) ($_SESSION[self::SESSION_KEY] ?? 0);
        if ($customerId < 1) {
            return null;
        }

        $customer = $this->customers->findById($customerId);
        if ($customer === null || (int) ($customer['is_active'] ?? 0) !== 1) {
            unset($_SESSION[self::SESSION_KEY]);
            return null;
        }

        return $this->publicCustomer($customer);
    }

    /** @return array<string, mixed> */
    public function requireCustomer(): array
    {
        $customer = $this->customer();
        if ($customer === null) {
            throw new AppException('Vui lòng đăng nhập để tiếp tục.', 401);
        }

        return $customer;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateProfile(array $payload): array
    {
        $customer = $this->requireCustomer();
        $this->customers->updateProfile(
            (int) $customer['customer_id'],
            $this->name($payload['customer_name'] ?? ''),
            $this->address($payload['customer_address'] ?? '')
        );

        return $this->requireCustomer();
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }

    private function startSession(int $customerId): void
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $customerId;
        $this->customers->touchLastLogin($customerId);
    }

    private function name(mixed $value): string
    {
        $name = trim((string) $value);
        if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 160) {
            throw new AppException('Họ tên khách hàng không hợp lệ.', 422);
        }
        return $name;
    }

    private function phone(mixed $value): string
    {
        $phone = Phone::normalize($value);
        if (!Phone::isValidVietnamPhone($phone)) {
            throw new AppException('Số điện thoại chưa hợp lệ.', 422);
        }
        return $phone;
    }

    private function address(mixed $value): string
    {
        $address = trim((string) $value);
        if (mb_strlen($address, 'UTF-8') > 255) {
            throw new AppException('Địa chỉ quá dài.', 422);
        }
        return $address;
    }

    private function password(mixed $value): string
    {
        $password = (string) $value;
        if (strlen($password) < 8 || strlen($password) > 72) {
            throw new AppException('Mật khẩu phải có từ 8 đến 72 ký tự.', 422);
        }
        return $password;
    }

    /** @param array<string, mixed> $customer @return array<string, mixed> */
    private function publicCustomer(array $customer): array
    {
        return [
            'customer_id' => (int) $customer['customer_id'],
            'customer_name' => (string) $customer['customer_name'],
            'customer_phone' => (string) $customer['customer_phone'],
            'customer_address' => (string) ($customer['customer_address'] ?? ''),
            'last_login_at' => $customer['last_login_at'] ?? null,
        ];
    }
}
