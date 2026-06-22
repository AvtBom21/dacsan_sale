<?php

declare(strict_types=1);

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Core\Csrf;
use DacSanNhaDan\Core\Database;
use DacSanNhaDan\Core\Request;
use DacSanNhaDan\Core\Response;
use DacSanNhaDan\Repositories\AdminDashboardRepository;
use DacSanNhaDan\Repositories\AdminProductRepository;
use DacSanNhaDan\Repositories\AdminSettingsRepository;
use DacSanNhaDan\Repositories\AdminUserRepository;
use DacSanNhaDan\Repositories\CustomerRepository;
use DacSanNhaDan\Repositories\InventoryRepository;
use DacSanNhaDan\Repositories\OrderRepository;
use DacSanNhaDan\Repositories\ProductRepository;
use DacSanNhaDan\Repositories\PurchasePlanRepository;
use DacSanNhaDan\Repositories\ReviewRepository;
use DacSanNhaDan\Repositories\SettingRepository;
use DacSanNhaDan\Repositories\ShippingRepository;
use DacSanNhaDan\Services\AdminAuthorizationService;
use DacSanNhaDan\Services\AdminAuthService;
use DacSanNhaDan\Services\AdminInventoryService;
use DacSanNhaDan\Services\AdminProductService;
use DacSanNhaDan\Services\AdminReviewService;
use DacSanNhaDan\Services\AdminSettingsService;
use DacSanNhaDan\Services\AdminUserService;
use DacSanNhaDan\Services\AdminService;
use DacSanNhaDan\Services\CartQuoteService;
use DacSanNhaDan\Services\CheckoutService;
use DacSanNhaDan\Services\InventoryService;
use DacSanNhaDan\Services\OrderService;
use DacSanNhaDan\Services\PurchasePlanService;
use DacSanNhaDan\Services\UploadService;
use DacSanNhaDan\Support\Logger;

$config = require dirname(__DIR__, 2) . '/app/bootstrap.php';

try {
    unset($config);
    $action = admin_api_action();
    $pdo = Database::connection();

    $authorization = new AdminAuthorizationService();
    $auth = new AdminAuthService(new AdminUserRepository($pdo), $authorization);
    $admin = new AdminService(new AdminDashboardRepository($pdo));
    $adminProductRepository = new AdminProductRepository($pdo);
    $adminProducts = new AdminProductService($pdo, $adminProductRepository);
    $inventoryRepository = new InventoryRepository($pdo);
    $adminInventory = new AdminInventoryService($pdo, $inventoryRepository);
    $adminSettings = new AdminSettingsService($pdo, new AdminSettingsRepository($pdo));
    $adminUsers = new AdminUserService($pdo, new AdminUserRepository($pdo));
    $adminReviews = new AdminReviewService(new ReviewRepository($pdo));
    $uploads = new UploadService(dirname(__DIR__, 2));

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

    if ($action === 'product-image-upload') {
        admin_api_require_method('POST');
        Csrf::requireAdminToken(admin_api_csrf([]));
        $productId = admin_api_post_id('product_id', 40);
        if (!$adminProductRepository->productExists($productId)) {
            throw new AppException('Không tìm thấy sản phẩm.', 404);
        }
        $imageAlt = admin_api_post_text('image_alt', 255);
        $isBase = admin_api_post_flag('is_base');
        $sortOrder = admin_api_post_non_negative_int('sort_order');
        $file = admin_api_uploaded_file('image');

        $image = $uploads->storeImageAndPersist(
            $file,
            $productId,
            function (array $stored) use (
                $pdo,
                $adminProductRepository,
                $productId,
                $imageAlt,
                $isBase,
                $sortOrder
            ): array {
                $pdo->beginTransaction();
                try {
                    if ($isBase === 1) {
                        $adminProductRepository->clearActiveBaseImages($productId);
                    }
                    $imageId = $adminProductRepository->insertImage([
                        'product_id' => $productId,
                        'image_path' => $stored['path'],
                        'source_url' => '',
                        'image_alt' => $imageAlt,
                        'is_base' => $isBase,
                        'is_active' => 1,
                        'sort_order' => $sortOrder,
                    ]);
                    $image = $adminProductRepository->image($imageId, $productId);
                    if ($image === null) {
                        throw new AppException('Không thể đọc metadata ảnh vừa tạo.', 500);
                    }
                    $pdo->commit();
                    return $image;
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
            }
        );
        Response::ok($image);
        return;
    }

    if ($action === 'payment-qr-upload') {
        admin_api_require_method('POST');
        Csrf::requireAdminToken(admin_api_csrf([]));
        $file = admin_api_uploaded_file('image');
        $result = $uploads->storeImageAndPersist(
            $file,
            'payment-qr',
            function (array $stored) use ($pdo): array {
                $pdo->beginTransaction();
                try {
                    $statement = $pdo->prepare(
                        'INSERT INTO settings (setting_key, setting_value, note)
                         VALUES (:setting_key, :setting_value, :note)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
                    );
                    $statement->execute([
                        'setting_key' => 'bank_qr_image_path',
                        'setting_value' => $stored['path'],
                        'note' => 'Ảnh QR chuyển khoản trong products_image',
                    ]);
                    $pdo->commit();
                    return ['path' => $stored['path']];
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
            }
        );
        Response::ok($result);
        return;
    }

    if ($action === 'inventory') {
        admin_api_require_method('GET');
        Response::ok($admin->inventory(admin_api_query_filters()));
        return;
    }

    if ($action === 'inventory-lot-detail') {
        admin_api_require_method('GET');
        Response::ok($adminInventory->detail(admin_api_required_id('lot_id')));
        return;
    }

    if ($action === 'inventory-receive') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        Response::ok($adminInventory->receiveManual($body));
        return;
    }

    if ($action === 'inventory-adjust') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        Response::ok($adminInventory->adjustLot(
            admin_api_body_id($body, 'lot_id'),
            $body['delta_base'] ?? null,
            $body['reason'] ?? null
        ));
        return;
    }

    if ($action === 'po-list') {
        admin_api_require_method('GET');
        Response::ok($admin->purchasePlans(admin_api_query_filters()));
        return;
    }

    if ($action === 'settings') {
        admin_api_require_method('GET');
        Response::ok($adminSettings->page());
        return;
    }

    if ($action === 'settings-update') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        Response::ok($adminSettings->updateSettings($body));
        return;
    }

    if ($action === 'shipping-zone-save') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        Response::ok($adminSettings->saveZone($body));
        return;
    }

    if ($action === 'shipping-zone-active') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        Response::ok($adminSettings->setZoneActive(
            admin_api_body_id($body, 'zone_id'),
            ((int) ($body['is_active'] ?? 0)) === 1
        ));
        return;
    }

    if ($action === 'shipping-zone-default') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        Response::ok($adminSettings->setZoneDefault(admin_api_body_id($body, 'zone_id')));
        return;
    }

    if ($action === 'admin-users') {
        admin_api_require_method('GET');
        Response::ok($adminUsers->page());
        return;
    }

    if ($action === 'admin-user-create') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        Response::ok($adminUsers->create($body));
        return;
    }

    if ($action === 'admin-user-update') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        Response::ok($adminUsers->update($body));
        return;
    }

    if ($action === 'admin-user-password') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        Response::ok($adminUsers->resetPassword(
            $body['admin_id'] ?? null,
            $body['password'] ?? null
        ));
        return;
    }

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
        admin_api_require_method('GET');
        $detail = $purchasePlanService->getDetail(admin_api_required_id('plan_id'));
        if ($detail === null) {
            throw new AppException('Không tìm thấy PO.', 404);
        }
        Response::ok($detail);
        return;
    }

    if ($action === 'po-copy-text') {
        admin_api_require_method('GET');
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

    if ($action === 'review-list') {
        admin_api_require_method('GET');
        Response::ok($adminReviews->page(trim((string) ($_GET['status'] ?? ''))));
        return;
    }

    if ($action === 'review-moderate') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        Response::ok($adminReviews->moderate(
            $body['review_id'] ?? null,
            admin_api_body_string($body, 'status'),
            (int) $user['admin_id']
        ));
        return;
    }

    if ($action === 'po-mark-ordered') {
        admin_api_require_method('POST');
        $body = Request::json();
        Csrf::requireAdminToken(admin_api_csrf($body));
        $purchasePlanService->markPlanOrdered(admin_api_body_id($body, 'plan_id'));
        Response::ok(['message' => 'PO đã chuyển sang trạng thái đã đặt hàng.']);
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
        'product-image-upload' => 'products.manage',
        'inventory' => 'inventory.view',
        'inventory-lot-detail' => 'inventory.view',
        'inventory-receive' => 'inventory.manage',
        'inventory-adjust' => 'inventory.manage',
        'po-list' => 'purchase_plans.view',
        'po-detail' => 'purchase_plans.view',
        'po-copy-text' => 'purchase_plans.view',
        'po-preview' => 'purchase_plans.manage',
        'create-po' => 'purchase_plans.manage',
        'receive-po' => 'purchase_plans.manage',
        'po-mark-ordered' => 'purchase_plans.manage',
        'po-cancel' => 'purchase_plans.manage',
        'settings' => 'settings.view',
        'settings-update' => 'settings.manage',
        'shipping-zone-save' => 'settings.manage',
        'shipping-zone-active' => 'settings.manage',
        'shipping-zone-default' => 'settings.manage',
        'payment-qr-upload' => 'settings.manage',
        'admin-users' => 'admin_users.manage',
        'admin-user-create' => 'admin_users.manage',
        'admin-user-update' => 'admin_users.manage',
        'admin-user-password' => 'admin_users.manage',
        'review-list' => 'reviews.view',
        'review-moderate' => 'reviews.manage',
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
    foreach (['status', 'q', 'date_from', 'date_to', 'is_active', 'limit', 'page', 'per_page'] as $key) {
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

/** @return array<string, mixed> */
function admin_api_uploaded_file(string $key): array
{
    $file = $_FILES[$key] ?? null;
    if (!is_array($file)) {
        throw new AppException('Thiếu file tải lên.', 422);
    }
    foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $field) {
        if (!array_key_exists($field, $file) || is_array($file[$field])) {
            throw new AppException('Thông tin file tải lên không hợp lệ.', 422);
        }
    }

    return $file;
}

function admin_api_post_id(string $key, int $maxLength = 80): string
{
    $value = $_POST[$key] ?? '';
    if (
        !is_string($value)
        || $maxLength < 1
        || $maxLength > 80
        || strlen(trim($value)) > $maxLength
        || preg_match('/^[A-Za-z0-9_-]+$/', trim($value)) !== 1
    ) {
        throw new AppException('Tham số ' . $key . ' không hợp lệ.', 422);
    }

    return trim($value);
}

function admin_api_post_text(string $key, int $maxLength): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value) || mb_strlen(trim($value), 'UTF-8') > $maxLength) {
        throw new AppException('Tham số ' . $key . ' không hợp lệ.', 422);
    }

    return trim($value);
}

function admin_api_post_flag(string $key): int
{
    $value = $_POST[$key] ?? '0';
    if (!is_string($value) || !in_array($value, ['0', '1'], true)) {
        throw new AppException('Tham số ' . $key . ' không hợp lệ.', 422);
    }

    return (int) $value;
}

function admin_api_post_non_negative_int(string $key): int
{
    $value = $_POST[$key] ?? '0';
    if (
        !is_string($value)
        || filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 2147483647]]
        ) === false
    ) {
        throw new AppException('Tham số ' . $key . ' không hợp lệ.', 422);
    }

    return (int) $value;
}
