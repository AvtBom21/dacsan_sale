<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Repositories\AdminSettingsRepository;
use PDO;
use Throwable;

final class AdminSettingsService
{
    private const EDITABLE_KEYS = [
        'store_name', 'store_phone', 'zalo_link', 'free_ship_threshold',
        'default_shipping_zone_id', 'bank_name', 'bank_account_number',
        'bank_account_holder', 'bank_transfer_content', 'bank_qr_image_path',
    ];

    public function __construct(
        private PDO $pdo,
        private AdminSettingsRepository $settings
    ) {
    }

    /** @return array<string, mixed> */
    public function page(): array
    {
        return [
            'settings' => $this->settings->settings(),
            'setting_values' => $this->settings->settingValues(),
            'shipping_zones' => $this->settings->zones(),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function updateSettings(array $payload): array
    {
        $raw = $payload['settings'] ?? $payload;
        if (!is_array($raw)) {
            throw new AppException('Danh sách cài đặt không hợp lệ.', 422);
        }
        $clean = [];
        foreach ($raw as $key => $value) {
            if (!is_string($key) || !in_array($key, self::EDITABLE_KEYS, true)) {
                throw new AppException('Cài đặt không được phép chỉnh sửa: ' . (string) $key, 422);
            }
            if (!is_scalar($value) && $value !== null) {
                throw new AppException('Giá trị cài đặt không hợp lệ.', 422);
            }
            $value = trim((string) $value);
            if (mb_strlen($value, 'UTF-8') > 1000) {
                throw new AppException('Giá trị cài đặt quá dài.', 422);
            }
            if ($key === 'free_ship_threshold') {
                $value = (string) $this->money($value, 'Ngưỡng miễn phí giao hàng');
            }
            if ($key === 'bank_qr_image_path' && $value !== '' && !UploadService::isSafeLocalImagePath($value)) {
                throw new AppException('Đường dẫn QR không hợp lệ.', 422);
            }
            if ($key === 'default_shipping_zone_id' && $value !== '') {
                $zone = $this->settings->zone($value);
                if ($zone === null || (int) $zone['is_active'] !== 1) {
                    throw new AppException('Vùng giao hàng mặc định không hợp lệ.', 422);
                }
            }
            $clean[$key] = $value;
        }

        $this->pdo->beginTransaction();
        try {
            foreach ($clean as $key => $value) {
                $this->settings->upsertSetting($key, $value);
            }
            if (isset($clean['default_shipping_zone_id']) && $clean['default_shipping_zone_id'] !== '') {
                $this->settings->clearDefaults();
                $this->settings->setDefault($clean['default_shipping_zone_id']);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }

        return $this->page();
    }

    /** @param array<string, mixed> $payload */
    public function saveZone(array $payload): array
    {
        $zoneId = $this->id($payload['zone_id'] ?? '');
        $name = $this->text($payload['zone_name'] ?? '', 120, 'Tên vùng');
        $fee = $this->money($payload['fee_vnd'] ?? 0, 'Phí giao hàng');
        $active = $this->flag($payload['is_active'] ?? 1);
        $default = $this->flag($payload['is_default'] ?? 0);
        if ($default === 1) $active = 1;

        $this->pdo->beginTransaction();
        try {
            if ($default === 1) $this->settings->clearDefaults();
            $this->settings->saveZone([
                'zone_id' => $zoneId,
                'zone_name' => $name,
                'fee_vnd' => $fee,
                'is_default' => $default,
                'is_active' => $active,
            ]);
            if ($default === 1) {
                $this->settings->upsertSetting('default_shipping_zone_id', $zoneId);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
        return $this->page();
    }

    public function setZoneDefault(string $zoneId): array
    {
        $zoneId = $this->id($zoneId);
        if ($this->settings->zone($zoneId) === null) {
            throw new AppException('Không tìm thấy vùng giao hàng.', 404);
        }
        $this->pdo->beginTransaction();
        try {
            $this->settings->clearDefaults();
            $this->settings->setDefault($zoneId);
            $this->settings->upsertSetting('default_shipping_zone_id', $zoneId);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
        return $this->page();
    }

    public function setZoneActive(string $zoneId, bool $active): array
    {
        $zoneId = $this->id($zoneId);
        $zone = $this->settings->zone($zoneId);
        if ($zone === null) throw new AppException('Không tìm thấy vùng giao hàng.', 404);

        $this->pdo->beginTransaction();
        try {
            if (!$active && (int) $zone['is_default'] === 1) {
                $replacement = $this->settings->firstOtherActiveZone($zoneId);
                if ($replacement === null) {
                    throw new AppException('Phải còn ít nhất một vùng giao hàng mặc định đang hoạt động.', 409);
                }
                $this->settings->clearDefaults();
                $this->settings->setDefault((string) $replacement['zone_id']);
                $this->settings->upsertSetting('default_shipping_zone_id', (string) $replacement['zone_id']);
            }
            $this->settings->setActive($zoneId, $active);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
        return $this->page();
    }

    private function id(mixed $value): string
    {
        if (!is_string($value)) throw new AppException('Mã vùng không hợp lệ.', 422);
        $value = trim($value);
        if ($value === '' || strlen($value) > 40 || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            throw new AppException('Mã vùng không hợp lệ.', 422);
        }
        return $value;
    }

    private function text(mixed $value, int $max, string $label): string
    {
        if (!is_scalar($value)) throw new AppException($label . ' không hợp lệ.', 422);
        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > $max) {
            throw new AppException($label . ' không hợp lệ.', 422);
        }
        return $value;
    }

    private function money(mixed $value, string $label): int
    {
        $valid = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
        if ($valid === false) throw new AppException($label . ' không hợp lệ.', 422);
        return (int) $valid;
    }

    private function flag(mixed $value): int
    {
        return ((int) $value) === 1 ? 1 : 0;
    }
}
