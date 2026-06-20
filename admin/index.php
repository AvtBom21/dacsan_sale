<?php

declare(strict_types=1);

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Core\Csrf;
use DacSanNhaDan\Core\Database;
use DacSanNhaDan\Core\Response;
use DacSanNhaDan\Repositories\AdminDashboardRepository;
use DacSanNhaDan\Repositories\AdminUserRepository;
use DacSanNhaDan\Services\AdminAuthorizationService;
use DacSanNhaDan\Services\AdminAuthService;
use DacSanNhaDan\Services\AdminService;
use DacSanNhaDan\Support\Logger;

/**
 * @return array<string, string>
 */
function admin_page_permissions(): array
{
    return [
        'dashboard' => 'dashboard.view',
        'orders' => 'orders.view',
        'order-detail' => 'orders.view',
        'products' => 'products.view',
        'product-detail' => 'products.view',
        'product-form' => 'products.manage',
        'inventory' => 'inventory.view',
        'inventory-lot' => 'inventory.view',
        'purchase-plans' => 'purchase_plans.view',
        'purchase-plan-detail' => 'purchase_plans.view',
        'settings' => 'settings.view',
        'admin-users' => 'admin_users.manage',
    ];
}

function admin_page_from_value(mixed $value): string
{
    if (!is_string($value)) {
        return 'dashboard';
    }

    $page = trim((string) $value);

    return array_key_exists($page, admin_page_permissions()) ? $page : 'dashboard';
}

function admin_page_permission(string $page): string
{
    return admin_page_permissions()[$page] ?? 'dashboard.view';
}

function admin_page_id_from_value(string $page, mixed $value): ?string
{
    $requiredPages = [
        'order-detail',
        'product-detail',
        'inventory-lot',
        'purchase-plan-detail',
    ];
    $optionalPages = ['product-form'];

    if (!in_array($page, [...$requiredPages, ...$optionalPages], true)) {
        return null;
    }

    if ($value !== null && !is_string($value) && !is_int($value)) {
        throw new AppException('Mã đối tượng quản trị không hợp lệ.', 422);
    }

    $id = trim((string) $value);
    if ($id === '' && in_array($page, $optionalPages, true)) {
        return null;
    }

    if ($id === '' || preg_match('/^[A-Za-z0-9_-]{1,80}$/', $id) !== 1) {
        throw new AppException('Mã đối tượng quản trị không hợp lệ.', 422);
    }

    return $id;
}

function admin_require_page_permission(
    AdminAuthorizationService $authorization,
    string $role,
    string $page
): void {
    $authorization->require($role, admin_page_permission($page));
}

/**
 * @return array<string, bool>
 */
function admin_navigation_capabilities(
    AdminAuthorizationService $authorization,
    string $role
): array {
    return [
        'dashboard' => $authorization->allows($role, 'dashboard.view'),
        'orders' => $authorization->allows($role, 'orders.view'),
        'products' => $authorization->allows($role, 'products.view'),
        'inventory' => $authorization->allows($role, 'inventory.view'),
        'purchase_plans' => $authorization->allows($role, 'purchase_plans.view'),
        'settings' => $authorization->allows($role, 'settings.view'),
        'admin_users' => $authorization->allows($role, 'admin_users.manage'),
    ];
}

if (defined('DSND_ADMIN_HELPERS_ONLY') && DSND_ADMIN_HELPERS_ONLY === true) {
    return;
}

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $pdo = Database::connection();
    $authorization = new AdminAuthorizationService();
    $auth = new AdminAuthService(new AdminUserRepository($pdo), $authorization);
    $admin = new AdminService(new AdminDashboardRepository($pdo));
    $error = null;
    $user = null;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = trim((string) ($_POST['admin_action'] ?? ''));
        Csrf::requireAdminToken((string) ($_POST['csrf_token'] ?? ''));

        if ($action === 'login') {
            $auth->login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
            header('Location: ./');
            exit;
        }

        if ($action === 'logout') {
            $auth->logout();
            header('Location: ./');
            exit;
        }
    }

    $user = $auth->user();
    if ($user === null) {
        $csrfToken = Csrf::adminToken();
        ob_start();
        require dirname(__DIR__) . '/views/admin/login.php';
        Response::html((string) ob_get_clean());
        return;
    }

    $page = admin_page_from_value($_GET['page'] ?? 'dashboard');
    $role = (string) $user['role'];
    admin_require_page_permission($authorization, $role, $page);
    $pageId = admin_page_id_from_value($page, $_GET['id'] ?? null);
    $data = admin_page_data($admin, $page, $pageId);
    $capabilities = admin_navigation_capabilities($authorization, $role);
    $csrfToken = Csrf::adminToken();

    ob_start();
    require dirname(__DIR__) . '/views/admin/layout.php';
    Response::html((string) ob_get_clean());
} catch (AppException $exception) {
    $error = $exception->getMessage();
    if (($auth ?? null) instanceof AdminAuthService && ($user ?? null) === null) {
        $csrfToken = Csrf::adminToken();
        ob_start();
        require dirname(__DIR__) . '/views/admin/login.php';
        Response::html((string) ob_get_clean(), $exception->httpStatus());
        return;
    }

    Logger::error($exception->getMessage(), ['admin_page' => $_GET['page'] ?? 'dashboard']);
    Response::html('<h1>Lỗi quản trị</h1><p>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>', $exception->httpStatus());
} catch (Throwable $exception) {
    Logger::error($exception->getMessage(), [
        'admin_page' => $_GET['page'] ?? 'dashboard',
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);

    Response::html('<h1>Lỗi hệ thống quản trị</h1><p>Vui lòng thử lại sau.</p>', 500);
}

/**
 * @return array<string, mixed>
 */
function admin_page_data(AdminService $admin, string $page, ?string $pageId = null): array
{
    return match ($page) {
        'orders' => $admin->orders(admin_ui_filters()),
        'products' => $admin->products(admin_ui_filters()),
        'inventory' => $admin->inventory(admin_ui_filters()),
        'purchase-plans' => $admin->purchasePlans(admin_ui_filters()),
        'settings' => $admin->settings(),
        'order-detail',
        'product-detail',
        'product-form',
        'inventory-lot',
        'purchase-plan-detail',
        'admin-users' => [
            'id' => $pageId,
            'page' => $page,
            'placeholder' => true,
        ],
        default => $admin->dashboard(),
    };
}

/**
 * @return array<string, mixed>
 */
function admin_ui_filters(): array
{
    $filters = [];
    foreach (['status', 'q', 'date_from', 'date_to', 'is_active', 'limit'] as $key) {
        if (isset($_GET[$key]) && trim((string) $_GET[$key]) !== '') {
            $filters[$key] = trim((string) $_GET[$key]);
        }
    }

    return $filters;
}
