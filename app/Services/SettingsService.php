<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Repositories\SettingRepository;

final class SettingsService
{
    private const PUBLIC_KEYS = [
        'store_name',
        'store_phone',
        'zalo_link',
        'free_ship_threshold',
        'default_shipping_zone_id',
    ];

    public function __construct(private SettingRepository $settings)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function publicSettings(): array
    {
        $all = $this->settings->allKeyValue();
        $public = [];

        foreach (self::PUBLIC_KEYS as $key) {
            if (array_key_exists($key, $all)) {
                $public[$key] = $key === 'free_ship_threshold' ? (int) $all[$key] : $all[$key];
            }
        }

        $public['locale'] = 'vi';
        $public['currency'] = 'VND';

        return $public;
    }
}
