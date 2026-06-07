<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Repositories\ShippingRepository;

final class ShippingService
{
    public function __construct(private ShippingRepository $shipping)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function zones(): array
    {
        return $this->shipping->activeZones();
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'zones' => $this->zones(),
            'default_zone' => $this->shipping->defaultZone(),
            'free_ship_threshold' => $this->shipping->freeShipThreshold(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quote(?string $zoneId, int $subtotalVnd): array
    {
        $zone = $zoneId ? $this->shipping->findActiveZone($zoneId) : null;
        $zone ??= $this->shipping->defaultZone();

        $threshold = $this->shipping->freeShipThreshold();
        $subtotalVnd = max(0, $subtotalVnd);
        $baseFee = (int) ($zone['fee_vnd'] ?? 0);
        $fee = $threshold > 0 && $subtotalVnd >= $threshold ? 0 : $baseFee;

        return [
            'zone' => $zone,
            'subtotal_vnd' => $subtotalVnd,
            'base_fee_vnd' => $baseFee,
            'fee_vnd' => $fee,
            'free_ship_threshold' => $threshold,
            'free_ship_remaining_vnd' => max(0, $threshold - $subtotalVnd),
            'is_free_ship' => $threshold > 0 && $subtotalVnd >= $threshold,
        ];
    }
}
