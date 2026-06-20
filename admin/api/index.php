<?php

declare(strict_types=1);

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Core\Csrf;
use DacSanNhaDan\Core\Database;
use DacSanNhaDan\Core\Request;
use DacSanNhaDan\Core\Response;
use DacSanNhaDan\Repositories\AdminDashboardRepository;
use DacSanNhaDan\Repositories\AdminProductRepository;
use DacSanNhaDan\Repositories\AdminUserRepository;
use DacSanNhaDan\Repositories\CustomerRepository;
use DacSanNhaDan\Repositories\InventoryRepository;
use DacSanNhaDan\Repositories\OrderRepository;
use DacSanNhaDan\Repositories\ProductRepository;
use DacSanNhaDan\Repositories\PurchasePlanRepository;
use DacSanNhaDan\Repositories\SettingRepository;
use DacSanNhaDan\Repositories\ShippingRepository;
use DacSanNhaDan\Services\AdminAuthorizationService;
use DacSanNhaDan\Services\AdminAuthService;
use DacSanNhaDan\Services\AdminProductService;
use DacSanNhaDan\Services\AdminService;
use DacSanNhaDan\Services\CartQuoteService;
use DacSanNhaDan\Services\CheckoutService;
use DacSanNhaDan\Services\InventoryService;
use DacSanNhaDan\Services\OrderService;
use DacSanNhaDan\Services\PurchasePlanService;
use DacSanNhaDan\Support\Logger;

$config = require dirname(__DIR__, 2) . '/app/bootstrap.php';

try {
    unset($config);
    $action = admin_api_action();
    $pdo = Database::connection();

    $authorization = new AdminAuthorizationService();
    $auth = new AdminAuthService(new AdminUserRepository($pdo), $authorization);
    $admin = new AdminService(new AdminDashboardRepository($pdo));
    $adminProducts = new AdminProductService($pdo, new AdminProductRepository($pdo));

    if ($action === '' || $action === 'health') {
        Response::ok([
            'component' => 'admin-api',
            'app' => 'Đặc Sản Nhà Dân',
            'time' => date(DATE_ATOM),
        ]);
        return;
    }

    if ($action === 'csrf-token') {
        Response::ok([
            'csrf_token' => Csrf::adminToken(),
        ]);
        return;
    }

    if ($action === 'login') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        $user = $auth->login((string) ($body['username'] ?? ''), (string) ($body['password'] ?? ''));
        Response::ok([
            'user' => $user,
            'csrf_token' => Csrf::regenerateAdminToken(),
        ]);
        return;
    }

    $user = $auth->requireUser();
    $permission = admin_api_action_permission($action);
    if ($permission !== null) {
        $authorization->require((string) $user['role'], $permission);
    }

    if ($action === 'logout') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        $auth->logout();
        Response::ok([
            'message' => 'Đã đăng xuất.',
        ]);
        return;
    }

    if ($action === 'me') {
        Response::ok([
            'user' => $user,
            'csrf_token' => Csrf::adminToken(),
        ]);
        return;
    }

    if ($action === 'dashboard') {
        admin_api_require_method('GET');
        Response::ok($admin->dashboard());
        return;
    }

    if ($action === 'orders') {
        admin_api_require_method('GET');
        Response::ok($admin->orders(admin_api_query_filters()));
        return;
    }

    if ($action === 'order-detail') {
        admin_api_require_method('GET');
        $detail = $admin->orderDetail(admin_api_required_id('order_id'));
        if ($detail === null) {
            throw new AppException('Không tìm thấy đơn hàng.', 404);
        }
        Response::ok($detail);
        return;
    }

    if ($action === 'products') {
        admin_api_require_method('GET');
        Response::ok($admin->products(admin_api_query_filters()));
        return;
    }

    if ($action === 'product-detail') {
        admin_api_require_method('GET');
        Response::ok($adminProducts->detail(admin_api_required_id('product_id')));
        return;
    }

    if ($action === 'product-save') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        Response::ok($adminProducts->save($body));
        return;
    }

    if ($action === 'product-active') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        $adminProducts->setActive(
            admin_api_body_id($body, 'product_id'),
            ((int) ($body['is_active'] ?? 0)) === 1
        );
        Response::ok(['message' => 'Đã cập nhật trạng thái sản phẩm.']);
        return;
    }

    if ($action === 'inventory') {
        admin_api_require_method('GET');
        Response::ok($admin->inventory(admin_api_query_filters()));
        return;
    }

    if ($action === 'po-list') {
        admin_api_require_method('GET');
        Response::ok($admin->purchasePlans(admin_api_query_filters()));
        return;
    }

    if ($action === 'settings') {
        admin_api_require_method('GET');
        Response::ok($admin->settings());
        return;
    }

    if ($action === 'setting-update') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        $admin->updateSetting((string) ($body['setting_key'] ?? ''), (string) ($body['setting_value'] ?? ''));
        Response::ok(['message' => 'Đã cập nhật setting.']);
        return;
    }

    $inventoryRepository = new InventoryRepository($pdo);
    $orderRepository = new OrderRepository($pdo);
    $purchasePlanRepository = new PurchasePlanRepository($pdo);
    $inventoryService = new InventoryService($inventoryRepository);
    $orderService = new OrderService($pdo, $orderRepository, $inventoryService);
    $purchasePlanService = new PurchasePlanService($pdo, $purchasePlanRepository, $orderService, $inventoryService);

    if ($action === 'order-status') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        Response::ok($orderService->changeStatus(
            admin_api_body_id($body, 'order_id'),
            admin_api_body_string($body, 'new_status')
        ));
        return;
    }

    if ($action === 'po-detail') {
        $detail = $purchasePlanService->getDetail(admin_api_required_id('plan_id'));
        if ($detail === null) {
            throw new AppException('Không tìm thấy PO.', 404);
        }
        Response::ok($detail);
        return;
    }

    if ($action === 'po-copy-text') {
        Response::ok([
            'text' => $purchasePlanService->copyPurchasePlanText(admin_api_required_id('plan_id')),
        ]);
        return;
    }

    if ($action === 'po-preview') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        Response::ok($purchasePlanService->previewFromSelectedOrders(admin_api_body_order_ids($body)));
        return;
    }

    if ($action === 'create-po') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        $planId = $purchasePlanService->createFromSelectedOrders(
            admin_api_body_order_ids($body),
            ['note' => trim((string) ($body['note'] ?? ''))]
        );
        Response::ok(['plan_id' => $planId, 'message' => 'PO đã được tạo.']);
        return;
    }

    if ($action === 'receive-po') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        $receiptId = $purchasePlanService->receivePlan(admin_api_body_id($body, 'plan_id'), $body);
        Response::ok(['receipt_id' => $receiptId, 'message' => 'PO đã được nhận hàng.']);
        return;
    }

    if ($action === 'po-cancel') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        $purchasePlanService->cancelPlan(admin_api_body_id($body, 'plan_id'));
        Response::ok(['message' => 'PO đã được hủy.']);
        return;
    }

    throw new AppException('API quản trị không hợp lệ.', 404);
} catch (AppException $exception) {
    if ($exception->httpStatus() >= 500) {
        Logger::error($exception->getMessage(), ['admin_action' => admin_api_action()]);
    }

    Response::error($exception->getMessage(), $exception->httpStatus());
} catch (Throwable $exception) {
    Logger::error($exception->getMessage(), [
        'admin_action' => admin_api_action(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);

    Response::error('Có lỗi hệ thống quản trị. Vui lòng thử lại sau.', 500);
}

function admin_api_action(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $lastSegment = basename((string) $path);
    $action = trim((string) ($_GET['action'] ?? ''));

    if ($action === '' && $lastSegment !== '' && $lastSegment !== 'api' && $lastSegment !== 'index.php') {
        $action = $lastSegment;
    }

    return $action;
}

function admin_api_action_permission(string $action): ?string
{
    $permissions = [
        'dashboard' => 'dashboard.view',
        'orders' => 'orders.view',
        'order-detail' => 'orders.view',
        'order-status' => 'orders.transition',
        'products' => 'products.view',
        'product-detail' => 'products.view',
        'product-save' => 'products.manage',
        'product-active' => 'products.manage',
        'inventory' => 'inventory.view',
        'po-list' => 'purchase_plans.view',
        'po-detail' => 'purchase_plans.view',
        'po-copy-text' => 'purchase_plans.view',
        'po-preview' => 'purchase_plans.manage',
        'create-po' => 'purchase_plans.manage',
        'receive-po' => 'purchase_plans.manage',
        'po-cancel' => 'purchase_plans.manage',
        'settings' => 'settings.view',
        'setting-update' => 'settings.manage',
    ];

    return $permissions[$action] ?? null;
}

function admin_api_require_method(string $method): void
{
    if (Request::method() !== $method) {
        throw new AppException('Phương thức HTTP không hợp lệ.', 405);
    }
}

/**
 * @param array<string, mixed> $body
 */
function admin_api_csrf(array $body): string
{
    return trim((string) ($body['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
}

/**
 * @return array<string, mixed>
 */
function admin_api_query_filters(): array
{
    $filters = [];
    foreach (['status', 'q', 'date_from', 'date_to', 'is_active', 'limit'] as $key) {
        if (isset($_GET[$key]) && trim((string) $_GET[$key]) !== '') {
            $filters[$key] = trim((string) $_GET[$key]);
        }
    }

    return $filters;
}

function admin_api_required_id(string $key): string
{
    $raw = $_GET[$key] ?? '';
    if (!is_string($raw) && !is_int($raw)) {
        throw new AppException('Tham số ' . $key . ' không hợp lệ.', 422);
    }

    $value = trim((string) $raw);
    if ($value === '' || preg_match('/^[A-Za-z0-9_-]{1,80}$/', $value) !== 1) {
        throw new AppException('Tham số ' . $key . ' không hợp lệ.', 422);
    }

    return $value;
}

/**
 * @param array<string, mixed> $body
 */
function admin_api_body_id(array $body, string $key): string
{
    $raw = $body[$key] ?? '';
    if (!is_string($raw) && !is_int($raw)) {
        throw new AppException('Tham số ' . $key . ' không hợp lệ.', 422);
    }

    $value = trim((string) $raw);
    if ($value === '' || preg_match('/^[A-Za-z0-9_-]{1,80}$/', $value) !== 1) {
        throw new AppException('Tham số ' . $key . ' không hợp lệ.', 422);
    }

    return $value;
}

/**
 * @param array<string, mixed> $body
 */
function admin_api_body_string(array $body, string $key): string
{
    $value = $body[$key] ?? '';
    if (!is_string($value)) {
        throw new AppException('Tham số ' . $key . ' không hợp lệ.', 422);
    }

    return trim($value);
}

/**
 * @param array<string, mixed> $body
 * @return array<int, string>
 */
function admin_api_body_order_ids(array $body): array
{
    $raw = $body['order_ids'] ?? [];
    if (!is_array($raw)) {
        throw new AppException('Danh sách đơn hàng không hợp lệ.', 422);
    }

    $orderIds = [];
    foreach ($raw as $orderId) {
        $orderIds[] = admin_api_body_id(['order_id' => $orderId], 'order_id');
    }

    $orderIds = array_values(array_unique($orderIds));
    if ($orderIds === []) {
        throw new AppException('Vui lòng chọn ít nhất một đơn hàng.', 422);
    }

    return $orderIds;
}
