<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;

final class AdminAuthorizationService
{
    /**
     * @var array<string, list<string>>
     */
    private const PERMISSIONS = [
        'owner' => ['*'],
        'admin' => [
            'dashboard.view',
            'orders.view',
            'orders.transition',
            'orders.print',
            'products.view',
            'products.manage',
            'inventory.view',
            'inventory.manage',
            'purchase_plans.view',
            'purchase_plans.manage',
            'settings.view',
            'settings.manage',
        ],
        'staff' => [
            'dashboard.view',
            'orders.view',
            'orders.transition',
            'orders.print',
            'purchase_plans.view',
            'purchase_plans.manage',
        ],
    ];

    public function allows(string $role, string $permission): bool
    {
        $permissions = self::PERMISSIONS[$role] ?? [];

        return in_array('*', $permissions, true)
            || in_array($permission, $permissions, true);
    }

    public function require(string $role, string $permission): void
    {
        if (!$this->allows($role, $permission)) {
            throw new AppException('Bạn không có quyền thực hiện thao tác này.', 403);
        }
    }
}
