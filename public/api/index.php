<?php

declare(strict_types=1);

use DacSanNhaDan\Core\Database;
use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Core\Csrf;
use DacSanNhaDan\Core\Request;
use DacSanNhaDan\Core\Response;
use DacSanNhaDan\Repositories\CustomerRepository;
use DacSanNhaDan\Repositories\InventoryRepository;
use DacSanNhaDan\Repositories\OrderRepository;
use DacSanNhaDan\Repositories\ProductRepository;
use DacSanNhaDan\Repositories\PurchasePlanRepository;
use DacSanNhaDan\Repositories\ReviewRepository;
use DacSanNhaDan\Repositories\SettingRepository;
use DacSanNhaDan\Repositories\ShippingRepository;
use DacSanNhaDan\Services\CartQuoteService;
use DacSanNhaDan\Services\CatalogService;
use DacSanNhaDan\Services\CheckoutService;
use DacSanNhaDan\Services\CustomerAuthService;
use DacSanNhaDan\Services\InventoryService;
use DacSanNhaDan\Services\OrderService;
use DacSanNhaDan\Services\PurchasePlanService;
use DacSanNhaDan\Services\ReviewService;
use DacSanNhaDan\Services\SettingsService;
use DacSanNhaDan\Services\ShippingService;
use DacSanNhaDan\Support\DatabaseBaseline;
use DacSanNhaDan\Support\Logger;
use DacSanNhaDan\Support\Phone;

$config = require dirname(__DIR__, 2) . '/app/bootstrap.php';

try {
    $action = api_action();
    $pdo = Database::connection();

    if ($action === '' || $action === 'health') {
        Response::ok([
            'component' => 'storefront-api',
            'app' => 'Đặc Sản Nhà Dân',
            'time' => date(DATE_ATOM),
        ]);
        return;
    }

    if ($action === 'checkout-token') {
        api_require_method('GET');
        Response::ok([
            'checkout_token' => Csrf::checkoutToken(),
        ]);
        return;
    }

    if ($action === 'db-health') {
        $databaseName = (string) $config['database']['database'];
        $inspection = DatabaseBaseline::inspect($pdo, $databaseName);
        $hasBaseline = $inspection['missing_tables'] === [] && $inspection['missing_views'] === [];

        if (!$hasBaseline) {
            throw new AppException('Database baseline chưa đầy đủ.', 500);
        }

        Response::ok([
            'database' => $inspection['database'],
            'table_count' => $inspection['table_count'],
            'view_count' => $inspection['view_count'],
            'message' => 'Database connection ok and baseline objects exist.',
        ]);
        return;
    }

    $settingRepository = new SettingRepository($pdo);
    $productRepository = new ProductRepository($pdo);
    $inventoryRepository = new InventoryRepository($pdo);
    $shippingRepository = new ShippingRepository($pdo);
    $customerRepository = new CustomerRepository($pdo);
    $orderRepository = new OrderRepository($pdo);
    $purchasePlanRepository = new PurchasePlanRepository($pdo);
    $reviewRepository = new ReviewRepository($pdo);

    $settingsService = new SettingsService($settingRepository);
    $catalogService = new CatalogService($productRepository, $inventoryRepository);
    $shippingService = new ShippingService($shippingRepository);
    $cartQuoteService = new CartQuoteService(
        $productRepository,
        $inventoryRepository,
        $shippingRepository,
        $settingRepository
    );
    $inventoryService = new InventoryService($inventoryRepository);
    $orderService = new OrderService($pdo, $orderRepository, $inventoryService);
    $purchasePlanService = new PurchasePlanService($pdo, $purchasePlanRepository, $orderService, $inventoryService);
    $checkoutService = new CheckoutService(
        $pdo,
        $cartQuoteService,
        $customerRepository,
        $orderRepository
    );
    $customerAuth = new CustomerAuthService($customerRepository);
    $reviewService = new ReviewService($reviewRepository);

    if ($action === 'customer-session') {
        api_require_method('GET');
        $customer = $customerAuth->customer();
        Response::ok([
            'authenticated' => $customer !== null,
            'customer' => $customer,
            'checkout_token' => Csrf::checkoutToken(),
        ]);
        return;
    }

    if ($action === 'customer-register') {
        api_require_method('POST');
        $body = Request::json();
        Csrf::requireCheckoutToken((string) ($body['checkout_token'] ?? ''));
        Response::ok([
            'customer' => $customerAuth->register($body),
            'checkout_token' => Csrf::regenerateCheckoutToken(),
        ]);
        return;
    }

    if ($action === 'customer-login') {
        api_require_method('POST');
        $body = Request::json();
        Csrf::requireCheckoutToken((string) ($body['checkout_token'] ?? ''));
        Response::ok([
            'customer' => $customerAuth->login($body),
            'checkout_token' => Csrf::regenerateCheckoutToken(),
        ]);
        return;
    }

    if ($action === 'customer-profile-update') {
        api_require_method('POST');
        $body = Request::json();
        Csrf::requireCheckoutToken((string) ($body['checkout_token'] ?? ''));
        Response::ok([
            'customer' => $customerAuth->updateProfile($body),
            'checkout_token' => Csrf::regenerateCheckoutToken(),
        ]);
        return;
    }

    if ($action === 'customer-logout') {
        api_require_method('POST');
        $body = Request::json();
        Csrf::requireCheckoutToken((string) ($body['checkout_token'] ?? ''));
        $customerAuth->logout();
        Response::ok([
            'message' => 'Đã đăng xuất.',
            'checkout_token' => Csrf::regenerateCheckoutToken(),
        ]);
        return;
    }

    if ($action === 'customer-orders') {
        api_require_method('GET');
        $customer = $customerAuth->requireCustomer();
        Response::ok([
            'items' => $orderRepository->findOrdersByCustomerId((int) $customer['customer_id']),
        ]);
        return;
    }

    if ($action === 'review-create') {
        api_require_method('POST');
        $body = Request::json();
        Csrf::requireCheckoutToken((string) ($body['checkout_token'] ?? ''));
        $customer = $customerAuth->requireCustomer();
        Response::ok(array_merge(
            $reviewService->create((int) $customer['customer_id'], $body),
            ['checkout_token' => Csrf::regenerateCheckoutToken()]
        ));
        return;
    }

    if ($action === 'reviews-public') {
        api_require_method('GET');
        Response::ok(['items' => $reviewService->publicPositive()]);
        return;
    }

    if ($action === 'best-sellers') {
        api_require_method('GET');
        $items = $catalogService->bestSellers(3);
        Response::ok(['items' => $items, 'count' => count($items)]);
        return;
    }

    if ($action === 'settings') {
        Response::ok($settingsService->publicSettings());
        return;
    }

    if ($action === 'catalog') {
        $filters = api_catalog_filters();
        $items = $catalogService->catalog($filters);
        Response::ok([
            'items' => $items,
            'count' => count($items),
            'filters' => $filters,
        ]);
        return;
    }

    if ($action === 'product-detail') {
        $productId = api_required_id('product_id');
        $product = $catalogService->productDetail($productId);

        if ($product === null) {
            throw new AppException('Không tìm thấy sản phẩm.', 404);
        }

        Response::ok($product);
        return;
    }

    if ($action === 'shipping-zones') {
        Response::ok($shippingService->summary());
        return;
    }

    if ($action === 'stock-summary') {
        $productId = api_optional_id('product_id');
        $summary = $catalogService->stockSummary($productId);

        if ($productId !== null && $summary === null) {
            throw new AppException('Không tìm thấy tồn kho của sản phẩm.', 404);
        }

        Response::ok($productId === null ? [
            'items' => $summary,
            'count' => is_array($summary) ? count($summary) : 0,
        ] : $summary);
        return;
    }

    if ($action === 'cart-quote') {
        api_require_method('POST');
        Response::ok($cartQuoteService->quote(Request::json()));
        return;
    }

    if ($action === 'checkout') {
        api_require_method('POST');
        $body = Request::json();
        $customer = $customerAuth->customer();
        if ($customer !== null) {
            $body['_authenticated_customer_id'] = (int) $customer['customer_id'];
        }
        Response::ok($checkoutService->checkout($body));
        return;
    }

    if ($action === 'order-status') {
        api_require_method('POST');
        $body = Request::json();
        $orderId = api_required_body_id($body, 'order_id');
        $newStatus = trim((string) ($body['new_status'] ?? ''));

        if (!in_array($newStatus, ['new', 'confirmed', 'ordered', 'received', 'ready', 'done', 'cancelled'], true)) {
            throw new AppException('Trạng thái mới không hợp lệ.', 422);
        }

        Response::ok($orderService->changeStatus($orderId, $newStatus));
        return;
    }

    if ($action === 'po-preview') {
        api_require_method('POST');
        $body = Request::json();
        Response::ok($purchasePlanService->previewFromSelectedOrders(api_body_order_ids($body)));
        return;
    }

    if ($action === 'create-po') {
        api_require_method('POST');
        $body = Request::json();
        $planId = $purchasePlanService->createFromSelectedOrders(
            api_body_order_ids($body),
            ['note' => trim((string) ($body['note'] ?? ''))]
        );
        Response::ok([
            'plan_id' => $planId,
            'message' => 'PO đã được tạo thành công.',
        ]);
        return;
    }

    if ($action === 'po-detail') {
        api_require_method('GET');
        $planId = api_required_id('plan_id');
        $detail = $purchasePlanService->getDetail($planId);

        if ($detail === null) {
            throw new AppException('Không tìm thấy PO.', 404);
        }

        Response::ok($detail);
        return;
    }

    if ($action === 'po-copy-text') {
        api_require_method('GET');
        Response::ok([
            'text' => $purchasePlanService->copyPurchasePlanText(api_required_id('plan_id')),
        ]);
        return;
    }

    if ($action === 'po-cancel') {
        api_require_method('POST');
        $body = Request::json();
        $purchasePlanService->cancelPlan(api_required_body_id($body, 'plan_id'));
        Response::ok([
            'message' => 'PO đã được hủy.',
        ]);
        return;
    }

    if ($action === 'receive-po') {
        api_require_method('POST');
        $body = Request::json();
        $receiptId = $purchasePlanService->receivePlan(api_required_body_id($body, 'plan_id'), $body);
        Response::ok([
            'receipt_id' => $receiptId,
            'message' => 'PO đã được nhận hàng.',
        ]);
        return;
    }

    if ($action === 'order-lookup') {
        api_require_method('GET');
        $phone = Phone::normalize((string) ($_GET['phone'] ?? ''));
        if (!Phone::isValidVietnamPhone($phone)) {
            throw new AppException('Số điện thoại tra cứu không hợp lệ.', 422);
        }

        Response::ok([
            'items' => $orderRepository->findOrdersByPhone($phone),
        ]);
        return;
    }

    throw new AppException('API action không hợp lệ.', 404);
} catch (AppException $exception) {
    if ($exception->httpStatus() >= 500) {
        Logger::error($exception->getMessage(), ['action' => api_action()]);
    }

    Response::error($exception->getMessage(), $exception->httpStatus());
} catch (Throwable $exception) {
    Logger::error($exception->getMessage(), [
        'action' => api_action(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);

    Response::error('Có lỗi hệ thống. Vui lòng thử lại sau.', 500);
}

function api_action(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $lastSegment = basename((string) $path);
    $action = trim((string) ($_GET['action'] ?? ''));

    if ($action === '' && $lastSegment !== '' && $lastSegment !== 'api' && $lastSegment !== 'index.php') {
        $action = $lastSegment;
    }

    return $action;
}

function api_require_method(string $method): void
{
    if (Request::method() !== $method) {
        throw new AppException('Phương thức HTTP không hợp lệ.', 405);
    }
}

/**
 * @return array{category_id?: string, source?: string, q?: string}
 */
function api_catalog_filters(): array
{
    $filters = [];
    $categoryId = api_optional_id('category_id');
    $source = trim((string) ($_GET['source'] ?? ''));
    $query = trim((string) ($_GET['q'] ?? ''));

    if ($categoryId !== null) {
        $filters['category_id'] = $categoryId;
    }

    if ($source !== '') {
        if (!in_array($source, ['Binh Dinh', 'Gia Lai', 'Unknown'], true)) {
            throw new AppException('Nguồn sản phẩm không hợp lệ.', 422);
        }

        $filters['source'] = $source;
    }

    if ($query !== '') {
        if (mb_strlen($query, 'UTF-8') > 80 || preg_match('/[\x00-\x1F\x7F]/', $query) === 1) {
            throw new AppException('Từ khóa tìm kiếm không hợp lệ.', 422);
        }

        $filters['q'] = $query;
    }

    return $filters;
}

function api_required_id(string $key): string
{
    $value = api_optional_id($key);

    if ($value === null) {
        throw new AppException('Thiếu tham số ' . $key . '.', 422);
    }

    return $value;
}

function api_optional_id(string $key): ?string
{
    $value = trim((string) ($_GET[$key] ?? ''));

    if ($value === '') {
        return null;
    }

    if (preg_match('/^[A-Za-z0-9_-]{1,80}$/', $value) !== 1) {
        throw new AppException('Tham số ' . $key . ' không hợp lệ.', 422);
    }

    return $value;
}

/**
 * @param array<string, mixed> $body
 */
function api_required_body_id(array $body, string $key): string
{
    $value = trim((string) ($body[$key] ?? ''));

    if ($value === '') {
        throw new AppException('Thiếu tham số ' . $key . '.', 422);
    }

    if (preg_match('/^[A-Za-z0-9_-]{1,80}$/', $value) !== 1) {
        throw new AppException('Tham số ' . $key . ' không hợp lệ.', 422);
    }

    return $value;
}

/**
 * @param array<string, mixed> $body
 * @return array<int, string>
 */
function api_body_order_ids(array $body): array
{
    $rawOrderIds = $body['order_ids'] ?? [];
    if (!is_array($rawOrderIds)) {
        throw new AppException('Danh sách đơn hàng không hợp lệ.', 422);
    }

    $orderIds = [];
    foreach ($rawOrderIds as $orderId) {
        $orderId = trim((string) $orderId);
        if ($orderId === '') {
            continue;
        }
        if (preg_match('/^[A-Za-z0-9_-]{1,80}$/', $orderId) !== 1) {
            throw new AppException('Mã đơn hàng không hợp lệ.', 422);
        }
        $orderIds[$orderId] = $orderId;
    }

    if ($orderIds === []) {
        throw new AppException('Vui lòng chọn ít nhất một đơn hàng.', 422);
    }

    return array_values($orderIds);
}
