<?php

declare(strict_types=1);

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Core\Csrf;
use DacSanNhaDan\Core\Database;
use DacSanNhaDan\Core\Response;
use DacSanNhaDan\Repositories\AdminDashboardRepository;
use DacSanNhaDan\Repositories\AdminUserRepository;
use DacSanNhaDan\Services\AdminAuthService;
use DacSanNhaDan\Services\AdminService;
use DacSanNhaDan\Support\Logger;

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $pdo = Database::connection();
    $auth = new AdminAuthService(new AdminUserRepository($pdo));
    $admin = new AdminService(new AdminDashboardRepository($pdo));
    $error = null;

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

    $page = admin_page();
    $data = admin_page_data($admin, $page);
    $csrfToken = Csrf::adminToken();

    ob_start();
    require dirname(__DIR__) . '/views/admin/layout.php';
    Response::html((string) ob_get_clean());
} catch (AppException $exception) {
    $error = $exception->getMessage();
    if (($auth ?? null) instanceof AdminAuthService && $auth->user() === null) {
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

function admin_page(): string
{
    $page = trim((string) ($_GET['page'] ?? 'dashboard'));
    $allowed = ['dashboard', 'orders', 'products', 'inventory', 'purchase-plans', 'settings'];

    return in_array($page, $allowed, true) ? $page : 'dashboard';
}

/**
 * @return array<string, mixed>
 */
function admin_page_data(AdminService $admin, string $page): array
{
    return match ($page) {
        'orders' => $admin->orders(admin_ui_filters()),
        'products' => $admin->products(admin_ui_filters()),
        'inventory' => $admin->inventory(admin_ui_filters()),
        'purchase-plans' => $admin->purchasePlans(admin_ui_filters()),
        'settings' => $admin->settings(),
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
