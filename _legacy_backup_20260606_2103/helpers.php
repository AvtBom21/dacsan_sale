<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

class AppException extends RuntimeException
{
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money_vnd($value): string
{
    return number_format((int)round((float)$value), 0, ',', '.') . 'đ';
}

function decimal_display($value): string
{
    $number = (float)$value;
    if (abs($number - round($number)) < 0.0001) {
        return (string)(int)round($number);
    }
    return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
}

function admin_auto_expiry_date(string $receivedDate, int $shelfLifeValue, ?string $shelfLifeUnit): string
{
    if ($shelfLifeValue <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $receivedDate)) {
        return '';
    }
    $unit = (string)$shelfLifeUnit;
    if (!in_array($unit, ['days', 'months'], true)) {
        return '';
    }
    return (new DateTimeImmutable($receivedDate))
        ->modify('+' . $shelfLifeValue . ' ' . $unit)
        ->format('Y-m-d');
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    try {
        $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        throw new AppException('Dữ liệu gửi lên không hợp lệ.');
    }

    if (!is_array($payload)) {
        throw new AppException('Dữ liệu gửi lên không hợp lệ.');
    }

    return $payload;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function require_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        throw new AppException('Phiên bảo mật đã hết hạn. Vui lòng tải lại trang và thử lại.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

function consume_flash_messages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return is_array($messages) ? $messages : [];
}

function redirect_to(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function safe_admin_redirect(string $fallback = 'admin.php'): void
{
    $tab = preg_replace('/[^a-z_]/', '', (string)($_POST['return_tab'] ?? $_GET['tab'] ?? ''));
    if ($tab === 'inventory' && !empty($_POST['return_inventory_product_id'])) {
        redirect_to('admin.php?tab=inventory&inventory_product_id=' . rawurlencode((string)$_POST['return_inventory_product_id']));
    }
    redirect_to($tab ? 'admin.php?tab=' . rawurlencode($tab) : $fallback);
}

function ensure_checkout_token(bool $refresh = false): string
{
    if ($refresh || empty($_SESSION['checkout_token'])) {
        $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['checkout_token'];
}

function normalize_phone(string $phone): string
{
    $phone = trim($phone);
    $phone = preg_replace('/[^\d+]/', '', $phone) ?? '';
    if (str_starts_with($phone, '+')) {
        return '+' . preg_replace('/\D/', '', substr($phone, 1));
    }
    return preg_replace('/\D/', '', $phone) ?? '';
}

function get_settings_map(PDO $pdo): array
{
    $rows = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function app_image_path(?string $path): string
{
    $placeholder = 'products_image/placeholder.svg';
    $path = trim((string)$path);
    if ($path === '') {
        return $placeholder;
    }

    $path = str_replace('\\', '/', $path);
    $path = ltrim($path, '/');
    if (!preg_match('#^products_image/[A-Za-z0-9._/\-]+$#', $path)) {
        return $placeholder;
    }

    $fullPath = __DIR__ . '/' . $path;
    if (!is_file($fullPath)) {
        return $placeholder;
    }

    return $path;
}

function source_to_region(string $source): string
{
    return $source === 'Gia Lai' ? 'gia-lai' : 'binh-dinh';
}

function store_status_label(?string $status): string
{
    $status = $status ?: 'unknown';
    return [
        'new' => 'Đã tiếp nhận',
        'confirmed' => 'Đã xác nhận',
        'ready' => 'Sẵn sàng giao',
        'done' => 'Đã giao hàng',
        'cancelled' => 'Đã hủy',
        'ordered' => 'Đã đặt NCC',
        'received' => 'Đã nhận hàng',
        'unknown' => 'Không xác định',
    ][$status] ?? $status;
}

function load_store_catalog(PDO $pdo): array
{
    $cardRows = $pdo->query(
        'SELECT * FROM v_product_cards ORDER BY category_id, product_name'
    )->fetchAll();

    $products = [];
    foreach ($cardRows as $row) {
        $productId = (string)$row['product_id'];
        if (isset($products[$productId])) {
            continue;
        }

        $products[$productId] = [
            'id' => $productId,
            'region' => source_to_region((string)$row['default_source']),
            'source' => $row['default_source'],
            'category' => $row['category_label'],
            'name' => $row['product_name'],
            'desc' => $row['short_description'] ?: '',
            'fullDesc' => $row['full_description'] ?: $row['short_description'] ?: '',
            'ingredients' => $row['ingredients'] ?: '',
            'baseUomLabel' => $row['base_uom_label'] ?: '',
            'shelfLifeValue' => (int)($row['shelf_life_value'] ?? 0),
            'shelfLifeUnit' => $row['shelf_life_unit'] ?? '',
            'img' => app_image_path($row['base_image_path'] ?? ''),
            'images' => [],
            'options' => [],
        ];
    }

    if (!$products) {
        return [];
    }

    $ids = array_keys($products);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $imageStmt = $pdo->prepare(
        "SELECT product_id, image_path, image_alt, is_base
         FROM product_images
         WHERE product_id IN ($placeholders)
           AND is_active = 1
         ORDER BY product_id, sort_order, image_id"
    );
    $imageStmt->execute($ids);
    foreach ($imageStmt->fetchAll() as $image) {
        $productId = (string)$image['product_id'];
        if (!isset($products[$productId])) {
            continue;
        }
        $path = app_image_path($image['image_path'] ?? '');
        if (!in_array($path, $products[$productId]['images'], true)) {
            $products[$productId]['images'][] = $path;
        }
        if ((int)$image['is_base'] === 1) {
            $products[$productId]['img'] = $path;
        }
    }

    $uomStmt = $pdo->prepare(
        "SELECT product_id, uom_id, uom_label, conversion_to_base, unit_price_vnd,
                is_base_unit, is_default, sort_order
         FROM product_uoms
         WHERE product_id IN ($placeholders)
           AND is_active = 1
           AND is_sellable = 1
         ORDER BY product_id, is_default DESC, sort_order, conversion_to_base"
    );
    $uomStmt->execute($ids);
    foreach ($uomStmt->fetchAll() as $uom) {
        $productId = (string)$uom['product_id'];
        if (!isset($products[$productId])) {
            continue;
        }
        $price = (int)$uom['unit_price_vnd'];
        $products[$productId]['options'][] = [
            'uomId' => $uom['uom_id'],
            'uom' => $uom['uom_label'],
            'price' => money_vnd($price),
            'priceValue' => $price,
            'conversionToBase' => (float)$uom['conversion_to_base'],
            'isBaseUnit' => (int)$uom['is_base_unit'] === 1,
            'isDefault' => (int)$uom['is_default'] === 1,
        ];
    }

    foreach ($products as $productId => &$product) {
        if (!$product['images']) {
            $product['images'][] = $product['img'] ?: 'products_image/placeholder.svg';
        }
        if (!$product['options']) {
            unset($products[$productId]);
            continue;
        }
        $first = $product['options'][0];
        $product['uom'] = $first['uom'];
        $product['price'] = $first['price'];
    }
    unset($product);

    return array_values($products);
}

function fetch_shipping_zone(PDO $pdo, ?string $zoneId): array
{
    if ($zoneId) {
        $stmt = $pdo->prepare(
            'SELECT zone_id, zone_name, fee_vnd FROM shipping_zones WHERE zone_id = ? AND is_active = 1'
        );
        $stmt->execute([$zoneId]);
        $zone = $stmt->fetch();
        if ($zone) {
            return $zone;
        }
    }

    $settings = get_settings_map($pdo);
    $defaultZone = (string)($settings['default_shipping_zone_id'] ?? '');
    if ($defaultZone !== '') {
        $stmt = $pdo->prepare(
            'SELECT zone_id, zone_name, fee_vnd FROM shipping_zones WHERE zone_id = ? AND is_active = 1'
        );
        $stmt->execute([$defaultZone]);
        $zone = $stmt->fetch();
        if ($zone) {
            return $zone;
        }
    }

    $zone = $pdo->query(
        'SELECT zone_id, zone_name, fee_vnd FROM shipping_zones WHERE is_active = 1 ORDER BY is_default DESC, zone_name LIMIT 1'
    )->fetch();

    if (!$zone) {
        return ['zone_id' => null, 'zone_name' => '', 'fee_vnd' => 0];
    }

    return $zone;
}

function unique_id(PDO $pdo, string $kind): string
{
    $map = [
        'order' => ['orders', 'order_id', 'DH'],
        'lot' => ['inventory_lots', 'lot_id', 'LOT'],
        'movement' => ['inventory_movements', 'movement_id', 'MOV'],
        'plan' => ['purchase_plans', 'plan_id', 'PO'],
        'receipt' => ['purchase_plan_receipts', 'receipt_id', 'RCV'],
    ];
    if (!isset($map[$kind])) {
        throw new InvalidArgumentException('Unknown id kind.');
    }

    [$table, $column, $prefix] = $map[$kind];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $column = ?");
    for ($i = 0; $i < 10; $i++) {
        $id = $prefix . date('YmdHis') . strtoupper(bin2hex(random_bytes(3)));
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $id;
        }
    }

    throw new AppException('Không tạo được mã dữ liệu duy nhất. Vui lòng thử lại.');
}

function create_store_order(PDO $pdo, array $payload): array
{
    $customerName = trim((string)($payload['customer_name'] ?? ''));
    $customerPhone = normalize_phone((string)($payload['customer_phone'] ?? ''));
    $customerAddress = trim((string)($payload['customer_address'] ?? ''));
    $shippingMethod = (string)($payload['shipping_method'] ?? 'delivery');
    $shippingZoneId = trim((string)($payload['shipping_zone_id'] ?? ''));
    $paymentMethod = strtoupper(trim((string)($payload['payment_method'] ?? 'COD')));
    $note = trim((string)($payload['note'] ?? ''));
    $items = $payload['items'] ?? [];

    if (mb_strlen($customerName) < 2 || mb_strlen($customerName) > 160) {
        throw new AppException('Vui lòng nhập họ tên khách hàng hợp lệ.');
    }
    if (!preg_match('/^\+?\d{9,15}$/', $customerPhone)) {
        throw new AppException('Số điện thoại chưa hợp lệ.');
    }
    if (!in_array($shippingMethod, ['delivery', 'pickup'], true)) {
        throw new AppException('Phương thức nhận hàng không hợp lệ.');
    }
    if ($shippingMethod === 'delivery' && (mb_strlen($customerAddress) < 5 || mb_strlen($customerAddress) > 255)) {
        throw new AppException('Vui lòng nhập địa chỉ giao hàng rõ ràng.');
    }
    if (!in_array($paymentMethod, ['COD', 'BANK'], true)) {
        throw new AppException('Hình thức thanh toán không hợp lệ.');
    }
    if (!is_array($items) || count($items) === 0) {
        throw new AppException('Đơn hàng chưa có sản phẩm.');
    }
    if (count($items) > 40) {
        throw new AppException('Đơn hàng có quá nhiều dòng sản phẩm.');
    }

    $combined = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new AppException('Dòng sản phẩm không hợp lệ.');
        }
        $productId = trim((string)($item['product_id'] ?? $item['id'] ?? ''));
        $uomId = trim((string)($item['uom_id'] ?? $item['uomId'] ?? ''));
        $qty = (float)($item['qty'] ?? 0);
        if ($productId === '' || $uomId === '' || $qty <= 0 || $qty > 99) {
            throw new AppException('Sản phẩm hoặc số lượng trong giỏ hàng không hợp lệ.');
        }
        $qty = round($qty, 3);
        $key = $productId . '|' . $uomId;
        if (!isset($combined[$key])) {
            $combined[$key] = ['product_id' => $productId, 'uom_id' => $uomId, 'qty' => 0.0];
        }
        $combined[$key]['qty'] = round($combined[$key]['qty'] + $qty, 3);
    }

    $lineStmt = $pdo->prepare(
        "SELECT p.product_id, p.product_name, p.default_source, p.is_active,
                u.uom_id, u.uom_label, u.conversion_to_base, u.unit_price_vnd
         FROM product_uoms u
         JOIN products p ON p.product_id = u.product_id
         WHERE p.product_id = ?
           AND u.uom_id = ?
           AND p.is_active = 1
           AND u.is_active = 1
           AND u.is_sellable = 1"
    );

    $validatedLines = [];
    $subtotal = 0;
    $sources = [];
    foreach ($combined as $line) {
        $lineStmt->execute([$line['product_id'], $line['uom_id']]);
        $dbLine = $lineStmt->fetch();
        if (!$dbLine) {
            throw new AppException('Một sản phẩm trong giỏ hàng không còn bán hoặc UOM không hợp lệ.');
        }

        $qtyUom = (float)$line['qty'];
        $conversion = (float)$dbLine['conversion_to_base'];
        if ($conversion <= 0) {
            throw new AppException('Cấu hình quy đổi UOM của sản phẩm chưa hợp lệ.');
        }
        $qtyBase = round($qtyUom * $conversion, 3);
        $unitPrice = (int)$dbLine['unit_price_vnd'];
        $lineTotal = (int)round($unitPrice * $qtyUom);
        $subtotal += $lineTotal;
        $sources[] = (string)$dbLine['default_source'];

        $validatedLines[] = [
            'product_id' => $dbLine['product_id'],
            'product_name' => $dbLine['product_name'],
            'uom_id' => $dbLine['uom_id'],
            'uom_label' => $dbLine['uom_label'],
            'source_location' => $dbLine['default_source'] ?: 'Unknown',
            'qty_uom' => $qtyUom,
            'conversion_to_base' => $conversion,
            'qty_base' => $qtyBase,
            'unit_price_vnd' => $unitPrice,
            'line_total_vnd' => $lineTotal,
        ];
    }

    if ($subtotal <= 0) {
        throw new AppException('Tổng tiền đơn hàng chưa hợp lệ.');
    }

    $settings = get_settings_map($pdo);
    $freeShipThreshold = (int)($settings['free_ship_threshold'] ?? 0);
    $zone = $shippingMethod === 'delivery' ? fetch_shipping_zone($pdo, $shippingZoneId ?: null) : ['zone_id' => null, 'zone_name' => '', 'fee_vnd' => 0];
    $shippingFee = $shippingMethod === 'delivery' ? (int)$zone['fee_vnd'] : 0;
    if ($freeShipThreshold > 0 && $subtotal >= $freeShipThreshold) {
        $shippingFee = 0;
    }

    $sourceValues = array_values(array_unique(array_filter($sources, static fn($src) => $src !== 'Unknown')));
    $sourceSummary = count($sourceValues) === 1 ? $sourceValues[0] : (count($sourceValues) > 1 ? 'Mixed' : 'Unknown');
    $total = $subtotal + $shippingFee;
    $paymentLabel = $paymentMethod === 'BANK' ? 'Chuyển khoản ngân hàng' : 'Thanh toán khi nhận hàng';
    $storedNote = trim("Thanh toán: {$paymentLabel}\n" . $note);

    $pdo->beginTransaction();
    try {
        $customerStmt = $pdo->prepare(
            "INSERT INTO customers (customer_name, customer_phone, customer_address)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
               customer_name = VALUES(customer_name),
               customer_address = VALUES(customer_address),
               updated_at = CURRENT_TIMESTAMP"
        );
        $customerStmt->execute([$customerName, $customerPhone, $customerAddress]);

        $customerIdStmt = $pdo->prepare('SELECT customer_id FROM customers WHERE customer_phone = ?');
        $customerIdStmt->execute([$customerPhone]);
        $customerId = (int)$customerIdStmt->fetchColumn();

        $orderId = unique_id($pdo, 'order');
        $orderStmt = $pdo->prepare(
            "INSERT INTO orders
              (order_id, customer_id, status, customer_name, customer_phone, customer_address,
               receive_date, note, shipping_method, shipping_zone_id, shipping_fee_vnd,
               subtotal_vnd, total_vnd, source_summary)
             VALUES
              (?, ?, 'new', ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)"
        );
        $orderStmt->execute([
            $orderId,
            $customerId ?: null,
            $customerName,
            $customerPhone,
            $customerAddress,
            $storedNote,
            $shippingMethod,
            $zone['zone_id'] ?? null,
            $shippingFee,
            $subtotal,
            $total,
            $sourceSummary,
        ]);

        $itemStmt = $pdo->prepare(
            "INSERT INTO order_items
              (order_id, line_no, product_id, product_name_snapshot, uom_id,
               uom_label_snapshot, source_location, qty_uom, conversion_to_base_snapshot,
               qty_base, unit_price_vnd, line_total_vnd)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $lineNo = 1;
        foreach ($validatedLines as $line) {
            $itemStmt->execute([
                $orderId,
                $lineNo++,
                $line['product_id'],
                $line['product_name'],
                $line['uom_id'],
                $line['uom_label'],
                $line['source_location'],
                $line['qty_uom'],
                $line['conversion_to_base'],
                $line['qty_base'],
                $line['unit_price_vnd'],
                $line['line_total_vnd'],
            ]);
        }

        $pdo->commit();

        return [
            'order_id' => $orderId,
            'subtotal_vnd' => $subtotal,
            'shipping_fee_vnd' => $shippingFee,
            'total_vnd' => $total,
            'status' => 'new',
            'status_label' => store_status_label('new'),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function handle_store_checkout(PDO $pdo): void
{
    try {
        $payload = read_json_input();
        $token = (string)($payload['checkout_token'] ?? '');
        if (!$token || empty($_SESSION['checkout_token']) || !hash_equals($_SESSION['checkout_token'], $token)) {
            json_response([
                'success' => false,
                'message' => 'Phiên đặt hàng đã được dùng hoặc đã hết hạn. Vui lòng tải lại trang trước khi đặt lại.',
            ], 409);
        }

        $order = create_store_order($pdo, $payload);
        $nextToken = ensure_checkout_token(true);
        json_response([
            'success' => true,
            'message' => 'Đơn hàng đã được ghi nhận.',
            'order' => $order,
            'next_checkout_token' => $nextToken,
        ]);
    } catch (AppException $e) {
        json_response(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (Throwable $e) {
        error_log($e);
        json_response([
            'success' => false,
            'message' => APP_ENV === 'dev' ? 'Lỗi xử lý đơn hàng: ' . $e->getMessage() : 'Không thể tạo đơn hàng lúc này. Vui lòng thử lại sau.',
        ], 500);
    }
}

function admin_hash_is_placeholder(string $hash): bool
{
    return str_contains($hash, 'REPLACE_THIS_WITH_REAL_PASSWORD_HASH') || str_contains($hash, 'placeholder');
}

function admin_current_user(): ?array
{
    $admin = $_SESSION['admin_user'] ?? null;
    return is_array($admin) ? $admin : null;
}

function admin_is_logged_in(): bool
{
    return admin_current_user() !== null;
}

function admin_can_manage_config(): bool
{
    $role = admin_current_user()['role'] ?? '';
    return in_array($role, ['owner', 'admin'], true);
}

function admin_require_login(): void
{
    if (!admin_is_logged_in()) {
        redirect_to('admin.php?login=1');
    }
}

function admin_placeholder_count(PDO $pdo): int
{
    $count = 0;
    $rows = $pdo->query('SELECT password_hash FROM admin_users WHERE is_active = 1')->fetchAll();
    foreach ($rows as $row) {
        if (admin_hash_is_placeholder((string)$row['password_hash'])) {
            $count++;
        }
    }
    return $count;
}

function admin_login(PDO $pdo, string $username, string $password): void
{
    $stmt = $pdo->prepare(
        'SELECT admin_id, username, password_hash, full_name, role, is_active FROM admin_users WHERE username = ? LIMIT 1'
    );
    $stmt->execute([trim($username)]);
    $admin = $stmt->fetch();

    if (!$admin || (int)$admin['is_active'] !== 1) {
        throw new AppException('Tài khoản hoặc mật khẩu chưa đúng.');
    }
    if (admin_hash_is_placeholder((string)$admin['password_hash'])) {
        throw new AppException('Tài khoản admin đang dùng password_hash placeholder. Hãy cập nhật hash thật trước khi đăng nhập.');
    }
    if (!password_verify($password, (string)$admin['password_hash'])) {
        throw new AppException('Tài khoản hoặc mật khẩu chưa đúng.');
    }

    session_regenerate_id(true);
    $_SESSION['admin_user'] = [
        'admin_id' => (int)$admin['admin_id'],
        'username' => (string)$admin['username'],
        'full_name' => (string)($admin['full_name'] ?: $admin['username']),
        'role' => (string)$admin['role'],
    ];
}

function admin_logout(): void
{
    unset($_SESSION['admin_user']);
    session_regenerate_id(true);
}

function admin_status_label(?string $status): string
{
    $status = $status ?: 'unknown';
    return [
        'new' => 'Mới',
        'confirmed' => 'Đã xác nhận',
        'ready' => 'Sẵn sàng giao',
        'done' => 'Hoàn thành',
        'cancelled' => 'Đã hủy',
        'ordered' => 'Đã đặt NCC',
        'received' => 'Đã nhận hàng',
        'unknown' => 'Không xác định',
    ][$status] ?? $status;
}

function admin_allowed_transition(?string $from, string $to): bool
{
    $from = $from ?: 'unknown';
    if ($from === $to) {
        return false;
    }
    if (in_array($from, ['done', 'cancelled'], true)) {
        return false;
    }
    if ($to === 'cancelled') {
        return !in_array($from, ['done', 'cancelled'], true);
    }
    $map = [
        'new' => ['confirmed'],
        'confirmed' => ['ordered', 'ready'],
        'ordered' => ['received'],
        'received' => ['ready'],
        'ready' => ['done'],
    ];
    return in_array($to, $map[$from] ?? [], true);
}

function insert_inventory_movement(PDO $pdo, array $data): string
{
    $movementId = unique_id($pdo, 'movement');
    $stmt = $pdo->prepare(
        "INSERT INTO inventory_movements
          (movement_id, movement_type, ref_type, ref_id, lot_id, product_id, source_location,
           uom_id, qty_uom, conversion_to_base_snapshot, qty_base, cost_per_base_unit_vnd, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $movementId,
        $data['movement_type'],
        $data['ref_type'] ?? 'MANUAL',
        $data['ref_id'] ?? null,
        $data['lot_id'] ?? null,
        $data['product_id'],
        $data['source_location'] ?? 'Unknown',
        $data['uom_id'] ?? null,
        $data['qty_uom'] ?? 0,
        $data['conversion_to_base_snapshot'] ?? 1,
        $data['qty_base'],
        $data['cost_per_base_unit_vnd'] ?? 0,
        $data['note'] ?? null,
    ]);

    return $movementId;
}

function admin_complete_order_fefo(PDO $pdo, string $orderId): void
{
    $pdo->beginTransaction();
    try {
        $orderStmt = $pdo->prepare('SELECT order_id, status FROM orders WHERE order_id = ? FOR UPDATE');
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();
        if (!$order) {
            throw new AppException('Không tìm thấy đơn hàng.');
        }
        if ($order['status'] === 'done') {
            throw new AppException('Đơn hàng đã hoàn tất trước đó, không trừ tồn lần hai.');
        }
        if ($order['status'] === 'cancelled') {
            throw new AppException('Đơn hàng đã hủy không thể hoàn tất.');
        }
        if ($order['status'] !== 'ready') {
            throw new AppException('Chỉ đơn ở trạng thái sẵn sàng giao mới được hoàn tất.');
        }

        $itemsStmt = $pdo->prepare(
            'SELECT order_item_id, line_no, product_id, product_name_snapshot, uom_id,
                    uom_label_snapshot, source_location, qty_uom,
                    conversion_to_base_snapshot, qty_base
             FROM order_items
             WHERE order_id = ?
             ORDER BY line_no'
        );
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll();
        if (!$items) {
            throw new AppException('Đơn hàng không có dòng sản phẩm.');
        }

        $lotStmt = $pdo->prepare(
            "SELECT lot_id, product_id, source_location, qty_base_on_hand, qty_base_reserved,
                    cost_per_base_unit_vnd, expiry_date
             FROM inventory_lots
             WHERE product_id = ?
               AND (qty_base_on_hand - qty_base_reserved) > 0
             ORDER BY expiry_date IS NULL, expiry_date ASC, received_date ASC, lot_id ASC
             FOR UPDATE"
        );
        $updateLotStmt = $pdo->prepare(
            'UPDATE inventory_lots SET qty_base_on_hand = qty_base_on_hand - ? WHERE lot_id = ?'
        );
        $allocatedStmt = $pdo->prepare(
            'UPDATE order_items SET allocated_lot_id = COALESCE(allocated_lot_id, ?) WHERE order_item_id = ?'
        );
        $allocationStmt = $pdo->prepare(
            'INSERT INTO order_item_allocations
              (order_item_id, order_id, lot_id, product_id, qty_base, movement_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($items as $item) {
            $remaining = (float)$item['qty_base'];
            $lotStmt->execute([$item['product_id']]);
            $lots = $lotStmt->fetchAll();

            foreach ($lots as $lot) {
                if ($remaining <= 0.0001) {
                    break;
                }
                $available = (float)$lot['qty_base_on_hand'] - (float)$lot['qty_base_reserved'];
                if ($available <= 0) {
                    continue;
                }
                $take = min($remaining, $available);
                $take = round($take, 3);
                $updateLotStmt->execute([$take, $lot['lot_id']]);
                $qtyUomOut = round($take / max((float)$item['conversion_to_base_snapshot'], 0.001), 3);
                $movementId = insert_inventory_movement($pdo, [
                    'movement_type' => 'OUT',
                    'ref_type' => 'ORDER',
                    'ref_id' => $orderId,
                    'lot_id' => $lot['lot_id'],
                    'product_id' => $item['product_id'],
                    'source_location' => $lot['source_location'],
                    'uom_id' => $item['uom_id'],
                    'qty_uom' => $qtyUomOut,
                    'conversion_to_base_snapshot' => $item['conversion_to_base_snapshot'],
                    'qty_base' => $take,
                    'cost_per_base_unit_vnd' => $lot['cost_per_base_unit_vnd'],
                    'note' => 'Xuất kho FEFO cho đơn ' . $orderId . ', dòng ' . $item['line_no'],
                ]);
                $allocationStmt->execute([
                    $item['order_item_id'],
                    $orderId,
                    $lot['lot_id'],
                    $item['product_id'],
                    $take,
                    $movementId,
                ]);
                $allocatedStmt->execute([$lot['lot_id'], $item['order_item_id']]);
                $remaining = round($remaining - $take, 3);
            }

            if ($remaining > 0.0001) {
                throw new AppException(
                    'Không đủ tồn kho cho ' . $item['product_name_snapshot'] .
                    '. Thiếu ' . decimal_display($remaining) . ' base unit.'
                );
            }
        }

        $statusStmt = $pdo->prepare("UPDATE orders SET status = 'done' WHERE order_id = ? AND status <> 'done'");
        $statusStmt->execute([$orderId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_change_order_status(PDO $pdo, string $orderId, string $newStatus): void
{
    if ($newStatus === 'done') {
        admin_complete_order_fefo($pdo, $orderId);
        return;
    }

    $validStatuses = ['new', 'confirmed', 'ready', 'done', 'cancelled', 'ordered', 'received'];
    if (!in_array($newStatus, $validStatuses, true)) {
        throw new AppException('Trạng thái mới không hợp lệ.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT order_id, status FROM orders WHERE order_id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new AppException('Không tìm thấy đơn hàng.');
        }
        if (!admin_allowed_transition((string)$order['status'], $newStatus)) {
            throw new AppException('Không thể chuyển trạng thái từ ' . admin_status_label((string)$order['status']) . ' sang ' . admin_status_label($newStatus) . '.');
        }

        $update = $pdo->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
        $update->execute([$newStatus, $orderId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_receive_stock(PDO $pdo, array $input): void
{
    $productId = trim((string)($input['product_id'] ?? ''));
    $uomId = trim((string)($input['uom_id'] ?? ''));
    $qtyUom = (float)($input['qty_uom'] ?? 0);
    $receivedDate = trim((string)($input['received_date'] ?? date('Y-m-d')));
    $expiryDate = trim((string)($input['expiry_date'] ?? ''));
    $inputSource = admin_valid_source_filter(trim((string)($input['source_location'] ?? '')));
    $supplier = trim((string)($input['supplier_name'] ?? ''));
    $costPerBase = max(0, (int)($input['cost_per_base_unit_vnd'] ?? 0));
    $note = trim((string)($input['note'] ?? ''));

    if ($productId === '' || $uomId === '' || $qtyUom <= 0) {
        throw new AppException('Vui lòng chọn sản phẩm, UOM nhập và số lượng hợp lệ.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $receivedDate)) {
        throw new AppException('Ngày nhập không hợp lệ.');
    }
    if ($expiryDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryDate)) {
        throw new AppException('Hạn sử dụng không hợp lệ.');
    }

    $stmt = $pdo->prepare(
        "SELECT p.product_id, p.default_source, p.shelf_life_value, p.shelf_life_unit,
                u.uom_id, u.conversion_to_base
         FROM products p
         JOIN product_uoms u ON u.product_id = p.product_id
         WHERE p.product_id = ?
           AND u.uom_id = ?
           AND p.is_active = 1
           AND u.is_active = 1
           AND u.is_purchasable = 1"
    );
    $stmt->execute([$productId, $uomId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new AppException('Sản phẩm hoặc UOM nhập không hợp lệ.');
    }

    if ($expiryDate === '') {
        $expiryDate = admin_auto_expiry_date(
            $receivedDate,
            (int)$row['shelf_life_value'],
            (string)($row['shelf_life_unit'] ?? '')
        );
    }

    $conversion = (float)$row['conversion_to_base'];
    $qtyBase = round($qtyUom * $conversion, 3);
    $sourceLocation = $inputSource !== '' ? $inputSource : ($row['default_source'] ?: 'Unknown');
    if ($qtyBase <= 0) {
        throw new AppException('Số lượng quy đổi base UOM chưa hợp lệ.');
    }

    $pdo->beginTransaction();
    try {
        $lotId = unique_id($pdo, 'lot');
        $insertLot = $pdo->prepare(
            "INSERT INTO inventory_lots
              (lot_id, product_id, source_location, qty_base_on_hand, qty_base_reserved,
               received_date, expiry_date, supplier_name, cost_per_base_unit_vnd,
               received_uom_id, received_qty_uom, conversion_to_base_snapshot, note)
             VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insertLot->execute([
            $lotId,
            $productId,
            $sourceLocation,
            $qtyBase,
            $receivedDate,
            $expiryDate ?: null,
            $supplier ?: null,
            $costPerBase,
            $uomId,
            $qtyUom,
            $conversion,
            $note ?: null,
        ]);

        insert_inventory_movement($pdo, [
            'movement_type' => 'IN',
            'ref_type' => 'LOT',
            'ref_id' => $lotId,
            'lot_id' => $lotId,
            'product_id' => $productId,
            'source_location' => $sourceLocation,
            'uom_id' => $uomId,
            'qty_uom' => $qtyUom,
            'conversion_to_base_snapshot' => $conversion,
            'qty_base' => $qtyBase,
            'cost_per_base_unit_vnd' => $costPerBase,
            'note' => $note ?: 'Nhập hàng thủ công',
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_adjust_lot(PDO $pdo, array $input): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc dieu chinh ton kho thu cong.');
    }

    $lotId = trim((string)($input['lot_id'] ?? ''));
    $newQty = (float)($input['new_qty_base'] ?? -1);
    $reason = trim((string)($input['reason'] ?? ''));

    if ($lotId === '' || $newQty < 0) {
        throw new AppException('Số tồn mới không hợp lệ.');
    }
    if (mb_strlen($reason) < 5) {
        throw new AppException('Điều chỉnh tồn kho bắt buộc nhập lý do rõ ràng.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT lot_id, product_id, source_location, qty_base_on_hand, qty_base_reserved, cost_per_base_unit_vnd
             FROM inventory_lots WHERE lot_id = ? FOR UPDATE'
        );
        $stmt->execute([$lotId]);
        $lot = $stmt->fetch();
        if (!$lot) {
            throw new AppException('Không tìm thấy lô tồn kho.');
        }
        if ($newQty < (float)$lot['qty_base_reserved']) {
            throw new AppException('Không thể chỉnh tồn nhỏ hơn số lượng đang reserve.');
        }
        $delta = round($newQty - (float)$lot['qty_base_on_hand'], 3);
        if (abs($delta) < 0.0001) {
            throw new AppException('Số tồn mới không thay đổi.');
        }

        $update = $pdo->prepare('UPDATE inventory_lots SET qty_base_on_hand = ? WHERE lot_id = ?');
        $update->execute([$newQty, $lotId]);
        insert_inventory_movement($pdo, [
            'movement_type' => 'ADJUST',
            'ref_type' => 'MANUAL',
            'ref_id' => $lotId,
            'lot_id' => $lotId,
            'product_id' => $lot['product_id'],
            'source_location' => $lot['source_location'],
            'uom_id' => null,
            'qty_uom' => $delta,
            'conversion_to_base_snapshot' => 1,
            'qty_base' => $delta,
            'cost_per_base_unit_vnd' => $lot['cost_per_base_unit_vnd'],
            'note' => $reason,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_void_lot(PDO $pdo, array $input): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc dong lo ton kho.');
    }

    $lotId = trim((string)($input['lot_id'] ?? ''));
    $reason = trim((string)($input['reason'] ?? ''));
    if ($lotId === '') {
        throw new AppException('Khong tim thay ma lo can dong.');
    }
    if (mb_strlen($reason) < 5) {
        throw new AppException('Dong lo bat buoc nhap ly do ro rang.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT lot_id, product_id, source_location, qty_base_on_hand, qty_base_reserved, cost_per_base_unit_vnd
             FROM inventory_lots WHERE lot_id = ? FOR UPDATE'
        );
        $stmt->execute([$lotId]);
        $lot = $stmt->fetch();
        if (!$lot) {
            throw new AppException('Khong tim thay lo ton kho.');
        }
        if ((float)$lot['qty_base_reserved'] > 0.0001) {
            throw new AppException('Khong the dong lo dang co so luong reserved.');
        }
        $currentQty = (float)$lot['qty_base_on_hand'];
        if ($currentQty <= 0.0001) {
            throw new AppException('Lo nay da ve 0, khong can dong tiep.');
        }

        $update = $pdo->prepare('UPDATE inventory_lots SET qty_base_on_hand = 0 WHERE lot_id = ?');
        $update->execute([$lotId]);
        insert_inventory_movement($pdo, [
            'movement_type' => 'ADJUST',
            'ref_type' => 'MANUAL',
            'ref_id' => $lotId,
            'lot_id' => $lotId,
            'product_id' => $lot['product_id'],
            'source_location' => $lot['source_location'],
            'uom_id' => null,
            'qty_uom' => -$currentQty,
            'conversion_to_base_snapshot' => 1,
            'qty_base' => -$currentQty,
            'cost_per_base_unit_vnd' => $lot['cost_per_base_unit_vnd'],
            'note' => 'VOID_LOT: ' . $reason,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_update_setting(PDO $pdo, string $key, string $value): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tài khoản staff không được sửa cấu hình.');
    }
    $allowed = ['store_name', 'free_ship_threshold', 'default_shipping_zone_id', 'store_phone', 'zalo_link'];
    if (!in_array($key, $allowed, true)) {
        throw new AppException('Cấu hình không được phép cập nhật.');
    }
    if ($key === 'free_ship_threshold' && (!ctype_digit($value) || (int)$value < 0)) {
        throw new AppException('Ngưỡng miễn phí ship không hợp lệ.');
    }
    if ($key === 'default_shipping_zone_id') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM shipping_zones WHERE zone_id = ? AND is_active = 1');
        $stmt->execute([$value]);
        if ((int)$stmt->fetchColumn() === 0) {
            throw new AppException('Vùng giao hàng mặc định không hợp lệ.');
        }
    }
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([$key, $value]);
}

function admin_save_shipping_zone(PDO $pdo, array $input): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc sua shipping zones.');
    }

    $zoneId = trim((string)($input['zone_id'] ?? ''));
    $zoneName = trim((string)($input['zone_name'] ?? ''));
    $fee = (int)($input['fee_vnd'] ?? 0);
    $isDefault = !empty($input['is_default']) ? 1 : 0;
    $isActive = !empty($input['is_active']) ? 1 : 0;

    if (!preg_match('/^[A-Za-z0-9_-]{3,40}$/', $zoneId)) {
        throw new AppException('Ma vung giao hang khong hop le.');
    }
    if ($zoneName === '' || mb_strlen($zoneName) > 120 || $fee < 0) {
        throw new AppException('Ten vung hoac phi giao hang khong hop le.');
    }
    if ($isDefault === 1) {
        $isActive = 1;
    }

    $pdo->beginTransaction();
    try {
        if ($isDefault === 1) {
            $pdo->exec('UPDATE shipping_zones SET is_default = 0');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO shipping_zones (zone_id, zone_name, fee_vnd, is_default, is_active)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               zone_name = VALUES(zone_name),
               fee_vnd = VALUES(fee_vnd),
               is_default = VALUES(is_default),
               is_active = VALUES(is_active),
               updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$zoneId, $zoneName, $fee, $isDefault, $isActive]);

        if ($isDefault === 1) {
            admin_update_setting($pdo, 'default_shipping_zone_id', $zoneId);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_update_product(PDO $pdo, array $input): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc sua san pham.');
    }

    $productId = trim((string)($input['product_id'] ?? ''));
    $productName = trim((string)($input['product_name'] ?? ''));
    $productSlug = trim((string)($input['product_slug'] ?? ''));
    $categoryId = trim((string)($input['category_id'] ?? ''));
    $defaultSource = trim((string)($input['default_source'] ?? 'Unknown'));
    $shortDescription = trim((string)($input['short_description'] ?? ''));
    $fullDescription = trim((string)($input['full_description'] ?? ''));
    $ingredients = trim((string)($input['ingredients'] ?? ''));
    $baseUomLabel = trim((string)($input['base_uom_label'] ?? ''));
    $shelfLifeValue = max(0, (int)($input['shelf_life_value'] ?? 0));
    $shelfLifeUnit = (string)($input['shelf_life_unit'] ?? '');
    $isActive = !empty($input['is_active']) ? 1 : 0;

    if ($productId === '' || $productName === '' || mb_strlen($productName) > 200) {
        throw new AppException('Thong tin san pham khong hop le.');
    }
    if (!preg_match('/^[a-z0-9][a-z0-9-]{1,218}[a-z0-9]$/', $productSlug)) {
        throw new AppException('Slug san pham khong hop le. Chi dung chu thuong, so va dau gach ngang.');
    }
    if (!in_array($defaultSource, ['Binh Dinh', 'Gia Lai', 'Unknown'], true)) {
        throw new AppException('Nguon hang khong hop le.');
    }
    if ($isActive === 1 && $baseUomLabel === '') {
        throw new AppException('San pham active bat buoc co base UOM label.');
    }
    if (!in_array($shelfLifeUnit, ['', 'days', 'months'], true)) {
        throw new AppException('Don vi shelf life khong hop le.');
    }

    $catStmt = $pdo->prepare('SELECT category_name FROM categories WHERE category_id = ?');
    $catStmt->execute([$categoryId]);
    $categoryName = $catStmt->fetchColumn();
    if (!$categoryName) {
        throw new AppException('Danh muc san pham khong hop le.');
    }
    $slugStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE product_slug = ? AND product_id <> ?');
    $slugStmt->execute([$productSlug, $productId]);
    if ((int)$slugStmt->fetchColumn() > 0) {
        throw new AppException('Slug san pham da ton tai.');
    }

    $stmt = $pdo->prepare(
        'UPDATE products
         SET product_name = ?,
             product_slug = ?,
             category_id = ?,
             category_label = ?,
             default_source = ?,
             short_description = ?,
             full_description = ?,
             ingredients = ?,
             base_uom_label = ?,
             shelf_life_value = ?,
             shelf_life_unit = ?,
             is_active = ?
         WHERE product_id = ?'
    );
    $stmt->execute([
        $productName,
        $productSlug,
        $categoryId,
        $categoryName,
        $defaultSource,
        $shortDescription ?: null,
        $fullDescription ?: null,
        $ingredients ?: null,
        $baseUomLabel,
        $shelfLifeValue,
        $shelfLifeUnit,
        $isActive,
        $productId,
    ]);
    if ($stmt->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM products WHERE product_id = ?');
        $exists->execute([$productId]);
        if ((int)$exists->fetchColumn() === 0) {
            throw new AppException('Khong tim thay san pham can sua.');
        }
    }
}

function admin_create_product(PDO $pdo, array $input): string
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc tao san pham.');
    }

    $productId = trim((string)($input['product_id'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_-]{3,40}$/', $productId)) {
        throw new AppException('Ma san pham moi khong hop le.');
    }
    $exists = $pdo->prepare('SELECT COUNT(*) FROM products WHERE product_id = ?');
    $exists->execute([$productId]);
    if ((int)$exists->fetchColumn() > 0) {
        throw new AppException('Ma san pham da ton tai.');
    }

    $productName = trim((string)($input['product_name'] ?? ''));
    $productSlug = trim((string)($input['product_slug'] ?? ''));
    $categoryId = trim((string)($input['category_id'] ?? ''));
    $defaultSource = trim((string)($input['default_source'] ?? 'Unknown'));
    $baseUomLabel = trim((string)($input['base_uom_label'] ?? ''));
    if ($productName === '' || !preg_match('/^[a-z0-9][a-z0-9-]{1,218}[a-z0-9]$/', $productSlug) || $baseUomLabel === '') {
        throw new AppException('Ten, slug va base UOM cua san pham moi la bat buoc.');
    }
    if (!in_array($defaultSource, ['Binh Dinh', 'Gia Lai', 'Unknown'], true)) {
        throw new AppException('Nguon hang khong hop le.');
    }
    $catStmt = $pdo->prepare('SELECT category_name FROM categories WHERE category_id = ?');
    $catStmt->execute([$categoryId]);
    $categoryName = $catStmt->fetchColumn();
    if (!$categoryName) {
        throw new AppException('Danh muc san pham khong hop le.');
    }
    $slugStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE product_slug = ?');
    $slugStmt->execute([$productSlug]);
    if ((int)$slugStmt->fetchColumn() > 0) {
        throw new AppException('Slug san pham da ton tai.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO products
          (product_id, product_name, product_slug, category_id, category_label, default_source,
           short_description, full_description, ingredients, base_uom_label,
           shelf_life_value, shelf_life_unit, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $productId,
        $productName,
        $productSlug,
        $categoryId,
        $categoryName,
        $defaultSource,
        trim((string)($input['short_description'] ?? '')) ?: null,
        trim((string)($input['full_description'] ?? '')) ?: null,
        trim((string)($input['ingredients'] ?? '')) ?: null,
        $baseUomLabel,
        max(0, (int)($input['shelf_life_value'] ?? 0)),
        in_array((string)($input['shelf_life_unit'] ?? ''), ['', 'days', 'months'], true) ? (string)($input['shelf_life_unit'] ?? '') : '',
        !empty($input['is_active']) ? 1 : 0,
    ]);

    return $productId;
}

function admin_save_uom(PDO $pdo, array $input): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc sua UOM.');
    }

    $uomId = trim((string)($input['uom_id'] ?? ''));
    $productId = trim((string)($input['product_id'] ?? ''));
    $uomLabel = trim((string)($input['uom_label'] ?? ''));
    $conversion = (float)($input['conversion_to_base'] ?? 0);
    $unitPrice = max(0, (int)($input['unit_price_vnd'] ?? 0));
    $costPrice = max(0, (int)($input['cost_price_vnd'] ?? 0));
    $isBaseUnit = !empty($input['is_base_unit']) ? 1 : 0;
    $isDefault = !empty($input['is_default']) ? 1 : 0;
    $isSellable = !empty($input['is_sellable']) ? 1 : 0;
    $isPurchasable = !empty($input['is_purchasable']) ? 1 : 0;
    $isActive = !empty($input['is_active']) ? 1 : 0;
    $sortOrder = (int)($input['sort_order'] ?? 0);
    $note = trim((string)($input['note'] ?? ''));

    if (!preg_match('/^[A-Za-z0-9_-]{3,60}$/', $uomId)) {
        throw new AppException('Ma UOM khong hop le.');
    }
    if ($productId === '' || $uomLabel === '' || mb_strlen($uomLabel) > 120 || $conversion <= 0) {
        throw new AppException('Thong tin UOM khong hop le.');
    }
    if ($isDefault === 1) {
        $isActive = 1;
    }

    $productStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE product_id = ?');
    $productStmt->execute([$productId]);
    if ((int)$productStmt->fetchColumn() === 0) {
        throw new AppException('San pham cua UOM khong ton tai.');
    }

    $pdo->beginTransaction();
    try {
        $existingStmt = $pdo->prepare('SELECT product_id, conversion_to_base, is_default FROM product_uoms WHERE uom_id = ? FOR UPDATE');
        $existingStmt->execute([$uomId]);
        $existing = $existingStmt->fetch();
        if ($existing && $existing['product_id'] !== $productId) {
            throw new AppException('Khong duoc doi UOM sang san pham khac.');
        }
        if ($existing && abs((float)$existing['conversion_to_base'] - $conversion) > 0.0001) {
            $refStmt = $pdo->prepare(
                'SELECT
                   (SELECT COUNT(*) FROM order_items WHERE uom_id = ?) AS order_count,
                   (SELECT COUNT(*) FROM inventory_movements WHERE uom_id = ?) AS movement_count'
            );
            $refStmt->execute([$uomId, $uomId]);
            $refs = $refStmt->fetch();
            if (((int)$refs['order_count'] + (int)$refs['movement_count']) > 0) {
                throw new AppException('UOM da phat sinh don/movement nen khong duoc doi conversion_to_base. Hay tao UOM moi de ap dung tuong lai.');
            }
        }

        if ($isDefault === 1) {
            $reset = $pdo->prepare('UPDATE product_uoms SET is_default = 0 WHERE product_id = ?');
            $reset->execute([$productId]);
        }
        if ($isBaseUnit === 1) {
            $resetBase = $pdo->prepare('UPDATE product_uoms SET is_base_unit = 0 WHERE product_id = ?');
            $resetBase->execute([$productId]);
        }

        if ($existing) {
            $stmt = $pdo->prepare(
                'UPDATE product_uoms
                 SET uom_label = ?,
                     conversion_to_base = ?,
                     unit_price_vnd = ?,
                     cost_price_vnd = ?,
                     is_base_unit = ?,
                     is_default = ?,
                     is_sellable = ?,
                     is_purchasable = ?,
                     is_active = ?,
                     sort_order = ?,
                     note = ?
                 WHERE uom_id = ?'
            );
            $stmt->execute([
                $uomLabel,
                $conversion,
                $unitPrice,
                $costPrice,
                $isBaseUnit,
                $isDefault,
                $isSellable,
                $isPurchasable,
                $isActive,
                $sortOrder,
                $note ?: null,
                $uomId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO product_uoms
                  (uom_id, product_id, uom_label, conversion_to_base, unit_price_vnd, cost_price_vnd,
                   is_base_unit, is_default, is_sellable, is_purchasable, is_active, sort_order, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $uomId,
                $productId,
                $uomLabel,
                $conversion,
                $unitPrice,
                $costPrice,
                $isBaseUnit,
                $isDefault,
                $isSellable,
                $isPurchasable,
                $isActive,
                $sortOrder,
                $note ?: null,
            ]);
        }

        $countDefault = $pdo->prepare('SELECT COUNT(*) FROM product_uoms WHERE product_id = ? AND is_default = 1');
        $countDefault->execute([$productId]);
        if ((int)$countDefault->fetchColumn() === 0) {
            $fallback = $pdo->prepare(
                'SELECT uom_id FROM product_uoms
                 WHERE product_id = ? AND is_active = 1
                 ORDER BY is_base_unit DESC, conversion_to_base ASC, sort_order ASC
                 LIMIT 1'
            );
            $fallback->execute([$productId]);
            $fallbackId = $fallback->fetchColumn() ?: $uomId;
            $makeDefault = $pdo->prepare('UPDATE product_uoms SET is_default = 1 WHERE uom_id = ?');
            $makeDefault->execute([$fallbackId]);
        }
        $countBase = $pdo->prepare('SELECT COUNT(*) FROM product_uoms WHERE product_id = ? AND is_base_unit = 1 AND is_active = 1');
        $countBase->execute([$productId]);
        if ((int)$countBase->fetchColumn() === 0) {
            $fallbackBase = $pdo->prepare(
                'SELECT uom_id FROM product_uoms
                 WHERE product_id = ? AND is_active = 1
                 ORDER BY conversion_to_base ASC, sort_order ASC
                 LIMIT 1'
            );
            $fallbackBase->execute([$productId]);
            $baseId = $fallbackBase->fetchColumn();
            if ($baseId) {
                $makeBase = $pdo->prepare('UPDATE product_uoms SET is_base_unit = 1 WHERE uom_id = ?');
                $makeBase->execute([$baseId]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_deactivate_uom(PDO $pdo, string $uomId): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc sua UOM.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT uom_id, product_id, is_default, is_base_unit FROM product_uoms WHERE uom_id = ? FOR UPDATE');
        $stmt->execute([$uomId]);
        $uom = $stmt->fetch();
        if (!$uom) {
            throw new AppException('Khong tim thay UOM.');
        }
        if ((int)$uom['is_default'] === 1 || (int)$uom['is_base_unit'] === 1) {
            $otherActive = $pdo->prepare(
                'SELECT COUNT(*) FROM product_uoms
                 WHERE product_id = ? AND uom_id <> ? AND is_active = 1'
            );
            $otherActive->execute([$uom['product_id'], $uomId]);
            if ((int)$otherActive->fetchColumn() === 0) {
                throw new AppException('Khong the inactive UOM default/base khi chua co UOM active khac thay the.');
            }
        }

        $update = $pdo->prepare('UPDATE product_uoms SET is_active = 0, is_sellable = 0, is_purchasable = 0, is_default = 0 WHERE uom_id = ?');
        $update->execute([$uomId]);

        if ((int)$uom['is_default'] === 1) {
            $fallback = $pdo->prepare(
                'SELECT uom_id FROM product_uoms
                 WHERE product_id = ? AND is_active = 1
                 ORDER BY is_base_unit DESC, conversion_to_base ASC, sort_order ASC
                 LIMIT 1'
            );
            $fallback->execute([$uom['product_id']]);
            $fallbackId = $fallback->fetchColumn();
            if ($fallbackId) {
                $makeDefault = $pdo->prepare('UPDATE product_uoms SET is_default = 1 WHERE uom_id = ?');
                $makeDefault->execute([$fallbackId]);
            }
        }
        if ((int)$uom['is_base_unit'] === 1) {
            $fallbackBase = $pdo->prepare(
                'SELECT uom_id FROM product_uoms
                 WHERE product_id = ? AND is_active = 1
                 ORDER BY conversion_to_base ASC, sort_order ASC
                 LIMIT 1'
            );
            $fallbackBase->execute([$uom['product_id']]);
            $baseId = $fallbackBase->fetchColumn();
            if ($baseId) {
                $makeBase = $pdo->prepare('UPDATE product_uoms SET is_base_unit = 1 WHERE uom_id = ?');
                $makeBase->execute([$baseId]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_toggle_uom(PDO $pdo, string $uomId, int $isActive): void
{
    if ($isActive === 1) {
        if (!admin_can_manage_config()) {
            throw new AppException('Tai khoan staff khong duoc sua UOM.');
        }
        $stmt = $pdo->prepare('UPDATE product_uoms SET is_active = 1 WHERE uom_id = ?');
        $stmt->execute([$uomId]);
        if ($stmt->rowCount() === 0) {
            throw new AppException('Khong tim thay UOM.');
        }
        return;
    }
    admin_deactivate_uom($pdo, $uomId);
}

function admin_delete_uom_if_unused(PDO $pdo, string $uomId): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc xoa UOM.');
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT uom_id, product_id, is_default, is_base_unit FROM product_uoms WHERE uom_id = ? FOR UPDATE');
        $stmt->execute([$uomId]);
        $uom = $stmt->fetch();
        if (!$uom) {
            throw new AppException('Khong tim thay UOM.');
        }
        $refs = $pdo->prepare(
            'SELECT
               (SELECT COUNT(*) FROM order_items WHERE uom_id = ?) AS order_count,
               (SELECT COUNT(*) FROM inventory_movements WHERE uom_id = ?) AS movement_count,
               (SELECT COUNT(*) FROM inventory_lots WHERE received_uom_id = ?) AS lot_count,
               (SELECT COUNT(*) FROM plan_items WHERE uom_id = ?) AS plan_count,
               (SELECT COUNT(*) FROM purchase_plan_receipt_items WHERE uom_id = ?) AS receipt_count'
        );
        $refs->execute([$uomId, $uomId, $uomId, $uomId, $uomId]);
        $row = $refs->fetch() ?: [];
        $refCount = (int)($row['order_count'] ?? 0) + (int)($row['movement_count'] ?? 0)
            + (int)($row['lot_count'] ?? 0) + (int)($row['plan_count'] ?? 0) + (int)($row['receipt_count'] ?? 0);
        if ($refCount > 0) {
            throw new AppException('UOM da co giao dich/movement/PO, khong the xoa. Hay inactive.');
        }
        if ((int)$uom['is_default'] === 1 || (int)$uom['is_base_unit'] === 1) {
            $otherActive = $pdo->prepare(
                'SELECT uom_id FROM product_uoms
                 WHERE product_id = ? AND uom_id <> ? AND is_active = 1
                 ORDER BY is_base_unit DESC, conversion_to_base ASC, sort_order ASC
                 LIMIT 1'
            );
            $otherActive->execute([$uom['product_id'], $uomId]);
            $fallback = $otherActive->fetchColumn();
            if (!$fallback) {
                throw new AppException('Khong the xoa UOM duy nhat cua san pham.');
            }
            if ((int)$uom['is_default'] === 1) {
                $pdo->prepare('UPDATE product_uoms SET is_default = 1 WHERE uom_id = ?')->execute([$fallback]);
            }
            if ((int)$uom['is_base_unit'] === 1) {
                $pdo->prepare('UPDATE product_uoms SET is_base_unit = 1 WHERE uom_id = ?')->execute([$fallback]);
            }
        }
        $pdo->prepare('DELETE FROM product_uoms WHERE uom_id = ?')->execute([$uomId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_toggle_product(PDO $pdo, string $productId, int $isActive): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tài khoản staff không được sửa sản phẩm.');
    }
    $stmt = $pdo->prepare('UPDATE products SET is_active = ? WHERE product_id = ?');
    $stmt->execute([$isActive ? 1 : 0, $productId]);
    if ($stmt->rowCount() === 0) {
        throw new AppException('Không tìm thấy sản phẩm cần cập nhật.');
    }
}

function admin_product_reference_counts(PDO $pdo, string $productId): array
{
    $stmt = $pdo->prepare(
        'SELECT
           (SELECT COUNT(*) FROM order_items WHERE product_id = ?) AS order_items,
           (SELECT COUNT(*) FROM inventory_lots WHERE product_id = ?) AS inventory_lots,
           (SELECT COUNT(*) FROM inventory_movements WHERE product_id = ?) AS inventory_movements,
           (SELECT COUNT(*) FROM plan_items WHERE product_id = ?) AS plan_items,
           (SELECT COUNT(*) FROM purchase_plan_receipt_items WHERE product_id = ?) AS receipt_items'
    );
    $stmt->execute([$productId, $productId, $productId, $productId, $productId]);
    $row = $stmt->fetch() ?: [];
    return [
        'order_items' => (int)($row['order_items'] ?? 0),
        'inventory_lots' => (int)($row['inventory_lots'] ?? 0),
        'inventory_movements' => (int)($row['inventory_movements'] ?? 0),
        'plan_items' => (int)($row['plan_items'] ?? 0),
        'receipt_items' => (int)($row['receipt_items'] ?? 0),
    ];
}

function admin_delete_product(PDO $pdo, string $productId): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc xoa san pham.');
    }
    $productId = trim($productId);
    if ($productId === '') {
        throw new AppException('Ma san pham can xoa khong hop le.');
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT product_id FROM products WHERE product_id = ? FOR UPDATE');
        $stmt->execute([$productId]);
        if (!$stmt->fetch()) {
            throw new AppException('Khong tim thay san pham can xoa.');
        }
        $refs = admin_product_reference_counts($pdo, $productId);
        if (array_sum($refs) > 0) {
            throw new AppException('San pham da co giao dich/tồn kho/PO, khong the xoa. Hay dung Ngung hoat dong.');
        }
        $pdo->prepare('DELETE FROM products WHERE product_id = ?')->execute([$productId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_upload_product_image(PDO $pdo, array $post, array $files): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tài khoản staff không được cập nhật ảnh sản phẩm.');
    }
    $productId = trim((string)($post['product_id'] ?? ''));
    $isBase = (int)($post['is_base'] ?? 0) === 1 ? 1 : 0;
    $sortOrder = max(0, (int)($post['sort_order'] ?? 0));
    $alt = trim((string)($post['image_alt'] ?? ''));
    $file = $files['image_file'] ?? null;

    if ($productId === '' || !$file || !is_array($file) || (int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new AppException('Vui lòng chọn sản phẩm và file ảnh hợp lệ.');
    }

    if (empty($file['tmp_name']) || !is_file((string)$file['tmp_name'])) {
        throw new AppException('File upload khong hop le.');
    }

    $stmt = $pdo->prepare('SELECT product_id, product_name, product_slug FROM products WHERE product_id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        throw new AppException('Không tìm thấy sản phẩm.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, (string)$file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new AppException('Chỉ cho phép upload ảnh jpg/jpeg/png/webp.');
    }
    if ((int)$file['size'] > 5 * 1024 * 1024) {
        throw new AppException('Ảnh không được vượt quá 5MB.');
    }

    $safeProduct = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($product['product_slug'] ?: $productId)) ?: 'product';
    $filename = $safeProduct . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $allowed[$mime];
    $relativePath = 'products_image/' . $filename;
    $targetPath = __DIR__ . '/' . $relativePath;

    if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
        throw new AppException('Không thể lưu file ảnh upload.');
    }

    $pdo->beginTransaction();
    try {
        if ($isBase === 1) {
            $reset = $pdo->prepare('UPDATE product_images SET is_base = 0 WHERE product_id = ?');
            $reset->execute([$productId]);
        }
        $insert = $pdo->prepare(
            'INSERT INTO product_images (product_id, image_path, image_alt, is_base, sort_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $productId,
            $relativePath,
            $alt ?: (string)$product['product_name'],
            $isBase,
            $sortOrder,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_file($targetPath)) {
            @unlink($targetPath);
        }
        throw $e;
    }
}

function admin_save_product_image(PDO $pdo, array $input): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc sua anh san pham.');
    }

    $imageId = (int)($input['image_id'] ?? 0);
    $productId = trim((string)($input['product_id'] ?? ''));
    $imagePath = trim((string)($input['image_path'] ?? ''));
    $imageAlt = trim((string)($input['image_alt'] ?? ''));
    $sortOrder = (int)($input['sort_order'] ?? 0);
    $isActive = !empty($input['is_active']) ? 1 : 0;
    $isBase = !empty($input['is_base']) ? 1 : 0;
    if ($imagePath !== '') {
        $imagePath = str_replace('\\', '/', ltrim($imagePath, '/'));
        if (!preg_match('#^products_image/[A-Za-z0-9._/-]+\.(jpe?g|png|webp)$#i', $imagePath)) {
            throw new AppException('Path anh chi duoc la jpg/jpeg/png/webp trong products_image/.');
        }
    }

    $pdo->beginTransaction();
    try {
        if ($imageId <= 0) {
            if ($productId === '' || $imagePath === '') {
                throw new AppException('Vui long chon san pham va nhap path anh.');
            }
            $productStmt = $pdo->prepare('SELECT product_id, product_name FROM products WHERE product_id = ? FOR UPDATE');
            $productStmt->execute([$productId]);
            $product = $productStmt->fetch();
            if (!$product) {
                throw new AppException('Khong tim thay san pham cua anh.');
            }
            if ($isBase === 1 && $isActive === 1) {
                $pdo->prepare('UPDATE product_images SET is_base = 0 WHERE product_id = ?')->execute([$productId]);
            }
            $insert = $pdo->prepare(
                'INSERT INTO product_images (product_id, image_path, image_alt, is_base, is_active, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $productId,
                $imagePath,
                $imageAlt ?: (string)$product['product_name'],
                ($isActive ? $isBase : 0),
                $isActive,
                $sortOrder,
            ]);
            $pdo->commit();
            return;
        }

        $stmt = $pdo->prepare('SELECT image_id, product_id, is_base FROM product_images WHERE image_id = ? FOR UPDATE');
        $stmt->execute([$imageId]);
        $image = $stmt->fetch();
        if (!$image) {
            throw new AppException('Khong tim thay anh san pham.');
        }
        if ($isBase === 1 && $isActive === 1) {
            $reset = $pdo->prepare('UPDATE product_images SET is_base = 0 WHERE product_id = ?');
            $reset->execute([$image['product_id']]);
        }
        $update = $pdo->prepare(
            'UPDATE product_images
             SET image_path = COALESCE(NULLIF(?, \'\'), image_path),
                 image_alt = ?, sort_order = ?, is_active = ?, is_base = ?
             WHERE image_id = ?'
        );
        $update->execute([$imagePath, $imageAlt ?: null, $sortOrder, $isActive, ($isActive ? $isBase : 0), $imageId]);
        $baseCount = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_active = 1 AND is_base = 1');
        $baseCount->execute([$image['product_id']]);
        if ((int)$baseCount->fetchColumn() === 0) {
            $fallback = $pdo->prepare(
                'SELECT image_id FROM product_images
                 WHERE product_id = ? AND is_active = 1
                 ORDER BY sort_order, image_id
                 LIMIT 1'
            );
            $fallback->execute([$image['product_id']]);
            $fallbackId = $fallback->fetchColumn();
            if ($fallbackId) {
                $pdo->prepare('UPDATE product_images SET is_base = 1 WHERE image_id = ?')->execute([$fallbackId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_set_base_image(PDO $pdo, string $productId, int $imageId): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc sua anh san pham.');
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT image_id FROM product_images WHERE image_id = ? AND product_id = ? AND is_active = 1 FOR UPDATE');
        $stmt->execute([$imageId, $productId]);
        if (!$stmt->fetch()) {
            throw new AppException('Anh base khong hop le.');
        }
        $pdo->prepare('UPDATE product_images SET is_base = 0 WHERE product_id = ?')->execute([$productId]);
        $pdo->prepare('UPDATE product_images SET is_base = 1 WHERE image_id = ?')->execute([$imageId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_delete_or_inactivate_product_image(PDO $pdo, int $imageId): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc sua anh san pham.');
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT image_id, product_id, is_base FROM product_images WHERE image_id = ? FOR UPDATE');
        $stmt->execute([$imageId]);
        $image = $stmt->fetch();
        if (!$image) {
            throw new AppException('Khong tim thay anh san pham.');
        }
        $pdo->prepare('UPDATE product_images SET is_active = 0, is_base = 0 WHERE image_id = ?')->execute([$imageId]);
        if ((int)$image['is_base'] === 1) {
            $fallback = $pdo->prepare(
                'SELECT image_id FROM product_images
                 WHERE product_id = ? AND is_active = 1
                 ORDER BY sort_order, image_id
                 LIMIT 1'
            );
            $fallback->execute([$image['product_id']]);
            $fallbackId = $fallback->fetchColumn();
            if ($fallbackId) {
                $pdo->prepare('UPDATE product_images SET is_base = 1 WHERE image_id = ?')->execute([$fallbackId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_fetch_dashboard(PDO $pdo): array
{
    $statusRows = $pdo->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status')->fetchAll();
    $statusCounts = ['new' => 0, 'confirmed' => 0, 'ordered' => 0, 'received' => 0, 'ready' => 0, 'done' => 0, 'cancelled' => 0];
    foreach ($statusRows as $row) {
        $statusCounts[(string)$row['status']] = (int)$row['total'];
    }
    $doneRevenue = (int)$pdo->query("SELECT COALESCE(SUM(total_vnd), 0) FROM orders WHERE status = 'done'")->fetchColumn();
    $inventorySummary = $pdo->query(
        'SELECT * FROM v_inventory_summary ORDER BY qty_base_available ASC, product_name'
    )->fetchAll();
    $expiring = $pdo->query(
        "SELECT l.*, p.product_name, p.base_uom_label
         FROM inventory_lots l
         JOIN products p ON p.product_id = l.product_id
         WHERE (l.qty_base_on_hand - l.qty_base_reserved) > 0
           AND l.expiry_date IS NOT NULL
           AND l.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
         ORDER BY l.expiry_date ASC, l.lot_id ASC
         LIMIT 10"
    )->fetchAll();
    $attentionOrders = $pdo->query(
        "SELECT order_id, created_at, customer_name, customer_phone, status, total_vnd
         FROM orders
         WHERE status NOT IN ('done','cancelled')
         ORDER BY created_at DESC
         LIMIT 8"
    )->fetchAll();
    $lowStock = $pdo->query(
        'SELECT *
         FROM v_inventory_summary
         WHERE qty_base_available <= 3
         ORDER BY qty_base_available ASC, product_name
         LIMIT 12'
    )->fetchAll();

    return [
        'status_counts' => $statusCounts,
        'done_revenue' => $doneRevenue,
        'inventory_summary' => $inventorySummary,
        'expiring_lots' => $expiring,
        'low_stock' => $lowStock,
        'attention_orders' => $attentionOrders,
    ];
}

function admin_fetch_orders(PDO $pdo, array $filters): array
{
    $where = [];
    $params = [];
    $status = trim((string)($filters['status'] ?? ''));
    $q = trim((string)($filters['q'] ?? ''));
    $dateFrom = trim((string)($filters['date_from'] ?? ''));
    $dateTo = trim((string)($filters['date_to'] ?? ''));

    if ($status !== '') {
        $where[] = 'o.status = ?';
        $params[] = $status;
    }
    if ($q !== '') {
        $where[] = '(o.order_id LIKE ? OR o.customer_phone LIKE ? OR o.customer_name LIKE ?)';
        $needle = '%' . $q . '%';
        array_push($params, $needle, $needle, $needle);
    }
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[] = 'DATE(o.created_at) >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[] = 'DATE(o.created_at) <= ?';
        $params[] = $dateTo;
    }

    $sql = "SELECT o.order_id, o.customer_id, o.created_at, o.status AS status,
                   o.customer_name, o.customer_phone, o.customer_address, o.receive_date, o.note,
                   o.shipping_method, o.shipping_zone_id, o.shipping_fee_vnd,
                   o.subtotal_vnd, o.total_vnd, o.source_summary, o.updated_at,
                   COALESCE(plan_counts.total_items, 0) AS total_items,
                   COALESCE(plan_counts.planned_items, 0) AS planned_items
            FROM orders o
            LEFT JOIN (
                SELECT order_id,
                       COUNT(*) AS total_items,
                       SUM(CASE WHEN planned_plan_id IS NOT NULL THEN 1 ELSE 0 END) AS planned_items
                FROM order_items
                GROUP BY order_id
            ) plan_counts ON plan_counts.order_id = o.order_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
           ' ORDER BY o.created_at DESC LIMIT 200';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function admin_fetch_order_detail(PDO $pdo, string $orderId): ?array
{
    if ($orderId === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT order_id, customer_id, created_at, status AS status,
                customer_name, customer_phone, customer_address, receive_date, note,
                shipping_method, shipping_zone_id, shipping_fee_vnd,
                subtotal_vnd, total_vnd, source_summary, updated_at
         FROM orders
         WHERE order_id = ?'
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }
    $items = $pdo->prepare(
        'SELECT order_item_id, order_id, line_no, product_id, product_name_snapshot,
                uom_id, uom_label_snapshot, source_location, qty_uom,
                conversion_to_base_snapshot, qty_base, unit_price_vnd, line_total_vnd,
                allocated_lot_id, planned_plan_id, planned_at, created_at
         FROM order_items
         WHERE order_id = ?
         ORDER BY line_no'
    );
    $items->execute([$orderId]);
    $order['items'] = $items->fetchAll();
    $order['plan_status'] = admin_order_plan_status($pdo, $orderId);
    $allocations = $pdo->prepare(
        'SELECT a.*, l.expiry_date, l.received_date
         FROM order_item_allocations a
         JOIN inventory_lots l ON l.lot_id = a.lot_id
         WHERE a.order_id = ?
         ORDER BY a.order_item_id, a.allocation_id'
    );
    $allocations->execute([$orderId]);
    $order['allocations'] = $allocations->fetchAll();
    return $order;
}

function admin_order_plan_status(PDO $pdo, string $orderId): string
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total_items,
                SUM(CASE WHEN planned_plan_id IS NOT NULL THEN 1 ELSE 0 END) AS planned_items
         FROM order_items
         WHERE order_id = ?'
    );
    $stmt->execute([$orderId]);
    $row = $stmt->fetch() ?: ['total_items' => 0, 'planned_items' => 0];
    return admin_plan_status_from_counts((int)$row['total_items'], (int)$row['planned_items']);
}

function admin_plan_status_from_counts(int $totalItems, int $plannedItems): string
{
    if ($totalItems <= 0 || $plannedItems <= 0) {
        return 'unplanned';
    }
    if ($plannedItems >= $totalItems) {
        return 'planned';
    }
    return 'partial';
}

function admin_order_plan_status_label(?string $status): string
{
    $status = $status ?: 'unplanned';
    return [
        'unplanned' => 'Chưa gộp',
        'partial' => 'Gộp một phần',
        'planned' => 'Đã gộp',
    ][$status] ?? 'Chưa gộp';
}

function admin_fetch_inventory(PDO $pdo): array
{
    $summary = $pdo->query(
        'SELECT * FROM v_inventory_summary ORDER BY product_name'
    )->fetchAll();
    return ['summary' => $summary, 'lots' => [], 'movements' => []];
}

function admin_fetch_inventory_product_detail(PDO $pdo, string $productId): ?array
{
    $productId = trim($productId);
    if ($productId === '') {
        return null;
    }

    $summary = $pdo->prepare('SELECT * FROM v_inventory_summary WHERE product_id = ?');
    $summary->execute([$productId]);
    $product = $summary->fetch();
    if (!$product) {
        return null;
    }

    $lots = $pdo->prepare(
        "SELECT l.*, p.product_name, p.base_uom_label
         FROM inventory_lots l
         JOIN products p ON p.product_id = l.product_id
         WHERE l.product_id = ?
         ORDER BY l.expiry_date IS NULL, l.expiry_date, l.received_date, l.lot_id"
    );
    $lots->execute([$productId]);
    $product['lots'] = $lots->fetchAll();

    $movements = $pdo->prepare(
        "SELECT m.*, p.product_name
         FROM inventory_movements m
         JOIN products p ON p.product_id = m.product_id
         WHERE m.product_id = ?
         ORDER BY m.created_at DESC
         LIMIT 80"
    );
    $movements->execute([$productId]);
    $product['movements'] = $movements->fetchAll();

    return $product;
}

function admin_get_products(PDO $pdo, array $filters = []): array
{
    $where = [];
    $params = [];
    $q = trim((string)($filters['q'] ?? ''));
    $categoryId = trim((string)($filters['category_id'] ?? ''));
    $active = trim((string)($filters['active'] ?? ''));

    if ($q !== '') {
        $where[] = '(p.product_id LIKE ? OR p.product_name LIKE ? OR p.product_slug LIKE ?)';
        $needle = '%' . $q . '%';
        array_push($params, $needle, $needle, $needle);
    }
    if ($categoryId !== '') {
        $where[] = 'p.category_id = ?';
        $params[] = $categoryId;
    }
    if ($active !== '' && in_array($active, ['0', '1'], true)) {
        $where[] = 'p.is_active = ?';
        $params[] = (int)$active;
    }

    $sql = "SELECT p.*, c.category_name,
                   img.image_path AS base_image_path,
                   u.uom_label AS default_uom_label,
                   u.unit_price_vnd AS default_price_vnd
            FROM products p
            JOIN categories c ON c.category_id = p.category_id
            LEFT JOIN product_images img
              ON img.product_id = p.product_id AND img.is_base = 1 AND img.is_active = 1
            LEFT JOIN product_uoms u
              ON u.product_id = p.product_id AND u.is_default = 1 AND u.is_active = 1" .
           ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
           ' ORDER BY p.is_active DESC, p.default_source, p.product_name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function admin_get_product_detail(PDO $pdo, string $productId): ?array
{
    if ($productId === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT p.*, c.category_name
         FROM products p
         JOIN categories c ON c.category_id = p.category_id
         WHERE p.product_id = ?'
    );
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        return null;
    }
    $uoms = $pdo->prepare(
        'SELECT u.*,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.uom_id = u.uom_id) AS order_refs,
                (SELECT COUNT(*) FROM inventory_movements im WHERE im.uom_id = u.uom_id) AS movement_refs
         FROM product_uoms u
         WHERE u.product_id = ?
         ORDER BY u.is_default DESC, u.sort_order, u.conversion_to_base'
    );
    $uoms->execute([$productId]);
    $product['uoms'] = $uoms->fetchAll();

    $images = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_active DESC, sort_order, image_id');
    $images->execute([$productId]);
    $product['images'] = $images->fetchAll();

    $summary = $pdo->prepare('SELECT * FROM v_inventory_summary WHERE product_id = ?');
    $summary->execute([$productId]);
    $product['inventory_summary'] = $summary->fetch();
    $product['refs'] = admin_product_reference_counts($pdo, $productId);
    $lots = $pdo->prepare(
        'SELECT * FROM inventory_lots
         WHERE product_id = ?
         ORDER BY expiry_date IS NULL, expiry_date, received_date, lot_id
         LIMIT 30'
    );
    $lots->execute([$productId]);
    $product['lots'] = $lots->fetchAll();

    return $product;
}

function admin_fetch_products(PDO $pdo): array
{
    $products = admin_get_products($pdo);

    $uoms = $pdo->query(
        'SELECT u.*, p.product_name,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.uom_id = u.uom_id) AS order_refs,
                (SELECT COUNT(*) FROM inventory_movements im WHERE im.uom_id = u.uom_id) AS movement_refs
         FROM product_uoms u
         JOIN products p ON p.product_id = u.product_id
         ORDER BY p.product_name, u.is_default DESC, u.sort_order, u.conversion_to_base'
    )->fetchAll();

    $images = $pdo->query(
        'SELECT pi.*, p.product_name
         FROM product_images pi
         JOIN products p ON p.product_id = pi.product_id
         ORDER BY p.product_name, pi.is_active DESC, pi.sort_order, pi.image_id'
    )->fetchAll();
    $categories = $pdo->query(
        'SELECT * FROM categories ORDER BY sort_order, category_name'
    )->fetchAll();

    return ['products' => $products, 'uoms' => $uoms, 'images' => $images, 'categories' => $categories];
}

function admin_date_or_today(string $value): string
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : date('Y-m-d');
}

function admin_valid_source_filter(string $source): string
{
    return in_array($source, ['Binh Dinh', 'Gia Lai', 'Unknown'], true) ? $source : '';
}

function admin_scope_from_sources(array $sources): string
{
    $sources = array_values(array_unique(array_filter($sources, fn($s) => in_array($s, ['Binh Dinh', 'Gia Lai', 'Unknown'], true))));
    if (!$sources) {
        return 'Unknown';
    }
    return count($sources) === 1 ? $sources[0] : 'Mixed';
}

function admin_has_purchase_plan_orders(PDO $pdo): bool
{
    static $hasTable = null;
    if ($hasTable !== null) {
        return $hasTable;
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'purchase_plan_orders'"
    );
    $stmt->execute();
    $hasTable = (int)$stmt->fetchColumn() > 0;
    return $hasTable;
}

function admin_order_ids_for_plan(PDO $pdo, string $planId): array
{
    if ($planId === '') {
        return [];
    }
    if (admin_has_purchase_plan_orders($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT order_id FROM purchase_plan_orders WHERE plan_id = ? ORDER BY created_at, order_id'
        );
        $stmt->execute([$planId]);
        return array_map('strval', array_column($stmt->fetchAll(), 'order_id'));
    }

    $stmt = $pdo->prepare(
        'SELECT DISTINCT order_id FROM order_items WHERE planned_plan_id = ? ORDER BY order_id'
    );
    $stmt->execute([$planId]);
    return array_map('strval', array_column($stmt->fetchAll(), 'order_id'));
}

function admin_get_order_date_overview(PDO $pdo): array
{
    return $pdo->query(
        "SELECT DATE(o.created_at) AS order_date,
                COUNT(DISTINCT o.order_id) AS orders_count,
                COUNT(oi.order_item_id) AS items_count,
                SUM(CASE WHEN oi.planned_plan_id IS NULL THEN 1 ELSE 0 END) AS unplanned_items
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.order_id
         WHERE o.status IN ('new','confirmed')
           AND o.status NOT IN ('done','cancelled')
         GROUP BY DATE(o.created_at)
         HAVING unplanned_items > 0
         ORDER BY order_date DESC
         LIMIT 30"
    )->fetchAll();
}

function admin_get_unplanned_summary_range(PDO $pdo, array $filters): array
{
    $from = admin_date_or_today((string)($filters['from_date'] ?? date('Y-m-d')));
    $to = admin_date_or_today((string)($filters['to_date'] ?? $from));
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    $source = admin_valid_source_filter((string)($filters['source'] ?? ''));
    $statuses = $filters['statuses'] ?? ['new', 'confirmed'];
    if (!is_array($statuses)) {
        $statuses = [$statuses];
    }
    $statuses = array_values(array_intersect($statuses, ['new', 'confirmed']));
    if (!$statuses) {
        $statuses = ['new', 'confirmed'];
    }

    $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
    $params = array_merge($statuses, [$from . ' 00:00:00', $to . ' 23:59:59']);
    $sourceSql = '';
    if ($source !== '') {
        $sourceSql = ' AND oi.source_location = ?';
        $params[] = $source;
    }

    $summarySql = "SELECT oi.source_location, oi.product_id, oi.product_name_snapshot,
                          oi.uom_id, oi.uom_label_snapshot,
                          SUM(oi.qty_uom) AS qty_needed_uom,
                          SUM(oi.qty_base) AS qty_needed_base,
                          COUNT(DISTINCT oi.order_id) AS orders_count,
                          COUNT(*) AS item_lines
                   FROM orders o
                   JOIN order_items oi ON oi.order_id = o.order_id
                   WHERE o.status IN ($statusPlaceholders)
                     AND o.status NOT IN ('done','cancelled')
                     AND o.created_at BETWEEN ? AND ?
                     AND oi.planned_plan_id IS NULL" . $sourceSql . "
                   GROUP BY oi.source_location, oi.product_id, oi.product_name_snapshot, oi.uom_id, oi.uom_label_snapshot
                   ORDER BY oi.source_location, oi.product_name_snapshot, oi.uom_label_snapshot";
    $stmt = $pdo->prepare($summarySql);
    $stmt->execute($params);
    $summary = $stmt->fetchAll();

    $orderParams = array_merge($statuses, [$from . ' 00:00:00', $to . ' 23:59:59']);
    $orderSourceSql = '';
    if ($source !== '') {
        $orderSourceSql = ' AND EXISTS (SELECT 1 FROM order_items x WHERE x.order_id = o.order_id AND x.source_location = ?)';
        $orderParams[] = $source;
    }
    $ordersSql = "SELECT o.order_id, o.created_at, o.customer_name, o.customer_phone, o.status,
                         COUNT(oi.order_item_id) AS item_count,
                         SUM(CASE WHEN oi.planned_plan_id IS NULL THEN 1 ELSE 0 END) AS unplanned_count,
                         SUM(CASE WHEN oi.planned_plan_id IS NOT NULL THEN 1 ELSE 0 END) AS planned_count
                  FROM orders o
                  JOIN order_items oi ON oi.order_id = o.order_id
                  WHERE o.status IN ($statusPlaceholders)
                    AND o.created_at BETWEEN ? AND ?" . $orderSourceSql . "
                  GROUP BY o.order_id
                  ORDER BY o.created_at DESC";
    $ordersStmt = $pdo->prepare($ordersSql);
    $ordersStmt->execute($orderParams);

    return [
        'from_date' => $from,
        'to_date' => $to,
        'source' => $source,
        'statuses' => $statuses,
        'summary' => $summary,
        'orders' => $ordersStmt->fetchAll(),
    ];
}

function admin_normalize_order_ids(array $orderIds): array
{
    $clean = [];
    foreach ($orderIds as $orderId) {
        $orderId = trim((string)$orderId);
        if ($orderId !== '' && preg_match('/^[A-Za-z0-9_-]{3,40}$/', $orderId)) {
            $clean[$orderId] = $orderId;
        }
    }
    return array_values($clean);
}

function admin_fetch_orders_for_grouping(PDO $pdo, array $filters): array
{
    $where = ["o.status NOT IN ('done','cancelled')"];
    $params = [];
    $statuses = $filters['statuses'] ?? ['new', 'confirmed'];
    if (!is_array($statuses)) {
        $statuses = [$statuses];
    }
    $statuses = array_values(array_intersect(array_map('strval', $statuses), ['new','confirmed','ordered','received']));
    if ($statuses) {
        $where[] = 'o.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
        array_push($params, ...$statuses);
    }

    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(o.order_id LIKE ? OR o.customer_phone LIKE ? OR o.customer_name LIKE ?)';
        $needle = '%' . $q . '%';
        array_push($params, $needle, $needle, $needle);
    }
    $dateFrom = trim((string)($filters['date_from'] ?? ''));
    $dateTo = trim((string)($filters['date_to'] ?? ''));
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[] = 'DATE(o.created_at) >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[] = 'DATE(o.created_at) <= ?';
        $params[] = $dateTo;
    }

    $planFilter = trim((string)($filters['plan_filter'] ?? ''));
    if ($planFilter === 'unplanned') {
        $where[] = 'COALESCE(plan_counts.planned_items, 0) = 0';
    } elseif ($planFilter === 'planned') {
        $where[] = 'COALESCE(plan_counts.total_items, 0) > 0 AND COALESCE(plan_counts.planned_items, 0) >= COALESCE(plan_counts.total_items, 0)';
    } elseif ($planFilter === 'partial') {
        $where[] = 'COALESCE(plan_counts.planned_items, 0) > 0 AND COALESCE(plan_counts.planned_items, 0) < COALESCE(plan_counts.total_items, 0)';
    }

    $sql = "SELECT o.order_id, o.created_at, o.status AS status, o.customer_name, o.customer_phone,
                   o.total_vnd, o.source_summary,
                   COALESCE(plan_counts.total_items, 0) AS total_items,
                   COALESCE(plan_counts.planned_items, 0) AS planned_items,
                   COALESCE(plan_counts.unplanned_items, 0) AS unplanned_items
            FROM orders o
            LEFT JOIN (
                SELECT order_id,
                       COUNT(*) AS total_items,
                       SUM(CASE WHEN planned_plan_id IS NOT NULL THEN 1 ELSE 0 END) AS planned_items,
                       SUM(CASE WHEN planned_plan_id IS NULL THEN 1 ELSE 0 END) AS unplanned_items
                FROM order_items
                GROUP BY order_id
            ) plan_counts ON plan_counts.order_id = o.order_id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY o.created_at DESC
            LIMIT 200';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['plan_status'] = admin_plan_status_from_counts((int)$row['total_items'], (int)$row['planned_items']);
    }
    unset($row);
    return $rows;
}

function admin_get_selected_orders_summary(PDO $pdo, array $orderIds): array
{
    $orderIds = admin_normalize_order_ids($orderIds);
    if (!$orderIds) {
        return ['orders' => [], 'summary' => []];
    }
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $ordersStmt = $pdo->prepare(
        "SELECT o.order_id, o.created_at, o.status AS status, o.customer_name, o.customer_phone,
                o.total_vnd,
                COUNT(oi.order_item_id) AS total_items,
                SUM(CASE WHEN oi.planned_plan_id IS NOT NULL THEN 1 ELSE 0 END) AS planned_items,
                SUM(CASE WHEN oi.planned_plan_id IS NULL THEN 1 ELSE 0 END) AS unplanned_items
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.order_id
         WHERE o.order_id IN ($placeholders)
           AND o.status NOT IN ('done','cancelled')
         GROUP BY o.order_id, o.created_at, o.status, o.customer_name, o.customer_phone, o.total_vnd
         ORDER BY o.created_at DESC"
    );
    $ordersStmt->execute($orderIds);
    $orders = $ordersStmt->fetchAll();
    foreach ($orders as &$order) {
        $order['plan_status'] = admin_plan_status_from_counts((int)$order['total_items'], (int)$order['planned_items']);
    }
    unset($order);

    $summaryStmt = $pdo->prepare(
        "SELECT oi.source_location, oi.product_id, oi.product_name_snapshot,
                oi.uom_id, oi.uom_label_snapshot,
                SUM(oi.qty_uom) AS qty_needed_uom,
                SUM(oi.qty_base) AS qty_needed_base,
                COUNT(DISTINCT oi.order_id) AS orders_count,
                COUNT(*) AS item_lines
         FROM order_items oi
         JOIN orders o ON o.order_id = oi.order_id
         WHERE oi.order_id IN ($placeholders)
           AND o.status NOT IN ('done','cancelled')
           AND oi.planned_plan_id IS NULL
         GROUP BY oi.source_location, oi.product_id, oi.product_name_snapshot, oi.uom_id, oi.uom_label_snapshot
         ORDER BY oi.source_location, oi.product_name_snapshot, oi.uom_label_snapshot"
    );
    $summaryStmt->execute($orderIds);
    return ['orders' => $orders, 'summary' => $summaryStmt->fetchAll()];
}

function admin_create_purchase_plan_from_selected_orders(PDO $pdo, array $orderIds, array $options = []): string
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc tao PO.');
    }
    $orderIds = admin_normalize_order_ids($orderIds);
    if (!$orderIds) {
        throw new AppException('Vui long chon it nhat mot don hang de tao PO.');
    }
    $note = trim((string)($options['note'] ?? ''));

    $pdo->beginTransaction();
    try {
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $sql = "SELECT oi.order_item_id, oi.order_id, oi.product_id, oi.product_name_snapshot,
                       oi.uom_id, oi.uom_label_snapshot, oi.source_location, oi.qty_uom,
                       oi.qty_base, oi.conversion_to_base_snapshot, u.cost_price_vnd,
                       DATE(o.created_at) AS order_date
                FROM orders o
                JOIN order_items oi ON oi.order_id = o.order_id
                JOIN product_uoms u ON u.uom_id = oi.uom_id
                WHERE o.order_id IN ($placeholders)
                  AND o.status NOT IN ('done','cancelled')
                  AND oi.planned_plan_id IS NULL
                ORDER BY oi.source_location, oi.product_id, oi.uom_id
                FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($orderIds);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            throw new AppException('Cac don da chon khong con item chua gop PO.');
        }

        $planId = unique_id($pdo, 'plan');
        $scope = admin_scope_from_sources(array_column($rows, 'source_location'));
        $dates = array_column($rows, 'order_date');
        $from = min($dates);
        $to = max($dates);
        $adminId = admin_current_user()['admin_id'] ?? null;
        $insertPlan = $pdo->prepare(
            'INSERT INTO purchase_plans (plan_id, order_from_date, order_to_date, supplier_scope, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insertPlan->execute([$planId, $from, $to, $scope, $note ?: null, $adminId]);

        $groups = [];
        foreach ($rows as $row) {
            $key = $row['product_id'] . '|' . $row['uom_id'] . '|' . $row['source_location'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'product_id' => $row['product_id'],
                    'product_name_snapshot' => $row['product_name_snapshot'],
                    'uom_id' => $row['uom_id'],
                    'uom_label_snapshot' => $row['uom_label_snapshot'],
                    'source_location' => $row['source_location'],
                    'qty_needed_uom' => 0.0,
                    'qty_needed_base' => 0.0,
                    'cost_per_uom_vnd' => (int)$row['cost_price_vnd'],
                ];
            }
            $groups[$key]['qty_needed_uom'] += (float)$row['qty_uom'];
            $groups[$key]['qty_needed_base'] += (float)$row['qty_base'];
        }

        $insertItem = $pdo->prepare(
            'INSERT INTO plan_items
              (plan_id, product_id, product_name_snapshot, uom_id, uom_label_snapshot, source_location,
               qty_needed_uom, qty_planned_uom, conversion_to_base_snapshot,
               qty_needed_base, qty_planned_base, cost_per_uom_vnd)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($groups as $group) {
            $qtyUom = round($group['qty_needed_uom'], 3);
            $qtyBase = round($group['qty_needed_base'], 3);
            $conversion = $qtyUom > 0 ? round($qtyBase / $qtyUom, 3) : 1;
            $insertItem->execute([
                $planId,
                $group['product_id'],
                $group['product_name_snapshot'],
                $group['uom_id'],
                $group['uom_label_snapshot'],
                $group['source_location'],
                $qtyUom,
                $qtyUom,
                $conversion,
                $qtyBase,
                $qtyBase,
                $group['cost_per_uom_vnd'],
            ]);
        }

        $stamp = $pdo->prepare(
            'UPDATE order_items
             SET planned_plan_id = ?, planned_at = NOW()
             WHERE order_item_id = ? AND planned_plan_id IS NULL'
        );
        $plannedOrderIds = [];
        foreach ($rows as $row) {
            $stamp->execute([$planId, $row['order_item_id']]);
            if ($stamp->rowCount() !== 1) {
                throw new AppException('Order item da duoc len PO boi phien khac. Da rollback de tranh PO trung.');
            }
            $plannedOrderIds[$row['order_id']] = $row['order_id'];
        }

        if (admin_has_purchase_plan_orders($pdo)) {
            $insertOrder = $pdo->prepare(
                'INSERT IGNORE INTO purchase_plan_orders (plan_id, order_id) VALUES (?, ?)'
            );
            foreach ($plannedOrderIds as $orderId) {
                $insertOrder->execute([$planId, $orderId]);
            }
        }

        $pdo->commit();
        return $planId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_create_purchase_plan_from_orders_range(PDO $pdo, array $input): string
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc tao PO.');
    }
    $from = admin_date_or_today((string)($input['from_date'] ?? date('Y-m-d')));
    $to = admin_date_or_today((string)($input['to_date'] ?? $from));
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    $source = admin_valid_source_filter((string)($input['source'] ?? ''));
    $statuses = $input['statuses'] ?? ['new', 'confirmed'];
    if (!is_array($statuses)) {
        $statuses = [$statuses];
    }
    $statuses = array_values(array_intersect($statuses, ['new', 'confirmed']));
    if (!$statuses) {
        $statuses = ['new', 'confirmed'];
    }
    $note = trim((string)($input['note'] ?? ''));

    $pdo->beginTransaction();
    try {
        $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = array_merge($statuses, [$from . ' 00:00:00', $to . ' 23:59:59']);
        $sourceSql = '';
        if ($source !== '') {
            $sourceSql = ' AND oi.source_location = ?';
            $params[] = $source;
        }
        $sql = "SELECT oi.order_item_id, oi.order_id, oi.product_id, oi.product_name_snapshot,
                       oi.uom_id, oi.uom_label_snapshot, oi.source_location, oi.qty_uom,
                       oi.qty_base, oi.conversion_to_base_snapshot, u.cost_price_vnd
                FROM orders o
                JOIN order_items oi ON oi.order_id = o.order_id
                JOIN product_uoms u ON u.uom_id = oi.uom_id
                WHERE o.status IN ($statusPlaceholders)
                  AND o.status NOT IN ('done','cancelled')
                  AND o.created_at BETWEEN ? AND ?
                  AND oi.planned_plan_id IS NULL" . $sourceSql . "
                ORDER BY oi.source_location, oi.product_id, oi.uom_id
                FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            throw new AppException('Khong co order item chua len PO trong khoang ngay da chon.');
        }

        $planId = unique_id($pdo, 'plan');
        $scope = admin_scope_from_sources(array_column($rows, 'source_location'));
        $adminId = admin_current_user()['admin_id'] ?? null;
        $insertPlan = $pdo->prepare(
            'INSERT INTO purchase_plans (plan_id, order_from_date, order_to_date, supplier_scope, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insertPlan->execute([$planId, $from, $to, $scope, $note ?: null, $adminId]);

        $groups = [];
        foreach ($rows as $row) {
            $key = $row['product_id'] . '|' . $row['uom_id'] . '|' . $row['source_location'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'product_id' => $row['product_id'],
                    'product_name_snapshot' => $row['product_name_snapshot'],
                    'uom_id' => $row['uom_id'],
                    'uom_label_snapshot' => $row['uom_label_snapshot'],
                    'source_location' => $row['source_location'],
                    'qty_needed_uom' => 0.0,
                    'qty_needed_base' => 0.0,
                    'cost_per_uom_vnd' => (int)$row['cost_price_vnd'],
                ];
            }
            $groups[$key]['qty_needed_uom'] += (float)$row['qty_uom'];
            $groups[$key]['qty_needed_base'] += (float)$row['qty_base'];
        }

        $insertItem = $pdo->prepare(
            'INSERT INTO plan_items
              (plan_id, product_id, product_name_snapshot, uom_id, uom_label_snapshot, source_location,
               qty_needed_uom, qty_planned_uom, conversion_to_base_snapshot,
               qty_needed_base, qty_planned_base, cost_per_uom_vnd)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($groups as $group) {
            $qtyUom = round($group['qty_needed_uom'], 3);
            $qtyBase = round($group['qty_needed_base'], 3);
            $conversion = $qtyUom > 0 ? round($qtyBase / $qtyUom, 3) : 1;
            $insertItem->execute([
                $planId,
                $group['product_id'],
                $group['product_name_snapshot'],
                $group['uom_id'],
                $group['uom_label_snapshot'],
                $group['source_location'],
                $qtyUom,
                $qtyUom,
                $conversion,
                $qtyBase,
                $qtyBase,
                $group['cost_per_uom_vnd'],
            ]);
        }

        $stamp = $pdo->prepare(
            'UPDATE order_items
             SET planned_plan_id = ?, planned_at = NOW()
             WHERE order_item_id = ? AND planned_plan_id IS NULL'
        );
        $plannedOrderIds = [];
        foreach ($rows as $row) {
            $stamp->execute([$planId, $row['order_item_id']]);
            if ($stamp->rowCount() !== 1) {
                throw new AppException('Order item da duoc len PO boi phien khac. Da rollback de tranh PO trung.');
            }
            $plannedOrderIds[$row['order_id']] = $row['order_id'];
        }

        if (admin_has_purchase_plan_orders($pdo)) {
            $insertOrder = $pdo->prepare(
                'INSERT IGNORE INTO purchase_plan_orders (plan_id, order_id) VALUES (?, ?)'
            );
            foreach ($plannedOrderIds as $orderId) {
                $insertOrder->execute([$planId, $orderId]);
            }
        }

        $pdo->commit();
        return $planId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_list_purchase_plans(PDO $pdo, array $filters = []): array
{
    $where = [];
    $params = [];
    $rawStatus = $filters['status'] ?? '';
    $status = is_array($rawStatus) ? '' : trim((string)$rawStatus);
    if ($status !== '' || is_array($rawStatus)) {
        $statuses = is_array($rawStatus) ? $rawStatus : [$status];
        $statuses = array_values(array_intersect(array_map('strval', $statuses), ['draft','ordered','partial_received','received','closed','cancelled']));
        if ($statuses) {
            $where[] = 'pp.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
            array_push($params, ...$statuses);
        }
    }
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = 'pp.plan_id LIKE ?';
        $params[] = '%' . $q . '%';
    }
    $orderCountSql = admin_has_purchase_plan_orders($pdo)
        ? '(SELECT COUNT(*) FROM purchase_plan_orders ppo WHERE ppo.plan_id = pp.plan_id)'
        : '(SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi WHERE oi.planned_plan_id = pp.plan_id)';
    $sql = "SELECT pp.plan_id, pp.created_at, pp.order_from_date, pp.order_to_date,
                   pp.status AS status, pp.supplier_scope, pp.note, pp.created_by, pp.updated_at,
                   COUNT(pi.plan_item_id) AS items_count,
                   COALESCE(SUM(pi.qty_planned_uom), 0) AS qty_planned_uom_total,
                   COALESCE(SUM(pi.qty_received_uom), 0) AS qty_received_uom_total,
                   COALESCE(SUM(pi.qty_planned_base), 0) AS qty_planned_base_total,
                   COALESCE(SUM(pi.qty_received_base), 0) AS qty_received_base_total,
                   $orderCountSql AS orders_count
            FROM purchase_plans pp
            LEFT JOIN plan_items pi ON pi.plan_id = pp.plan_id" .
           ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
           ' GROUP BY pp.plan_id, pp.created_at, pp.order_from_date, pp.order_to_date,
                     pp.status, pp.supplier_scope, pp.note, pp.created_by, pp.updated_at
             ORDER BY pp.created_at DESC LIMIT 100';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function admin_get_purchase_plan(PDO $pdo, string $planId): ?array
{
    if ($planId === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT plan_id, created_at, order_from_date, order_to_date, status AS status,
                supplier_scope, note, created_by, updated_at
         FROM purchase_plans
         WHERE plan_id = ?'
    );
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    if (!$plan) {
        return null;
    }
    $items = $pdo->prepare(
        'SELECT pi.*, p.shelf_life_value, p.shelf_life_unit
         FROM plan_items pi
         JOIN products p ON p.product_id = pi.product_id
         WHERE pi.plan_id = ?
         ORDER BY pi.source_location, pi.product_name_snapshot, pi.uom_label_snapshot'
    );
    $items->execute([$planId]);
    $plan['items'] = $items->fetchAll();

    $orderIds = admin_order_ids_for_plan($pdo, $planId);
    $plan['orders'] = [];
    $plan['outside_items'] = [];
    $ordersById = [];
    $orderPlannedByPlanItemId = [];
    $receivedAllocatedByPlanItemId = [];
    if ($orderIds) {
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $orders = $pdo->prepare(
            "SELECT o.order_id, o.created_at, o.status AS status, o.customer_name, o.customer_phone,
                    o.total_vnd,
                    COUNT(oi.order_item_id) AS total_items,
                    SUM(CASE WHEN oi.planned_plan_id = ? THEN 1 ELSE 0 END) AS plan_items_count
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.order_id
             WHERE o.order_id IN ($placeholders)
             GROUP BY o.order_id, o.created_at, o.status, o.customer_name, o.customer_phone, o.total_vnd
             ORDER BY o.created_at DESC"
        );
        $orders->execute(array_merge([$planId], $orderIds));
        foreach ($orders->fetchAll() as $order) {
            $order['items'] = [];
            $ordersById[(string)$order['order_id']] = $order;
        }

        $orderItems = $pdo->prepare(
            'SELECT oi.order_id, oi.order_item_id, oi.line_no, oi.product_id,
                    oi.product_name_snapshot, oi.uom_id, oi.uom_label_snapshot,
                    oi.source_location, oi.qty_uom, oi.qty_base,
                    o.created_at AS order_created_at,
                    pi.plan_item_id, pi.qty_received_uom, pi.cost_per_uom_vnd
             FROM order_items oi
             JOIN orders o ON o.order_id = oi.order_id
             LEFT JOIN plan_items pi
               ON pi.plan_id = oi.planned_plan_id
              AND pi.product_id = oi.product_id
              AND pi.uom_id = oi.uom_id
              AND pi.source_location = oi.source_location
             WHERE oi.planned_plan_id = ?
             ORDER BY o.created_at, oi.order_id, oi.line_no, oi.order_item_id'
        );
        $orderItems->execute([$planId]);
        $orderItemRows = $orderItems->fetchAll();
        $receivedPoolByPlanItemId = [];
        foreach ($orderItemRows as $row) {
            $planItemId = (int)($row['plan_item_id'] ?? 0);
            if ($planItemId > 0 && !array_key_exists($planItemId, $receivedPoolByPlanItemId)) {
                $receivedPoolByPlanItemId[$planItemId] = (float)($row['qty_received_uom'] ?? 0);
            }
        }
        foreach ($orderItemRows as $row) {
            $orderId = (string)$row['order_id'];
            if (!isset($ordersById[$orderId])) {
                $ordersById[$orderId] = [
                    'order_id' => $orderId,
                    'created_at' => $row['order_created_at'] ?? '',
                    'status' => 'unknown',
                    'customer_name' => '',
                    'customer_phone' => '',
                    'total_vnd' => 0,
                    'total_items' => 0,
                    'plan_items_count' => 0,
                    'items' => [],
                ];
            }
            $plannedQty = (float)($row['qty_uom'] ?? 0);
            $planItemId = (int)($row['plan_item_id'] ?? 0);
            $receivedQty = 0.0;
            if ($planItemId > 0) {
                $availableReceived = max(0.0, (float)($receivedPoolByPlanItemId[$planItemId] ?? 0));
                $receivedQty = min($plannedQty, $availableReceived);
                $receivedPoolByPlanItemId[$planItemId] = max(0.0, $availableReceived - $receivedQty);
                $orderPlannedByPlanItemId[$planItemId] = ($orderPlannedByPlanItemId[$planItemId] ?? 0.0) + $plannedQty;
                $receivedAllocatedByPlanItemId[$planItemId] = ($receivedAllocatedByPlanItemId[$planItemId] ?? 0.0) + $receivedQty;
            }
            $ordersById[$orderId]['items'][] = [
                'order_item_id' => (int)($row['order_item_id'] ?? 0),
                'line_no' => (int)($row['line_no'] ?? 0),
                'plan_item_id' => $planItemId,
                'product_id' => (string)($row['product_id'] ?? ''),
                'product_name' => (string)($row['product_name_snapshot'] ?? ''),
                'uom_id' => (string)($row['uom_id'] ?? ''),
                'uom_label' => (string)($row['uom_label_snapshot'] ?? ''),
                'source_location' => (string)($row['source_location'] ?? 'Unknown'),
                'qty_planned_uom' => $plannedQty,
                'qty_received_uom' => $receivedQty,
                'remaining_qty' => max(0.0, $plannedQty - $receivedQty),
                'cost_price_vnd' => (int)($row['cost_per_uom_vnd'] ?? 0),
            ];
            $ordersById[$orderId]['plan_items_count'] = count($ordersById[$orderId]['items']);
        }
        $plan['orders'] = array_values($ordersById);
    }

    foreach ($plan['items'] as $item) {
        $planItemId = (int)($item['plan_item_id'] ?? 0);
        $plannedInOrders = (float)($orderPlannedByPlanItemId[$planItemId] ?? 0.0);
        $outsidePlanned = max(0.0, (float)($item['qty_planned_uom'] ?? 0) - $plannedInOrders);
        if ($outsidePlanned <= 0.0001) {
            continue;
        }
        $receivedOutside = max(0.0, (float)($item['qty_received_uom'] ?? 0) - (float)($receivedAllocatedByPlanItemId[$planItemId] ?? 0.0));
        $receivedOutside = min($outsidePlanned, $receivedOutside);
        $plan['outside_items'][] = [
            'plan_item_id' => $planItemId,
            'product_id' => (string)($item['product_id'] ?? ''),
            'product_name' => (string)($item['product_name_snapshot'] ?? ''),
            'uom_id' => (string)($item['uom_id'] ?? ''),
            'uom_label' => (string)($item['uom_label_snapshot'] ?? ''),
            'source_location' => (string)($item['source_location'] ?? 'Unknown'),
            'qty_planned_uom' => $outsidePlanned,
            'qty_received_uom' => $receivedOutside,
            'remaining_qty' => max(0.0, $outsidePlanned - $receivedOutside),
            'cost_price_vnd' => (int)($item['cost_per_uom_vnd'] ?? 0),
        ];
    }

    $receipts = $pdo->prepare('SELECT * FROM purchase_plan_receipts WHERE plan_id = ? ORDER BY received_at DESC');
    $receipts->execute([$planId]);
    $plan['receipts'] = $receipts->fetchAll();

    $receiptItems = $pdo->prepare(
        'SELECT pri.*, l.expiry_date, l.received_date, l.supplier_name, p.product_name
         FROM purchase_plan_receipt_items pri
         JOIN inventory_lots l ON l.lot_id = pri.lot_id
         JOIN products p ON p.product_id = pri.product_id
         WHERE pri.plan_id = ?
         ORDER BY pri.created_at DESC, pri.receipt_item_id DESC'
    );
    $receiptItems->execute([$planId]);
    $plan['receipt_items'] = $receiptItems->fetchAll();
    return $plan;
}

function admin_get_purchase_plan_detail(PDO $pdo, string $planId): ?array
{
    return admin_get_purchase_plan($pdo, $planId);
}

function admin_save_purchase_plan_items(PDO $pdo, string $planId, array $input): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc sua PO.');
    }
    $qtyInputs = $input['qty_planned_uom'] ?? [];
    $costInputs = $input['cost_per_uom_vnd'] ?? [];
    if (!is_array($qtyInputs)) {
        throw new AppException('Du lieu dong PO khong hop le.');
    }
    $pdo->beginTransaction();
    try {
        $planStmt = $pdo->prepare('SELECT plan_id, status FROM purchase_plans WHERE plan_id = ? FOR UPDATE');
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch();
        if (!$plan) {
            throw new AppException('Khong tim thay PO.');
        }
        if (in_array($plan['status'], ['received','closed','cancelled'], true)) {
            throw new AppException('PO da khoa/da nhan xong/huy, khong the sua so luong.');
        }
        $itemStmt = $pdo->prepare('SELECT * FROM plan_items WHERE plan_item_id = ? AND plan_id = ? FOR UPDATE');
        $update = $pdo->prepare(
            'UPDATE plan_items
             SET qty_planned_uom = ?, qty_planned_base = ?, cost_per_uom_vnd = ?
             WHERE plan_item_id = ?'
        );
        foreach ($qtyInputs as $itemId => $qtyRaw) {
            $itemId = (int)$itemId;
            $qty = (float)$qtyRaw;
            $itemStmt->execute([$itemId, $planId]);
            $item = $itemStmt->fetch();
            if (!$item || $qty <= 0) {
                throw new AppException('So luong PO khong hop le.');
            }
            if ($qty + 0.0001 < (float)$item['qty_received_uom']) {
                throw new AppException('So luong planned khong duoc nho hon so da nhan.');
            }
            $plannedBase = round($qty * (float)$item['conversion_to_base_snapshot'], 3);
            $cost = max(0, (int)($costInputs[$itemId] ?? $item['cost_per_uom_vnd']));
            $update->execute([$qty, $plannedBase, $cost, $itemId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_update_purchase_plan_status(PDO $pdo, string $planId, string $newStatus): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc doi trang thai PO.');
    }
    $valid = ['draft','ordered','partial_received','received','closed','cancelled'];
    if (!in_array($newStatus, $valid, true)) {
        throw new AppException('Trang thai PO khong hop le.');
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT plan_id, created_at, order_from_date, order_to_date, status AS status,
                    supplier_scope, note, created_by, updated_at
             FROM purchase_plans
             WHERE plan_id = ?
             FOR UPDATE'
        );
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        if (!$plan) {
            throw new AppException('Khong tim thay PO.');
        }
        $from = $plan['status'];
        $allowed = [
            'draft' => ['ordered', 'cancelled'],
            'ordered' => ['closed', 'cancelled'],
            'partial_received' => ['closed'],
            'received' => ['closed'],
        ];
        if (!in_array($newStatus, $allowed[$from] ?? [], true)) {
            throw new AppException('Khong the chuyen PO tu ' . $from . ' sang ' . $newStatus . '.');
        }
        if ($newStatus === 'cancelled') {
            $received = $pdo->prepare('SELECT COALESCE(SUM(qty_received_base),0) FROM plan_items WHERE plan_id = ?');
            $received->execute([$planId]);
            if ((float)$received->fetchColumn() > 0) {
                throw new AppException('PO da co hang nhan, khong duoc huy don gian.');
            }
            $pdo->prepare('UPDATE order_items SET planned_plan_id = NULL, planned_at = NULL WHERE planned_plan_id = ?')->execute([$planId]);
        }
        $pdo->prepare('UPDATE purchase_plans SET status = ? WHERE plan_id = ?')->execute([$newStatus, $planId]);
        if ($newStatus === 'ordered') {
            admin_mark_orders_ordered_for_plan($pdo, $planId);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_cancel_purchase_plan(PDO $pdo, string $planId): void
{
    admin_update_purchase_plan_status($pdo, $planId, 'cancelled');
}

function admin_update_purchase_plan_item(PDO $pdo, int $planItemId, array $payload): void
{
    admin_save_purchase_plan_items($pdo, (string)($payload['plan_id'] ?? ''), [
        'qty_planned_uom' => [$planItemId => $payload['qty_planned_uom'] ?? 0],
        'cost_per_uom_vnd' => [$planItemId => $payload['cost_per_uom_vnd'] ?? 0],
    ]);
}

function admin_add_purchase_plan_item(PDO $pdo, string $planId, array $payload): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc sua PO.');
    }
    $productId = trim((string)($payload['product_id'] ?? ''));
    $uomId = trim((string)($payload['uom_id'] ?? ''));
    $source = trim((string)($payload['source_location'] ?? 'Unknown'));
    $qtyUom = (float)($payload['qty_planned_uom'] ?? 0);
    $cost = max(0, (int)($payload['cost_per_uom_vnd'] ?? 0));
    $note = trim((string)($payload['note'] ?? ''));
    if ($planId === '' || $productId === '' || $uomId === '' || $qtyUom <= 0) {
        throw new AppException('Thong tin dong PO bo sung khong hop le.');
    }
    if (!in_array($source, ['Binh Dinh','Gia Lai','Unknown'], true)) {
        throw new AppException('Nguon hang cua dong PO khong hop le.');
    }

    $pdo->beginTransaction();
    try {
        $planStmt = $pdo->prepare('SELECT plan_id, status AS status FROM purchase_plans WHERE plan_id = ? FOR UPDATE');
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch();
        if (!$plan) {
            throw new AppException('Khong tim thay PO.');
        }
        if (in_array((string)$plan['status'], ['received','closed','cancelled'], true)) {
            throw new AppException('PO da khoa/da nhan xong/huy, khong the them dong.');
        }

        $lookup = $pdo->prepare(
            'SELECT p.product_name, p.default_source, u.uom_label, u.conversion_to_base, u.cost_price_vnd
             FROM products p
             JOIN product_uoms u ON u.product_id = p.product_id
             WHERE p.product_id = ? AND u.uom_id = ? AND p.is_active = 1 AND u.is_active = 1 AND u.is_purchasable = 1'
        );
        $lookup->execute([$productId, $uomId]);
        $row = $lookup->fetch();
        if (!$row) {
            throw new AppException('San pham/UOM them vao PO khong hop le.');
        }
        if ($source === 'Unknown') {
            $source = $row['default_source'] ?: 'Unknown';
        }
        $conversion = (float)$row['conversion_to_base'];
        $qtyBase = round($qtyUom * $conversion, 3);
        $cost = $cost > 0 ? $cost : (int)$row['cost_price_vnd'];

        $stmt = $pdo->prepare(
            'INSERT INTO plan_items
              (plan_id, product_id, product_name_snapshot, uom_id, uom_label_snapshot, source_location,
               qty_needed_uom, qty_planned_uom, qty_received_uom, conversion_to_base_snapshot,
               qty_needed_base, qty_planned_base, qty_received_base, cost_per_uom_vnd, note)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, 0, ?, 0, ?, 0, ?, ?)
             ON DUPLICATE KEY UPDATE
               qty_planned_uom = qty_planned_uom + VALUES(qty_planned_uom),
               qty_planned_base = qty_planned_base + VALUES(qty_planned_base),
               cost_per_uom_vnd = VALUES(cost_per_uom_vnd),
               note = VALUES(note)'
        );
        $stmt->execute([
            $planId,
            $productId,
            $row['product_name'],
            $uomId,
            $row['uom_label'],
            $source,
            $qtyUom,
            $conversion,
            $qtyBase,
            $cost,
            $note ?: '[Bổ sung ngoài đơn đã gộp]',
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_delete_purchase_plan_item(PDO $pdo, int $planItemId): void
{
    if (!admin_can_manage_config()) {
        throw new AppException('Tai khoan staff khong duoc sua PO.');
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM plan_items WHERE plan_item_id = ? FOR UPDATE');
        $stmt->execute([$planItemId]);
        $item = $stmt->fetch();
        if (!$item) {
            throw new AppException('Khong tim thay dong PO.');
        }
        $planStmt = $pdo->prepare('SELECT plan_id, status AS status FROM purchase_plans WHERE plan_id = ? FOR UPDATE');
        $planStmt->execute([$item['plan_id']]);
        $plan = $planStmt->fetch();
        if (!$plan || in_array((string)$plan['status'], ['received','closed','cancelled'], true)) {
            throw new AppException('PO da khoa/da nhan xong/huy, khong the xoa dong.');
        }
        if ((float)$item['qty_received_base'] > 0.0001) {
            throw new AppException('Dong PO da co hang nhan, khong the xoa.');
        }
        $pdo->prepare(
            'UPDATE order_items
             SET planned_plan_id = NULL, planned_at = NULL
             WHERE planned_plan_id = ?
               AND product_id = ?
               AND uom_id = ?
               AND source_location = ?'
        )->execute([$item['plan_id'], $item['product_id'], $item['uom_id'], $item['source_location']]);
        $pdo->prepare('DELETE FROM plan_items WHERE plan_item_id = ?')->execute([$planItemId]);
        if (admin_has_purchase_plan_orders($pdo)) {
            $pdo->prepare(
                'DELETE ppo FROM purchase_plan_orders ppo
                 WHERE ppo.plan_id = ?
                   AND NOT EXISTS (
                     SELECT 1 FROM order_items oi
                     WHERE oi.order_id = ppo.order_id AND oi.planned_plan_id = ppo.plan_id
                   )'
            )->execute([$item['plan_id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_copy_purchase_plan_text(PDO $pdo, string $planId, string $source = ''): string
{
    $plan = admin_get_purchase_plan($pdo, $planId);
    if (!$plan) {
        return '';
    }
    $lines = ['PO ' . $planId . ' - ' . plan_status_label_for_text($plan['status'] ?? 'draft')];
    foreach (($plan['items'] ?? []) as $item) {
        if ($source !== '' && $item['source_location'] !== $source) {
            continue;
        }
        $lines[] = $item['source_location'] . ' | ' . $item['product_name_snapshot'] . ' | '
            . decimal_display($item['qty_planned_uom']) . ' ' . $item['uom_label_snapshot'];
    }
    return implode(PHP_EOL, $lines);
}

function plan_status_label_for_text(?string $status): string
{
    $status = $status ?: 'unknown';
    return [
        'draft' => 'Nháp',
        'ordered' => 'Đã đặt NCC',
        'partial_received' => 'Nhận một phần',
        'received' => 'Đã nhận đủ',
        'closed' => 'Đã khóa',
        'cancelled' => 'Đã hủy',
        'unknown' => 'Không xác định',
    ][$status] ?? $status;
}

function admin_mark_orders_ordered_for_plan(PDO $pdo, string $planId): void
{
    $pdo->prepare(
        "UPDATE orders o
         SET o.status = 'ordered'
         WHERE o.status IN ('new','confirmed')
           AND EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id AND oi.planned_plan_id = ?)
           AND NOT EXISTS (SELECT 1 FROM order_items oi2 WHERE oi2.order_id = o.order_id AND oi2.planned_plan_id IS NULL)"
    )->execute([$planId]);
}

function admin_recompute_purchase_plan_status(PDO $pdo, string $planId, bool $autoClose = false): string
{
    $remaining = $pdo->prepare(
        'SELECT COUNT(*) FROM plan_items
         WHERE plan_id = ? AND qty_received_base + 0.0001 < qty_planned_base'
    );
    $remaining->execute([$planId]);
    $remainingCount = (int)$remaining->fetchColumn();
    $received = $pdo->prepare('SELECT COALESCE(SUM(qty_received_base),0) FROM plan_items WHERE plan_id = ?');
    $received->execute([$planId]);
    $receivedBase = (float)$received->fetchColumn();
    $status = $remainingCount === 0 ? 'received' : ($receivedBase > 0 ? 'partial_received' : 'ordered');
    if ($autoClose && $status === 'received') {
        $status = 'closed';
    }
    $pdo->prepare('UPDATE purchase_plans SET status = ? WHERE plan_id = ?')->execute([$status, $planId]);
    if (in_array($status, ['received', 'closed'], true)) {
        $pdo->prepare(
            "UPDATE orders o
             SET o.status = 'received'
             WHERE o.status = 'ordered'
               AND EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.order_id AND oi.planned_plan_id = ?)"
        )->execute([$planId]);
    }
    return $status;
}

function admin_insert_po_receipt_lot(PDO $pdo, string $receiptId, string $planId, ?array $item, array $row): void
{
    $qtyUom = (float)$row['qty_uom'];
    if ($qtyUom <= 0) {
        throw new AppException('So luong nhan hang phai lon hon 0.');
    }
    $receivedDate = (string)($row['received_date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $receivedDate)) {
        throw new AppException('Ngay nhan hang khong hop le.');
    }
    $expiryDate = trim((string)($row['expiry_date'] ?? ''));
    if ($expiryDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryDate)) {
        throw new AppException('Han su dung khong hop le.');
    }
    if ($expiryDate !== '' && $expiryDate < $receivedDate) {
        throw new AppException('Han su dung khong duoc nho hon ngay nhan.');
    }

    if ($item) {
        $productId = $item['product_id'];
        $uomId = $item['uom_id'];
        $source = $item['source_location'];
        $conversion = (float)$item['conversion_to_base_snapshot'];
        $productName = $item['product_name_snapshot'];
        $shelfLifeValue = (int)$item['shelf_life_value'];
        $shelfLifeUnit = (string)($item['shelf_life_unit'] ?? '');
        $remaining = round((float)$item['qty_planned_uom'] - (float)$item['qty_received_uom'], 3);
        if ($qtyUom - $remaining > 0.0001) {
            throw new AppException('So luong nhan vuot phan con lai cua ' . $productName . '.');
        }
    } else {
        $productId = trim((string)($row['product_id'] ?? ''));
        $uomId = trim((string)($row['uom_id'] ?? ''));
        $source = trim((string)($row['source_location'] ?? 'Unknown'));
        if (!in_array($source, ['Binh Dinh', 'Gia Lai', 'Unknown'], true)) {
            throw new AppException('Nguon hang nhan ngoai PO khong hop le.');
        }
        $lookup = $pdo->prepare(
            "SELECT p.product_name, p.shelf_life_value, p.shelf_life_unit, p.default_source, u.conversion_to_base
             FROM products p
             JOIN product_uoms u ON u.product_id = p.product_id
             WHERE p.product_id = ? AND u.uom_id = ? AND p.is_active = 1 AND u.is_active = 1 AND u.is_purchasable = 1"
        );
        $lookup->execute([$productId, $uomId]);
        $found = $lookup->fetch();
        if (!$found) {
            throw new AppException('Dong nhan ngoai PO co product/UOM khong hop le.');
        }
        $conversion = (float)$found['conversion_to_base'];
        $shelfLifeValue = (int)$found['shelf_life_value'];
        $shelfLifeUnit = (string)($found['shelf_life_unit'] ?? '');
        $productName = $found['product_name'];
        if ($source === 'Unknown') {
            $source = $found['default_source'] ?: 'Unknown';
        }
    }

    if ($shelfLifeValue > 0 && $expiryDate === '') {
        $expiryDate = admin_auto_expiry_date($receivedDate, $shelfLifeValue, $shelfLifeUnit ?? '');
    }
    if ($expiryDate !== '' && $expiryDate < $receivedDate) {
        throw new AppException('Han su dung khong duoc nho hon ngay nhan.');
    }
    $qtyBase = round($qtyUom * $conversion, 3);
    $costPerUom = max(0, (int)($row['cost_per_uom_vnd'] ?? ($item['cost_per_uom_vnd'] ?? 0)));
    $costPerBase = $conversion > 0 ? (int)round($costPerUom / $conversion) : 0;
    $supplier = trim((string)($row['supplier_name'] ?? ''));
    $note = trim((string)($row['note'] ?? ''));

    $lotId = unique_id($pdo, 'lot');
    $insertLot = $pdo->prepare(
        "INSERT INTO inventory_lots
          (lot_id, product_id, source_location, qty_base_on_hand, qty_base_reserved,
           received_date, expiry_date, supplier_name, cost_per_base_unit_vnd,
           received_uom_id, received_qty_uom, conversion_to_base_snapshot, note)
         VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $insertLot->execute([
        $lotId,
        $productId,
        $source,
        $qtyBase,
        $receivedDate,
        $expiryDate ?: null,
        $supplier ?: null,
        $costPerBase,
        $uomId,
        $qtyUom,
        $conversion,
        ($item ? '' : '[EXTRA] ') . ($note ?: 'Nhan hang theo PO ' . $planId),
    ]);

    insert_inventory_movement($pdo, [
        'movement_type' => 'IN',
        'ref_type' => 'PLAN',
        'ref_id' => $planId,
        'lot_id' => $lotId,
        'product_id' => $productId,
        'source_location' => $source,
        'uom_id' => $uomId,
        'qty_uom' => $qtyUom,
        'conversion_to_base_snapshot' => $conversion,
        'qty_base' => $qtyBase,
        'cost_per_base_unit_vnd' => $costPerBase,
        'note' => 'Receipt ' . $receiptId . ': ' . ($note ?: 'Nhan hang theo PO'),
    ]);

    if ($item) {
        $pdo->prepare(
            'UPDATE plan_items
             SET qty_received_uom = qty_received_uom + ?,
                 qty_received_base = qty_received_base + ?
             WHERE plan_item_id = ?'
        )->execute([$qtyUom, $qtyBase, $item['plan_item_id']]);
    }

    $pdo->prepare(
        'INSERT INTO purchase_plan_receipt_items
          (receipt_id, plan_id, plan_item_id, lot_id, product_id, uom_id,
           qty_received_uom, conversion_to_base_snapshot, qty_received_base, cost_per_uom_vnd)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $receiptId,
        $planId,
        $item['plan_item_id'] ?? null,
        $lotId,
        $productId,
        $uomId,
        $qtyUom,
        $conversion,
        $qtyBase,
        $costPerUom,
    ]);
}

function admin_receive_purchase_plan(PDO $pdo, string $planId, array $input): string
{
    if (!admin_is_logged_in()) {
        throw new AppException('Vui long dang nhap admin.');
    }
    $autoClose = !empty($input['auto_close']);
    $receiptNote = trim((string)($input['receipt_note'] ?? ''));
    $pdo->beginTransaction();
    try {
        $planStmt = $pdo->prepare(
            'SELECT plan_id, created_at, order_from_date, order_to_date, status AS status,
                    supplier_scope, note, created_by, updated_at
             FROM purchase_plans
             WHERE plan_id = ?
             FOR UPDATE'
        );
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch();
        if (!$plan) {
            throw new AppException('Khong tim thay PO.');
        }
        if (in_array($plan['status'], ['closed', 'cancelled'], true)) {
            throw new AppException('PO closed/cancelled khong duoc nhan hang.');
        }
        $receiptId = unique_id($pdo, 'receipt');
        $adminId = admin_current_user()['admin_id'] ?? null;
        $pdo->prepare(
            'INSERT INTO purchase_plan_receipts (receipt_id, plan_id, received_by, note)
             VALUES (?, ?, ?, ?)'
        )->execute([$receiptId, $planId, $adminId, $receiptNote ?: null]);

        $qtyInputs = $input['receive_qty_uom'] ?? [];
        if (!is_array($qtyInputs)) {
            throw new AppException('Du lieu nhan hang khong hop le.');
        }
        $itemStmt = $pdo->prepare(
            'SELECT pi.*, p.shelf_life_value, p.shelf_life_unit
             FROM plan_items pi
             JOIN products p ON p.product_id = pi.product_id
             WHERE pi.plan_item_id = ? AND pi.plan_id = ?
             FOR UPDATE'
        );
        $receivedAny = false;
        foreach ($qtyInputs as $itemId => $qtyRaw) {
            $qty = (float)$qtyRaw;
            if ($qty <= 0) {
                continue;
            }
            $itemId = (int)$itemId;
            $itemStmt->execute([$itemId, $planId]);
            $item = $itemStmt->fetch();
            if (!$item) {
                throw new AppException('Dong PO nhan hang khong hop le.');
            }
            admin_insert_po_receipt_lot($pdo, $receiptId, $planId, $item, [
                'qty_uom' => $qty,
                'received_date' => $input['received_date'][$itemId] ?? date('Y-m-d'),
                'expiry_date' => $input['expiry_date'][$itemId] ?? '',
                'cost_per_uom_vnd' => $input['cost_per_uom_vnd'][$itemId] ?? $item['cost_per_uom_vnd'],
                'supplier_name' => $input['supplier_name'][$itemId] ?? '',
                'note' => $input['line_note'][$itemId] ?? '',
            ]);
            $receivedAny = true;
        }

        $extraQty = (float)($input['extra_qty_uom'] ?? 0);
        if ($extraQty > 0) {
            admin_insert_po_receipt_lot($pdo, $receiptId, $planId, null, [
                'product_id' => $input['extra_product_id'] ?? '',
                'uom_id' => $input['extra_uom_id'] ?? '',
                'source_location' => $input['extra_source_location'] ?? 'Unknown',
                'qty_uom' => $extraQty,
                'received_date' => $input['extra_received_date'] ?? date('Y-m-d'),
                'expiry_date' => $input['extra_expiry_date'] ?? '',
                'cost_per_uom_vnd' => $input['extra_cost_per_uom_vnd'] ?? 0,
                'supplier_name' => $input['extra_supplier_name'] ?? '',
                'note' => '[EXTRA] ' . trim((string)($input['extra_note'] ?? '')),
            ]);
            $receivedAny = true;
        }

        if (!$receivedAny) {
            throw new AppException('Chua nhap so luong nhan hang nao.');
        }
        $status = admin_recompute_purchase_plan_status($pdo, $planId, $autoClose);
        $pdo->commit();
        return $status;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admin_handle_post(PDO $pdo): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $action = (string)($_POST['action'] ?? '');
    try {
        require_csrf();

        if ($action === 'admin_login') {
            admin_login($pdo, (string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
            flash('success', 'Đăng nhập admin thành công.');
            redirect_to('admin.php');
        }

        if ($action === 'admin_logout') {
            admin_logout();
            flash('success', 'Đã đăng xuất admin.');
            redirect_to('admin.php?login=1');
        }

        admin_require_login();

        switch ($action) {
            case 'order_status':
                admin_change_order_status($pdo, (string)($_POST['order_id'] ?? ''), (string)($_POST['new_status'] ?? ''));
                flash('success', 'Đã cập nhật trạng thái đơn hàng.');
                safe_admin_redirect('admin.php?tab=orders');
                break;

            case 'receive_stock':
                admin_receive_stock($pdo, $_POST);
                flash('success', 'Đã nhập hàng và ghi movement IN.');
                safe_admin_redirect('admin.php?tab=inventory');
                break;

            case 'adjust_lot':
                admin_adjust_lot($pdo, $_POST);
                flash('success', 'Đã điều chỉnh tồn kho và ghi movement ADJUST.');
                safe_admin_redirect('admin.php?tab=inventory');
                break;

            case 'void_lot':
                admin_void_lot($pdo, $_POST);
                flash('success', 'Da dong lo an toan bang movement ADJUST ve 0.');
                safe_admin_redirect('admin.php?tab=inventory');
                break;

            case 'update_setting':
                admin_update_setting($pdo, (string)($_POST['setting_key'] ?? ''), (string)($_POST['setting_value'] ?? ''));
                flash('success', 'Đã cập nhật cấu hình.');
                safe_admin_redirect('admin.php?tab=settings');
                break;

            case 'save_shipping_zone':
                admin_save_shipping_zone($pdo, $_POST);
                flash('success', 'Da cap nhat vung giao hang.');
                safe_admin_redirect('admin.php?tab=settings');
                break;

            case 'update_product':
                admin_update_product($pdo, $_POST);
                flash('success', 'Da cap nhat thong tin san pham.');
                redirect_to('admin.php?tab=products&mode=edit&product_id=' . rawurlencode((string)($_POST['product_id'] ?? '')));
                break;

            case 'create_product':
                $productId = admin_create_product($pdo, $_POST);
                flash('success', 'Da tao san pham moi.');
                redirect_to('admin.php?tab=products&mode=edit&product_id=' . rawurlencode($productId));
                break;

            case 'save_uom':
                admin_save_uom($pdo, $_POST);
                flash('success', 'Da luu UOM va dong bo default UOM.');
                redirect_to('admin.php?tab=products&mode=edit&product_id=' . rawurlencode((string)($_POST['product_id'] ?? '')) . '&product_section=uom');
                break;

            case 'deactivate_uom':
                admin_deactivate_uom($pdo, (string)($_POST['uom_id'] ?? ''));
                flash('success', 'Da inactive UOM, khong xoa lich su don/movement.');
                redirect_to('admin.php?tab=products&mode=edit&product_id=' . rawurlencode((string)($_POST['product_id'] ?? '')) . '&product_section=uom');
                break;

            case 'delete_uom':
                admin_delete_uom_if_unused($pdo, (string)($_POST['uom_id'] ?? ''));
                flash('success', 'Da xoa UOM chua phat sinh giao dich.');
                redirect_to('admin.php?tab=products&mode=edit&product_id=' . rawurlencode((string)($_POST['product_id'] ?? '')) . '&product_section=uom');
                break;

            case 'toggle_product':
                admin_toggle_product($pdo, (string)($_POST['product_id'] ?? ''), (int)($_POST['is_active'] ?? 0));
                flash('success', 'Đã cập nhật trạng thái sản phẩm.');
                safe_admin_redirect('admin.php?tab=products');
                break;

            case 'delete_product':
                admin_delete_product($pdo, (string)($_POST['product_id'] ?? ''));
                flash('success', 'Da xoa san pham chua phat sinh giao dich.');
                safe_admin_redirect('admin.php?tab=products');
                break;

            case 'save_product_image':
                admin_save_product_image($pdo, $_POST);
                flash('success', 'Da cap nhat anh san pham.');
                redirect_to('admin.php?tab=products&mode=edit&product_id=' . rawurlencode((string)($_POST['product_id'] ?? '')) . '&product_section=images');
                break;

            case 'set_base_image':
                admin_set_base_image($pdo, (string)($_POST['product_id'] ?? ''), (int)($_POST['image_id'] ?? 0));
                flash('success', 'Da dat anh base moi.');
                redirect_to('admin.php?tab=products&mode=edit&product_id=' . rawurlencode((string)($_POST['product_id'] ?? '')) . '&product_section=images');
                break;

            case 'inactive_product_image':
                admin_delete_or_inactivate_product_image($pdo, (int)($_POST['image_id'] ?? 0));
                flash('success', 'Da inactive anh san pham.');
                redirect_to('admin.php?tab=products&mode=edit&product_id=' . rawurlencode((string)($_POST['product_id'] ?? '')) . '&product_section=images');
                break;

            case 'upload_product_image':
                admin_upload_product_image($pdo, $_POST, $_FILES);
                flash('success', 'Đã upload ảnh sản phẩm.');
                redirect_to('admin.php?tab=products&mode=edit&product_id=' . rawurlencode((string)($_POST['product_id'] ?? '')) . '&product_section=images');
                break;

            case 'create_purchase_plan':
                $selectedOrderIds = $_POST['order_ids'] ?? [];
                $planId = is_array($selectedOrderIds) && $selectedOrderIds
                    ? admin_create_purchase_plan_from_selected_orders($pdo, $selectedOrderIds, $_POST)
                    : admin_create_purchase_plan_from_orders_range($pdo, $_POST);
                flash('success', 'Da tao PO ' . $planId . ' va stamp order_items de tranh gom trung.');
                redirect_to('admin.php?tab=purchase_plan&plan_id=' . rawurlencode($planId));
                break;

            case 'save_purchase_plan_items':
                admin_save_purchase_plan_items($pdo, (string)($_POST['plan_id'] ?? ''), $_POST);
                flash('success', 'Da luu so luong planned cua PO.');
                redirect_to('admin.php?tab=purchase_plan&plan_id=' . rawurlencode((string)($_POST['plan_id'] ?? '')));
                break;

            case 'update_purchase_plan_status':
                admin_update_purchase_plan_status($pdo, (string)($_POST['plan_id'] ?? ''), (string)($_POST['new_status'] ?? ''));
                flash('success', 'Da cap nhat trang thai PO.');
                redirect_to('admin.php?tab=purchase_plan&plan_id=' . rawurlencode((string)($_POST['plan_id'] ?? '')));
                break;

            case 'add_purchase_plan_item':
                admin_add_purchase_plan_item($pdo, (string)($_POST['plan_id'] ?? ''), $_POST);
                flash('success', 'Da them dong san pham vao PO.');
                redirect_to('admin.php?tab=purchase_plan&view_plan_id=' . rawurlencode((string)($_POST['plan_id'] ?? '')));
                break;

            case 'delete_purchase_plan_item':
                admin_delete_purchase_plan_item($pdo, (int)($_POST['plan_item_id'] ?? 0));
                flash('success', 'Da xoa dong PO va clear planned item lien quan neu co.');
                redirect_to('admin.php?tab=purchase_plan&view_plan_id=' . rawurlencode((string)($_POST['plan_id'] ?? '')));
                break;

            case 'receive_purchase_plan':
                $status = admin_receive_purchase_plan($pdo, (string)($_POST['plan_id'] ?? ''), $_POST);
                flash('success', 'Da nhan hang theo PO, cong ton va cap nhat PO: ' . $status . '.');
                redirect_to('admin.php?tab=receive_po&view_plan_id=' . rawurlencode((string)($_POST['plan_id'] ?? '')));
                break;

            default:
                throw new AppException('Action không hợp lệ.');
        }
    } catch (AppException $e) {
        flash('error', $e->getMessage());
        safe_admin_redirect('admin.php');
    } catch (Throwable $e) {
        error_log($e);
        flash('error', APP_ENV === 'dev' ? 'Lỗi hệ thống: ' . $e->getMessage() : 'Có lỗi hệ thống. Vui lòng thử lại.');
        safe_admin_redirect('admin.php');
    }
}
