<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$pdo = db();
admin_handle_post($pdo);

$tab = preg_replace('/[^a-z_]/', '', (string)($_GET['tab'] ?? 'dashboard')) ?: 'dashboard';
$allowedTabs = ['dashboard', 'orders', 'purchase_plan', 'receive_po', 'inventory', 'products', 'settings'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'dashboard';
}

$messages = consume_flash_messages();
$placeholderAdmins = admin_placeholder_count($pdo);

function tab_url(string $tab, array $extra = []): string
{
    return 'admin.php?' . http_build_query(array_merge(['tab' => $tab], $extra));
}

function current_admin_url(array $extra = []): string
{
    $query = array_merge($_GET, $extra);
    foreach ($query as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        }
    }
    $query['tab'] = preg_replace('/[^a-z_]/', '', (string)($query['tab'] ?? 'dashboard')) ?: 'dashboard';
    return 'admin.php?' . http_build_query($query);
}

function modal_close_url(): string
{
    $query = $_GET;
    foreach (['order_id', 'mode', 'product_id', 'product_section', 'view_order_id', 'view_plan_id', 'receive_plan_id', 'plan_id', 'add_stock', 'inventory_product_id', 'inventory_section'] as $key) {
        unset($query[$key]);
    }
    $query['tab'] = preg_replace('/[^a-z_]/', '', (string)($query['tab'] ?? 'dashboard')) ?: 'dashboard';
    return 'admin.php?' . http_build_query($query);
}

function status_badge_class(?string $status): string
{
    $status = $status ?: 'unknown';
    return [
        'new' => 'badge-new',
        'confirmed' => 'badge-confirmed',
        'ready' => 'badge-ready',
        'done' => 'badge-done',
        'cancelled' => 'badge-cancelled',
        'ordered' => 'badge-ordered',
        'received' => 'badge-received',
        'unknown' => 'badge-done',
    ][$status] ?? 'badge-done';
}

function source_badge_class(string $source): string
{
    return $source === 'Gia Lai' ? 'badge-src-gl' : ($source === 'Binh Dinh' ? 'badge-src-bd' : 'badge-src-mixed');
}

function order_plan_badge_class(?string $status): string
{
    return [
        'unplanned' => 'badge-cancelled',
        'partial' => 'badge-ordered',
        'planned' => 'badge-received',
    ][$status ?: 'unplanned'] ?? 'badge-cancelled';
}

function plan_status_label(?string $status): string
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

function next_order_actions(?string $status): array
{
    $status = $status ?: 'unknown';
    return [
        'new' => [
            ['confirmed', 'Xác nhận', 'btn-primary', false],
            ['cancelled', 'Hủy', 'btn-outline danger-text', true],
        ],
        'confirmed' => [
            ['ordered', 'Đã đặt NCC', 'btn-outline', false],
            ['ready', 'Sẵn sàng giao', 'btn-primary', false],
            ['cancelled', 'Hủy', 'btn-outline danger-text', true],
        ],
        'ready' => [
            ['done', 'Hoàn tất & trừ tồn', 'btn-success', true],
            ['cancelled', 'Hủy', 'btn-outline danger-text', true],
        ],
        'ordered' => [
            ['received', 'Đã nhận hàng', 'btn-primary', false],
            ['cancelled', 'Hủy', 'btn-outline danger-text', true],
        ],
        'received' => [
            ['ready', 'Sẵn sàng giao', 'btn-primary', false],
            ['cancelled', 'Hủy', 'btn-outline danger-text', true],
        ],
    ][$status] ?? [];
}

function render_order_action_form(string $orderId, string $nextStatus, string $label, string $class, bool $confirm): string
{
    $confirmAttr = $confirm ? ' onsubmit="return confirm(\'Xác nhận thao tác này?\')"' : '';
    return '<form method="post" class="inline-form"' . $confirmAttr . '>'
        . csrf_field()
        . '<input type="hidden" name="action" value="order_status">'
        . '<input type="hidden" name="return_tab" value="orders">'
        . '<input type="hidden" name="order_id" value="' . h($orderId) . '">'
        . '<input type="hidden" name="new_status" value="' . h($nextStatus) . '">'
        . '<button class="btn btn-xs ' . h($class) . '" type="submit">' . h($label) . '</button>'
        . '</form>';
}

if (!admin_is_logged_in()):
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Đặc Sản Nhà Dân</title>
<style>
:root{--primary:#E8651A;--primary-dark:#C4520F;--primary-light:#FFF3EC;--success:#10B981;--success-light:#D1FAE5;--success-dark:#047857;--warning:#F59E0B;--warning-light:#FEF3C7;--warning-dark:#B45309;--danger:#EF4444;--danger-light:#FEE2E2;--danger-dark:#B91C1C;--info:#3B82F6;--info-light:#DBEAFE;--info-dark:#1D4ED8;--gray-50:#F9FAFB;--gray-100:#F3F4F6;--gray-200:#E5E7EB;--gray-300:#D1D5DB;--gray-500:#6B7280;--gray-600:#4B5563;--gray-700:#374151;--gray-800:#1F2937;--gray-900:#111827;--white:#fff;--shadow-sm:0 1px 2px rgba(0,0,0,.05);--shadow:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -1px rgba(0,0,0,.06);--radius:6px;--radius-lg:10px}
*{box-sizing:border-box;margin:0;padding:0}body{min-height:100vh;display:grid;place-items:center;background:var(--gray-50);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:var(--gray-800)}.login-card{width:min(440px,calc(100vw - 32px));background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);box-shadow:var(--shadow);padding:28px}.brand{font-size:1.35rem;font-weight:800;color:var(--gray-900);margin-bottom:6px}.brand span{color:var(--primary)}.hint{color:var(--gray-500);margin-bottom:20px;line-height:1.5}.form-group{display:grid;gap:6px;margin-bottom:14px}.form-group label{font-weight:700;font-size:.86rem}.form-input{min-height:42px;border:1px solid var(--gray-300);border-radius:var(--radius);padding:0 12px;font:inherit}.btn{min-height:42px;width:100%;border:0;border-radius:var(--radius);background:var(--primary);color:#fff;font-weight:800;cursor:pointer}.alert{padding:12px 14px;border-radius:var(--radius);margin-bottom:14px;line-height:1.45}.alert-error{background:var(--danger-light);color:var(--danger-dark)}.alert-success{background:var(--success-light);color:var(--success-dark)}.alert-warning{background:var(--warning-light);color:var(--warning-dark)}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;align-items:end}.form-grid.compact{grid-template-columns:repeat(auto-fit,minmax(170px,1fr))}.form-grid .span-2{grid-column:span 2}.form-row{display:flex;gap:12px;align-items:end;flex-wrap:wrap}.field-group,.form-group{display:flex;flex-direction:column;gap:6px;margin:0;min-width:0}.field-label,.form-group label{font-size:.84rem;font-weight:800;color:var(--gray-700);line-height:1.2}.field-control,.form-input,.form-select,textarea{width:100%;min-height:44px;border:1px solid var(--gray-300);border-radius:var(--radius);padding:9px 12px;font:inherit;font-size:.92rem;background:#fff;color:var(--gray-800);outline:none;transition:border-color .15s ease,box-shadow .15s ease}.field-control:focus,.form-input:focus,.form-select:focus,textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(232,101,26,.14)}textarea{height:auto;min-height:96px;resize:vertical}.btn{min-height:44px;height:44px;border-radius:var(--radius);font-size:.9rem}.btn-xs{min-height:30px;height:30px}.btn-danger{background:var(--danger);color:#fff}.filter-bar,.filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;align-items:end}.filter-bar .btn,.filter-grid .btn{align-self:end}.filter-extra{grid-column:1/-1;border-top:1px solid var(--gray-200);padding-top:12px;margin-top:2px}.checkbox-group{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center}.checkbox-pill{display:inline-flex;align-items:center;gap:7px;min-height:34px;padding:0 10px;border:1px solid var(--gray-200);border-radius:var(--radius-full);background:#fff;color:var(--gray-700);font-weight:700}.actions-row{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}.w-sm{max-width:110px}.w-md{max-width:180px}.w-lg{max-width:320px}.cell-input{min-width:110px}.summary-strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px}.summary-item{border:1px solid var(--gray-200);border-radius:var(--radius);background:var(--gray-50);padding:10px 12px}.summary-item span{display:block;color:var(--gray-500);font-size:.75rem;font-weight:800;text-transform:uppercase}.summary-item strong{display:block;margin-top:3px;color:var(--gray-900)}.lot-edit-row[hidden],.receive-extra-panel[hidden],[hidden]{display:none!important}.toast-stack{position:fixed;top:74px;right:24px;z-index:80;display:grid;gap:12px;width:min(420px,calc(100vw - 32px));pointer-events:none}.toast{pointer-events:auto;background:#fff;border:1px solid var(--gray-200);border-left:4px solid var(--info);border-radius:var(--radius-lg);box-shadow:0 18px 40px rgba(15,23,42,.18);padding:14px;display:grid;gap:10px;animation:toastIn .18s ease-out}.toast-success{border-left-color:var(--success)}.toast-error{border-left-color:var(--danger)}.toast-warning{border-left-color:var(--warning)}.toast-title{font-weight:850;color:var(--gray-900)}.toast-message{color:var(--gray-600)}.toast-actions{display:flex;justify-content:flex-end;gap:8px}.toast-close{border:0;background:transparent;color:var(--gray-500);font-weight:900;font-size:1rem;line-height:1}.toast.is-hiding{opacity:0;transform:translateY(-8px);transition:all .18s ease}@keyframes toastIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}@media(max-width:760px){.form-grid,.filter-bar,.filter-grid{grid-template-columns:1fr}.form-grid .span-2{grid-column:auto}.toolbar{align-items:stretch}.toolbar .btn{width:100%}.toast-stack{top:12px;right:12px;left:12px;width:auto}.modal-backdrop{padding:10px;align-items:flex-start}.admin-modal,.admin-modal.modal-wide{width:calc(100vw - 20px);max-height:90vh}}
</style>
</head>
<body>
<main class="login-card">
  <div class="brand">Đặc Sản Nhà Dân <span>Admin</span></div>
  <p class="hint">Đăng nhập bằng tài khoản trong bảng <span class="mono">admin_users</span>. Trang quản trị dùng PDO, CSRF và password_verify.</p>
  <?php foreach ($messages as $message): ?>
    <div class="alert alert-<?= h($message['type']) ?>"><?= h($message['message']) ?></div>
  <?php endforeach; ?>
  <?php if ($placeholderAdmins > 0): ?>
    <div class="alert alert-warning">Có admin đang dùng password_hash placeholder. Hãy cập nhật hash thật trước khi đăng nhập.</div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="admin_login">
    <div class="form-group">
      <label for="username">Tài khoản</label>
      <input class="form-input" id="username" name="username" autocomplete="username" required>
    </div>
    <div class="form-group">
      <label for="password">Mật khẩu</label>
      <input class="form-input" id="password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <button class="btn" type="submit">Đăng nhập</button>
  </form>
</main>
</body>
</html>
<?php
exit;
endif;

$admin = admin_current_user();
$dashboard = admin_fetch_dashboard($pdo);
$orderFilters = [
    'status' => $_GET['status'] ?? '',
    'q' => $_GET['q'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
];
$orders = $tab === 'orders' ? admin_fetch_orders($pdo, $orderFilters) : [];
$modalOrderId = '';
if ($tab === 'orders') {
    $modalOrderId = (string)($_GET['order_id'] ?? '');
} elseif ($tab === 'purchase_plan') {
    $modalOrderId = (string)($_GET['view_order_id'] ?? '');
}
$selectedOrder = in_array($tab, ['orders', 'purchase_plan'], true) && $modalOrderId !== ''
    ? admin_fetch_order_detail($pdo, $modalOrderId)
    : null;
$inventory = in_array($tab, ['dashboard', 'inventory'], true) ? admin_fetch_inventory($pdo) : ['summary' => [], 'lots' => [], 'movements' => []];
$productsData = in_array($tab, ['inventory', 'products', 'purchase_plan', 'receive_po'], true) ? admin_fetch_products($pdo) : ['products' => [], 'uoms' => [], 'images' => [], 'categories' => []];
$receiveProductsJson = '[]';
$receiveUomsJson = '[]';
if (in_array($tab, ['receive_po', 'inventory'], true)) {
    $receiveProductsJson = json_encode(array_map(static fn(array $product): array => [
        'product_id' => (string)($product['product_id'] ?? ''),
        'product_name' => (string)($product['product_name'] ?? ''),
        'default_source' => (string)($product['default_source'] ?? 'Unknown'),
        'shelf_life_value' => (int)($product['shelf_life_value'] ?? 0),
        'shelf_life_unit' => (string)($product['shelf_life_unit'] ?? ''),
        'is_active' => (int)($product['is_active'] ?? 0),
    ], $productsData['products']), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '[]';
    $receiveUomsJson = json_encode(array_map(static fn(array $uom): array => [
        'uom_id' => (string)($uom['uom_id'] ?? ''),
        'product_id' => (string)($uom['product_id'] ?? ''),
        'uom_label' => (string)($uom['uom_label'] ?? ''),
        'conversion_to_base' => (float)($uom['conversion_to_base'] ?? 1),
        'cost_price_vnd' => (int)($uom['cost_price_vnd'] ?? 0),
        'is_default' => (int)($uom['is_default'] ?? 0),
        'is_active' => (int)($uom['is_active'] ?? 0),
        'is_purchasable' => (int)($uom['is_purchasable'] ?? 0),
    ], $productsData['uoms']), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '[]';
}
$productFilters = [
    'q' => $_GET['product_q'] ?? '',
    'category_id' => $_GET['category_id'] ?? '',
    'active' => $_GET['active'] ?? '',
];
$productList = $tab === 'products' ? admin_get_products($pdo, $productFilters) : [];
$productMode = in_array((string)($_GET['mode'] ?? ''), ['create', 'edit'], true) ? (string)($_GET['mode'] ?? '') : '';
$selectedProductId = (string)($_GET['product_id'] ?? '');
$selectedProduct = $tab === 'products' ? admin_get_product_detail($pdo, $selectedProductId) : null;
$productSection = in_array((string)($_GET['product_section'] ?? 'basic'), ['basic','uom','images','inventory'], true) ? (string)($_GET['product_section'] ?? 'basic') : 'basic';
$legacyPoFilters = [
    'from_date' => $_GET['from_date'] ?? date('Y-m-d'),
    'to_date' => $_GET['to_date'] ?? date('Y-m-d'),
    'source' => $_GET['source'] ?? '',
    'statuses' => $_GET['statuses'] ?? ['new', 'confirmed'],
];
$groupFilters = [
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'q' => $_GET['q'] ?? '',
    'statuses' => $_GET['group_statuses'] ?? ['new', 'confirmed'],
    'plan_filter' => $_GET['plan_filter'] ?? '',
];
$groupOrders = $tab === 'purchase_plan' ? admin_fetch_orders_for_grouping($pdo, $groupFilters) : [];
$selectedOrderIds = $tab === 'purchase_plan' ? admin_normalize_order_ids((array)($_GET['order_ids'] ?? [])) : [];
$selectedOrdersSummary = $tab === 'purchase_plan' ? admin_get_selected_orders_summary($pdo, $selectedOrderIds) : ['orders' => [], 'summary' => []];
$planListFilters = [
    'status' => $_GET['plan_status'] ?? ($tab === 'receive_po' ? ['draft', 'ordered', 'partial_received'] : ''),
    'q' => $_GET['plan_q'] ?? '',
];
$plans = in_array($tab, ['purchase_plan', 'receive_po'], true) ? admin_list_purchase_plans($pdo, $planListFilters) : [];
$selectedPlanId = '';
if ($tab === 'purchase_plan') {
    $selectedPlanId = (string)($_GET['view_plan_id'] ?? '');
} elseif ($tab === 'receive_po') {
    $selectedPlanId = (string)($_GET['view_plan_id'] ?? ($_GET['receive_plan_id'] ?? ''));
}
$selectedPlan = in_array($tab, ['purchase_plan', 'receive_po'], true) && $selectedPlanId !== ''
    ? admin_get_purchase_plan_detail($pdo, $selectedPlanId)
    : null;
if (!$selectedPlan) {
    $selectedPlan = ['plan_id' => '', 'status' => 'unknown', 'items' => [], 'orders' => [], 'outside_items' => [], 'receipts' => [], 'receipt_items' => []];
}
$inventoryProductId = $tab === 'inventory' ? trim((string)($_GET['inventory_product_id'] ?? '')) : '';
$inventorySection = in_array((string)($_GET['inventory_section'] ?? 'lots'), ['lots', 'history'], true) ? (string)($_GET['inventory_section'] ?? 'lots') : 'lots';
$selectedInventoryProduct = $tab === 'inventory' && $inventoryProductId !== ''
    ? admin_fetch_inventory_product_detail($pdo, $inventoryProductId)
    : null;
$settings = get_settings_map($pdo);
$zones = $pdo->query('SELECT * FROM shipping_zones ORDER BY is_default DESC, zone_name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Đặc Sản Nhà Dân</title>
<style>
:root{--primary:#E8651A;--primary-dark:#C4520F;--primary-light:#FFF3EC;--success:#10B981;--success-light:#D1FAE5;--success-dark:#047857;--warning:#F59E0B;--warning-light:#FEF3C7;--warning-dark:#B45309;--danger:#EF4444;--danger-light:#FEE2E2;--danger-dark:#B91C1C;--info:#3B82F6;--info-light:#DBEAFE;--info-dark:#1D4ED8;--gray-50:#F9FAFB;--gray-100:#F3F4F6;--gray-200:#E5E7EB;--gray-300:#D1D5DB;--gray-400:#9CA3AF;--gray-500:#6B7280;--gray-600:#4B5563;--gray-700:#374151;--gray-800:#1F2937;--gray-900:#111827;--white:#FFFFFF;--shadow-sm:0 1px 2px rgba(0,0,0,.05);--shadow:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -1px rgba(0,0,0,.06);--radius:6px;--radius-lg:10px;--radius-full:9999px}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}html{font-size:14px}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:var(--gray-800);background:var(--gray-50);line-height:1.5;min-height:100vh}a{color:var(--primary);text-decoration:none}button{font:inherit;cursor:pointer}.app-header{height:60px;background:var(--white);display:flex;align-items:center;justify-content:space-between;padding:0 24px;border-bottom:1px solid var(--gray-200);position:sticky;top:0;z-index:10}.app-header h1{font-size:1.25rem;font-weight:800;color:var(--gray-900)}.app-header h1 span{color:var(--primary)}.header-right{display:flex;align-items:center;gap:12px;color:var(--gray-500)}.env-badge{background:var(--success-light);color:var(--success-dark);padding:4px 8px;border-radius:var(--radius);font-weight:800;font-size:.75rem}.tab-bar{background:var(--white);border-bottom:1px solid var(--gray-200);display:flex;gap:0;padding:0 24px;overflow-x:auto}.tab-btn{display:inline-flex;align-items:center;min-height:48px;padding:0 18px;border-bottom:2px solid transparent;color:var(--gray-500);font-weight:700;white-space:nowrap}.tab-btn.active{color:var(--primary);border-bottom-color:var(--primary)}.main-content{padding:24px;max-width:1600px;width:100%;margin:0 auto}.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px}.kpi-card,.card{background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm)}.kpi-card{padding:20px}.kpi-label{font-size:.78rem;color:var(--gray-500);text-transform:uppercase;font-weight:800;letter-spacing:.04em;margin-bottom:8px}.kpi-value{font-size:1.8rem;font-weight:850;color:var(--gray-900)}.kpi-card.highlight{border-left:4px solid var(--primary)}.kpi-card.danger{border-left:4px solid var(--danger)}.card{margin-bottom:24px;overflow:hidden}.card-header{padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;background:var(--gray-50);gap:12px}.card-title{font-weight:800;color:var(--gray-900)}.card-body{padding:20px}.grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}.grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.table-wrap{overflow-x:auto}table.data-table{width:100%;border-collapse:collapse;font-size:.86rem;text-align:left}table.data-table th{background:var(--gray-50);color:var(--gray-600);font-weight:800;padding:12px 14px;border-bottom:1px solid var(--gray-200);white-space:nowrap}table.data-table td{padding:12px 14px;border-bottom:1px solid var(--gray-100);vertical-align:top}.text-right{text-align:right}.text-center{text-align:center}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:.82rem;color:var(--gray-600)}.badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:var(--radius-full);font-size:.75rem;font-weight:800;white-space:nowrap}.badge-new{background:var(--info-light);color:var(--info-dark)}.badge-confirmed{background:#EDE9FE;color:#6D28D9}.badge-ready{background:var(--success);color:#fff}.badge-done{background:var(--gray-200);color:var(--gray-700)}.badge-cancelled{background:var(--danger-light);color:var(--danger-dark)}.badge-ordered{background:var(--warning-light);color:var(--warning-dark)}.badge-received{background:var(--success-light);color:var(--success-dark)}.badge-src-bd{background:#E0F2FE;color:#1E40AF}.badge-src-gl{background:#FEF3C7;color:#92400E}.badge-src-mixed{background:var(--gray-200);color:var(--gray-700)}.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:36px;padding:0 14px;border-radius:var(--radius);font-weight:750;border:1px solid transparent;background:var(--gray-100);color:var(--gray-800);white-space:nowrap}.btn-primary{background:var(--primary);color:#fff}.btn-success{background:var(--success);color:#fff}.btn-outline{background:#fff;border-color:var(--gray-300);color:var(--gray-700)}.btn-xs{min-height:28px;padding:0 9px;font-size:.76rem}.danger-text{color:var(--danger-dark)}.inline-form{display:inline-flex;margin:2px}.action-group{display:flex;gap:6px;flex-wrap:wrap}.filter-bar{display:flex;flex-wrap:wrap;gap:12px;align-items:end;background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-lg);padding:16px;margin-bottom:20px}.form-group{display:grid;gap:6px;margin-bottom:14px}.form-group label{font-weight:800;font-size:.84rem;color:var(--gray-700)}.form-input,.form-select,textarea{width:100%;min-height:38px;border:1px solid var(--gray-300);border-radius:var(--radius);padding:8px 10px;font:inherit;background:#fff}textarea{min-height:76px;resize:vertical}.alert{padding:12px 14px;border-radius:var(--radius);margin-bottom:14px;font-weight:650}.alert-error{background:var(--danger-light);color:var(--danger-dark)}.alert-success{background:var(--success-light);color:var(--success-dark)}.alert-warning{background:var(--warning-light);color:var(--warning-dark)}.empty-state{padding:30px;text-align:center;color:var(--gray-500)}.thumb{width:58px;height:58px;object-fit:cover;border-radius:var(--radius);border:1px solid var(--gray-200)}.thumb-lg{width:84px;height:84px}.muted{color:var(--gray-500);font-size:.86rem}.stack{display:grid;gap:8px}.subtabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}.subtab{border:1px solid var(--gray-300);border-radius:var(--radius);padding:7px 12px;font-weight:800;color:var(--gray-600);background:#fff}.subtab.active{border-color:var(--primary);color:var(--primary);background:var(--primary-light)}.copy-box{width:100%;min-height:150px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}.modal-backdrop{position:fixed;inset:0;background:rgba(17,24,39,.58);z-index:50;display:flex;align-items:center;justify-content:center;padding:24px}.admin-modal{width:min(1120px,calc(100vw - 48px));max-height:90vh;background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-lg);box-shadow:0 24px 70px rgba(15,23,42,.28);display:flex;flex-direction:column;overflow:hidden}.admin-modal.modal-wide{width:min(1320px,calc(100vw - 48px))}.modal-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;border-bottom:1px solid var(--gray-200);background:var(--gray-50)}.modal-title{font-weight:850;color:var(--gray-900)}.modal-close{width:34px;height:34px;border:1px solid var(--gray-300);border-radius:var(--radius);display:inline-flex;align-items:center;justify-content:center;background:#fff;color:var(--gray-600);font-weight:900;font-size:1.2rem}.modal-body{padding:20px;overflow:auto}.modal-footer{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;padding:14px 20px;border-top:1px solid var(--gray-200);background:var(--gray-50)}.modal-body .card{box-shadow:none}.modal-body .filter-bar{margin-bottom:12px}.receive-extra-panel[hidden]{display:none}.btn-disabled{opacity:.55;pointer-events:none}@media(max-width:980px){.grid-2,.grid-3{grid-template-columns:1fr}.main-content{padding:16px}.app-header{padding:0 14px}.tab-bar{padding:0 14px}.modal-backdrop{padding:10px;align-items:flex-start}.admin-modal,.admin-modal.modal-wide{width:calc(100vw - 20px);max-height:90vh}.modal-body{padding:14px}.modal-body .grid-2,.modal-body .grid-3{grid-template-columns:1fr}}
</style>
</head>
<body>
<header class="app-header">
  <h1>Đặc Sản Nhà Dân <span>Admin</span></h1>
  <div class="header-right">
    <span class="env-badge">MYSQL LIVE MODE</span>
    <span><?= h($admin['full_name'] ?? '') ?> · <?= h($admin['role'] ?? '') ?></span>
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="admin_logout">
      <button class="btn btn-outline btn-xs" type="submit">Đăng xuất</button>
    </form>
  </div>
</header>

<nav class="tab-bar">
  <?php foreach (['dashboard' => 'Tổng quan', 'orders' => 'Đơn hàng', 'purchase_plan' => 'Gộp đơn / Tạo PO', 'receive_po' => 'Nhận hàng theo PO', 'inventory' => 'Tồn kho', 'products' => 'Sản phẩm', 'settings' => 'Cấu hình'] as $id => $label): ?>
    <a class="tab-btn <?= $tab === $id ? 'active' : '' ?>" href="<?= h(tab_url($id)) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>

<main class="main-content">
  <?php if ($messages): ?>
    <div class="toast-stack" role="status" aria-live="polite">
      <?php foreach ($messages as $message): $toastType = in_array((string)($message['type'] ?? 'info'), ['success','error','warning','info'], true) ? (string)$message['type'] : 'info'; $toastTitle = ['success' => 'Thành công', 'error' => 'Không thể xử lý', 'warning' => 'Cần kiểm tra lại', 'info' => 'Thông báo'][$toastType]; ?>
        <div class="toast toast-<?= h($toastType) ?>" data-toast>
          <div style="display:flex;justify-content:space-between;gap:12px;align-items:start"><div class="toast-title"><?= h($toastTitle) ?></div><button class="toast-close" type="button" data-toast-close aria-label="Đóng">×</button></div>
          <div class="toast-message"><?= h((string)($message['message'] ?? '')) ?></div>
          <div class="toast-actions"><button class="btn btn-xs btn-outline" type="button" data-toast-close>Đã xem</button></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($placeholderAdmins > 0): ?>
    <div class="alert alert-warning">Có admin đang dùng password_hash placeholder. Tài khoản đó sẽ không đăng nhập được cho tới khi cập nhật hash thật.</div>
  <?php endif; ?>

  <?php if ($tab === 'dashboard'): ?>
    <section>
      <div class="kpi-grid">
        <div class="kpi-card highlight"><div class="kpi-label">Doanh thu đơn done</div><div class="kpi-value"><?= h(money_vnd($dashboard['done_revenue'])) ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Đơn mới</div><div class="kpi-value"><?= (int)$dashboard['status_counts']['new'] ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Đã xác nhận</div><div class="kpi-value"><?= (int)$dashboard['status_counts']['confirmed'] ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Sẵn sàng giao</div><div class="kpi-value"><?= (int)$dashboard['status_counts']['ready'] ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Hoàn tất</div><div class="kpi-value"><?= (int)$dashboard['status_counts']['done'] ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Đã hủy</div><div class="kpi-value"><?= (int)$dashboard['status_counts']['cancelled'] ?></div></div>
        <div class="kpi-card danger"><div class="kpi-label">Lô sắp hết hạn</div><div class="kpi-value"><?= count($dashboard['expiring_lots']) ?></div></div>
      </div>
      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">Đơn cần xử lý</div></div>
          <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Mã đơn</th><th>Khách</th><th>Tổng</th><th>Trạng thái</th></tr></thead>
            <tbody>
            <?php foreach ($dashboard['attention_orders'] as $order): $orderStatus = (string)($order['status'] ?? 'unknown'); ?>
              <tr>
                <td class="mono"><a href="<?= h(tab_url('orders', ['order_id' => $order['order_id']])) ?>"><?= h($order['order_id']) ?></a></td>
                <td><?= h($order['customer_name']) ?><div class="muted"><?= h($order['customer_phone']) ?></div></td>
                <td><?= h(money_vnd($order['total_vnd'])) ?></td>
                <td><span class="badge <?= h(status_badge_class($orderStatus)) ?>"><?= h(admin_status_label($orderStatus)) ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$dashboard['attention_orders']): ?><tr><td colspan="4" class="empty-state">Không có đơn đang chờ xử lý.</td></tr><?php endif; ?>
            </tbody>
          </table></div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">Cảnh báo hạn sử dụng</div></div>
          <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Lô</th><th>Sản phẩm</th><th>HSD</th><th class="text-right">Khả dụng</th></tr></thead>
            <tbody>
            <?php foreach ($dashboard['expiring_lots'] as $lot): ?>
              <tr><td class="mono"><?= h($lot['lot_id']) ?></td><td><?= h($lot['product_name']) ?></td><td><?= h($lot['expiry_date']) ?></td><td class="text-right"><?= h(decimal_display((float)$lot['qty_base_on_hand'] - (float)$lot['qty_base_reserved'])) ?> <?= h($lot['base_uom_label']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$dashboard['expiring_lots']): ?><tr><td colspan="4" class="empty-state">Chưa có lô nào cần cảnh báo trong 7 ngày.</td></tr><?php endif; ?>
            </tbody>
          </table></div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">Cảnh báo hết tồn / tồn thấp</div></div>
          <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Sản phẩm</th><th>Base UOM</th><th class="text-right">Available</th><th>HSD gần nhất</th></tr></thead>
            <tbody>
            <?php foreach ($dashboard['low_stock'] as $row): ?>
              <tr><td><?= h($row['product_name']) ?></td><td><?= h($row['base_uom_label']) ?></td><td class="text-right"><strong><?= h(decimal_display($row['qty_base_available'])) ?></strong></td><td><?= h($row['nearest_expiry_date'] ?: '--') ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$dashboard['low_stock']): ?><tr><td colspan="4" class="empty-state">Chưa có sản phẩm hết tồn hoặc tồn thấp.</td></tr><?php endif; ?>
            </tbody>
          </table></div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Tổng tồn theo v_inventory_summary</div></div>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Sản phẩm</th><th>Base UOM</th><th class="text-right">On hand</th><th class="text-right">Reserved</th><th class="text-right">Available</th><th>HSD gần nhất</th></tr></thead>
          <tbody>
          <?php foreach ($inventory['summary'] as $row): ?>
            <tr><td><?= h($row['product_name']) ?></td><td><?= h($row['base_uom_label']) ?></td><td class="text-right"><?= h(decimal_display($row['qty_base_on_hand'])) ?></td><td class="text-right"><?= h(decimal_display($row['qty_base_reserved'])) ?></td><td class="text-right"><strong><?= h(decimal_display($row['qty_base_available'])) ?></strong></td><td><?= h($row['nearest_expiry_date'] ?: '--') ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($tab === 'orders'): ?>
    <section>
      <div class="subtabs">
        <?php foreach (['' => 'Tất cả', 'new' => 'Mới', 'confirmed' => 'Đã xác nhận', 'ordered' => 'Đã đặt NCC', 'received' => 'Đã nhận hàng', 'ready' => 'Sẵn sàng giao', 'done' => 'Hoàn tất', 'cancelled' => 'Đã hủy'] as $statusKey => $label): ?>
          <a class="subtab <?= (string)$orderFilters['status'] === $statusKey ? 'active' : '' ?>" href="<?= h(tab_url('orders', array_merge($orderFilters, ['status' => $statusKey]))) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
      </div>
      <form class="filter-bar" method="get">
        <input type="hidden" name="tab" value="orders">
        <div class="form-group"><label>Tìm mã đơn, khách, SĐT</label><input class="form-input" name="q" value="<?= h((string)$orderFilters['q']) ?>"></div>
        <div class="form-group"><label>Từ ngày</label><input class="form-input" type="date" name="date_from" value="<?= h((string)$orderFilters['date_from']) ?>"></div>
        <div class="form-group"><label>Đến ngày</label><input class="form-input" type="date" name="date_to" value="<?= h((string)$orderFilters['date_to']) ?>"></div>
        <input type="hidden" name="status" value="<?= h((string)$orderFilters['status']) ?>">
        <button class="btn btn-outline" type="submit">Lọc đơn</button>
      </form>
      <div>
        <div class="card">
          <div class="card-header"><div class="card-title">Danh sách đơn hàng</div></div>
          <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Mã đơn</th><th>Ngày tạo</th><th>Khách hàng</th><th>SĐT</th><th class="text-right">Tổng tiền</th><th>Trạng thái</th><th>Gộp PO</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $order): $orderStatus = (string)($order['status'] ?? 'unknown'); $planStatus = admin_plan_status_from_counts((int)($order['total_items'] ?? 0), (int)($order['planned_items'] ?? 0)); ?>
              <tr>
                <td class="mono"><a href="<?= h(tab_url('orders', array_merge($orderFilters, ['order_id' => $order['order_id']]))) ?>"><?= h($order['order_id']) ?></a></td>
                <td><?= h($order['created_at']) ?></td>
                <td><?= h($order['customer_name']) ?></td>
                <td><?= h($order['customer_phone']) ?></td>
                <td class="text-right"><?= h(money_vnd($order['total_vnd'])) ?></td>
                <td><span class="badge <?= h(status_badge_class($orderStatus)) ?>"><?= h(admin_status_label($orderStatus)) ?></span></td>
                <td><span class="badge <?= h(order_plan_badge_class($planStatus)) ?>"><?= h(admin_order_plan_status_label($planStatus)) ?></span></td>
                <td><div class="action-group">
                  <?php $actions = next_order_actions($orderStatus); foreach ($actions as [$next, $label, $class, $confirm]): ?>
                    <?= render_order_action_form($order['order_id'], $next, $label, $class, $confirm) ?>
                  <?php endforeach; ?>
                  <a class="btn btn-xs btn-outline" href="<?= h(tab_url('orders', array_merge($orderFilters, ['order_id' => $order['order_id']]))) ?>">Xem</a>
                </div></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?><tr><td colspan="8" class="empty-state">Không tìm thấy đơn hàng.</td></tr><?php endif; ?>
            </tbody>
          </table></div>
        </div>
        <?php if ($selectedOrder): ?>
        <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="order-modal-title">
          <div class="admin-modal modal-wide">
            <div class="modal-header">
              <div id="order-modal-title" class="modal-title">Hóa đơn chi tiết</div>
              <a class="modal-close" href="<?= h(modal_close_url()) ?>" aria-label="Đóng">×</a>
            </div>
            <div class="modal-body">
              <?php $selectedOrderStatus = (string)($selectedOrder['status'] ?? 'unknown'); ?>
              <div class="stack">
                <div><strong class="mono"><?= h($selectedOrder['order_id'] ?? '') ?></strong> · <span class="badge <?= h(status_badge_class($selectedOrderStatus)) ?>"><?= h(admin_status_label($selectedOrderStatus)) ?></span> · <span class="badge <?= h(order_plan_badge_class($selectedOrder['plan_status'] ?? 'unplanned')) ?>"><?= h(admin_order_plan_status_label($selectedOrder['plan_status'] ?? 'unplanned')) ?></span></div>
                <div>Ngày đặt: <strong><?= h($selectedOrder['created_at'] ?? '') ?></strong></div>
                <div><?= h($selectedOrder['customer_name'] ?? '') ?> · <?= h($selectedOrder['customer_phone'] ?? '') ?></div>
                <div class="muted"><?= h((string)($selectedOrder['customer_address'] ?? '')) ?></div>
                <?php if (!empty($selectedOrder['note'])): ?><div class="muted"><?= nl2br(h((string)$selectedOrder['note'])) ?></div><?php endif; ?>
              </div>
              <div class="table-wrap" style="margin-top:16px"><table class="data-table">
                <thead><tr><th>STT</th><th>Sản phẩm</th><th>UOM khách mua</th><th class="text-right">qty_uom</th><th class="text-right">conversion</th><th class="text-right">qty_base</th><th class="text-right">Đơn giá</th><th class="text-right">Thành tiền</th><th>PO</th></tr></thead>
                <tbody>
                <?php foreach (($selectedOrder['items'] ?? []) as $item): ?>
                  <tr>
                    <td><?= (int)$item['line_no'] ?></td>
                    <td><?= h($item['product_name_snapshot']) ?><div class="muted"><?= h($item['source_location']) ?></div></td>
                    <td><?= h($item['uom_label_snapshot']) ?></td>
                    <td class="text-right"><?= h(decimal_display($item['qty_uom'])) ?></td>
                    <td class="text-right"><?= h(decimal_display($item['conversion_to_base_snapshot'])) ?></td>
                    <td class="text-right"><strong><?= h(decimal_display($item['qty_base'])) ?></strong></td>
                    <td class="text-right"><?= h(money_vnd($item['unit_price_vnd'])) ?></td>
                    <td class="text-right"><?= h(money_vnd($item['line_total_vnd'])) ?></td>
                    <td class="mono"><?= h($item['planned_plan_id'] ?: '--') ?><?php if (!empty($item['planned_at'])): ?><div class="muted"><?= h($item['planned_at']) ?></div><?php endif; ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table></div>
              <div class="stack" style="align-items:end;margin-top:14px">
                <div>Tạm tính: <strong><?= h(money_vnd($selectedOrder['subtotal_vnd'] ?? 0)) ?></strong></div>
                <div>Phí giao hàng: <strong><?= h(money_vnd($selectedOrder['shipping_fee_vnd'] ?? 0)) ?></strong></div>
                <div>Tổng tiền: <strong><?= h(money_vnd($selectedOrder['total_vnd'] ?? 0)) ?></strong></div>
              </div>
              <?php if (!empty($selectedOrder['allocations'])): ?>
                <div class="table-wrap" style="margin-top:16px"><table class="data-table">
                  <thead><tr><th>Order item</th><th>Lô FEFO</th><th class="text-right">qty_base</th><th>Movement</th><th>Ngày nhập</th><th>HSD</th></tr></thead>
                  <tbody>
                  <?php foreach (($selectedOrder['allocations'] ?? []) as $allocation): ?>
                    <tr><td class="mono"><?= h((string)$allocation['order_item_id']) ?></td><td class="mono"><?= h($allocation['lot_id']) ?></td><td class="text-right"><?= h(decimal_display($allocation['qty_base'])) ?></td><td class="mono"><?= h($allocation['movement_id']) ?></td><td><?= h($allocation['received_date']) ?></td><td><?= h($allocation['expiry_date'] ?: '--') ?></td></tr>
                  <?php endforeach; ?>
                  </tbody>
                </table></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($tab === 'purchase_plan'): ?>
    <section>
      <div class="card">
        <div class="card-header"><div class="card-title">Gộp đơn / Tạo PO</div></div>
        <div class="card-body">
          <form method="get">
            <input type="hidden" name="tab" value="purchase_plan">
            <div class="filter-bar">
              <div class="form-group"><label>Từ ngày</label><input class="form-input" type="date" name="date_from" value="<?= h((string)$groupFilters['date_from']) ?>"></div>
              <div class="form-group"><label>Đến ngày</label><input class="form-input" type="date" name="date_to" value="<?= h((string)$groupFilters['date_to']) ?>"></div>
              <div class="form-group"><label>Tìm mã đơn/SĐT/tên</label><input class="form-input" name="q" value="<?= h((string)$groupFilters['q']) ?>"></div>
              <div class="form-group"><label>Gộp PO</label><select class="form-select" name="plan_filter">
                <option value="">Tất cả</option><option value="unplanned" <?= $groupFilters['plan_filter'] === 'unplanned' ? 'selected' : '' ?>>Chưa gộp</option><option value="partial" <?= $groupFilters['plan_filter'] === 'partial' ? 'selected' : '' ?>>Gộp một phần</option><option value="planned" <?= $groupFilters['plan_filter'] === 'planned' ? 'selected' : '' ?>>Đã gộp</option>
              </select></div>
              <button class="btn btn-outline" type="submit">Lọc đơn</button>
              <div class="filter-extra"><div class="checkbox-group">
                <?php foreach (['new' => 'Mới', 'confirmed' => 'Đã xác nhận', 'ordered' => 'Đã đặt NCC', 'received' => 'Đã nhận hàng'] as $status => $label): ?>
                  <label class="checkbox-pill"><input type="checkbox" name="group_statuses[]" value="<?= h($status) ?>" <?= in_array($status, (array)$groupFilters['statuses'], true) ? 'checked' : '' ?>> <?= h($label) ?></label>
                <?php endforeach; ?>
              </div></div>
            </div>
            <div class="table-wrap"><table class="data-table">
              <thead><tr><th></th><th>Mã đơn</th><th>Ngày tạo</th><th>Khách hàng</th><th>SĐT</th><th class="text-right">Tổng tiền</th><th>Trạng thái</th><th>Gộp PO</th><th class="text-right">Dòng</th><th>Chi tiết</th></tr></thead>
              <tbody>
              <?php foreach ($groupOrders as $order): $orderStatus = (string)($order['status'] ?? 'unknown'); $planStatus = (string)($order['plan_status'] ?? 'unplanned'); $canGroup = (int)($order['unplanned_items'] ?? 0) > 0; ?>
                <tr>
                  <td><input type="checkbox" name="order_ids[]" value="<?= h($order['order_id']) ?>" <?= in_array($order['order_id'], $selectedOrderIds, true) ? 'checked' : '' ?> <?= $canGroup ? '' : 'disabled' ?>></td>
                  <td class="mono"><?= h($order['order_id']) ?></td><td><?= h($order['created_at']) ?></td><td><?= h($order['customer_name']) ?></td><td><?= h($order['customer_phone']) ?></td><td class="text-right"><?= h(money_vnd($order['total_vnd'])) ?></td>
                  <td><span class="badge <?= h(status_badge_class($orderStatus)) ?>"><?= h(admin_status_label($orderStatus)) ?></span></td>
                  <td><span class="badge <?= h(order_plan_badge_class($planStatus)) ?>"><?= h(admin_order_plan_status_label($planStatus)) ?></span></td>
                  <td class="text-right"><?= (int)($order['total_items'] ?? 0) ?></td>
                  <td><a class="btn btn-xs btn-outline" href="<?= h(current_admin_url(['tab' => 'purchase_plan', 'view_order_id' => $order['order_id'], 'view_plan_id' => null])) ?>">Xem</a></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$groupOrders): ?><tr><td colspan="10" class="empty-state">Không tìm thấy đơn phù hợp để gộp PO.</td></tr><?php endif; ?>
              </tbody>
            </table></div>
            <button class="btn btn-primary" type="submit" style="margin-top:12px">Xem tổng hợp đơn đã chọn</button>
          </form>
        </div>
      </div>

      <div>
        <div class="card">
          <div class="card-header"><div class="card-title">Tổng hợp đơn đã chọn</div></div>
          <div class="table-wrap"><table class="data-table"><thead><tr><th>Nguồn</th><th>Sản phẩm</th><th>UOM</th><th class="text-right">qty_uom</th><th class="text-right">qty_base</th><th class="text-right">Số đơn</th></tr></thead><tbody>
          <?php foreach (($selectedOrdersSummary['summary'] ?? []) as $row): ?><tr><td><span class="badge <?= h(source_badge_class($row['source_location'])) ?>"><?= h($row['source_location']) ?></span></td><td><?= h($row['product_name_snapshot']) ?></td><td><?= h($row['uom_label_snapshot']) ?></td><td class="text-right"><?= h(decimal_display($row['qty_needed_uom'])) ?></td><td class="text-right"><?= h(decimal_display($row['qty_needed_base'])) ?></td><td class="text-right"><?= (int)$row['orders_count'] ?></td></tr><?php endforeach; ?>
          <?php if (empty($selectedOrdersSummary['summary'])): ?><tr><td colspan="6" class="empty-state">Chọn đơn còn item chưa gộp để xem tổng hợp.</td></tr><?php endif; ?>
          </tbody></table></div>
          <div class="card-body">
            <form method="post" onsubmit="return confirm('Tạo PO từ các item chưa gộp của đơn đã chọn?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="create_purchase_plan">
              <?php foreach ($selectedOrderIds as $orderId): ?><input type="hidden" name="order_ids[]" value="<?= h($orderId) ?>"><?php endforeach; ?>
              <div class="form-group"><label>Ghi chú PO</label><textarea name="note" placeholder="Ví dụ: gom đơn cuối ngày gửi NCC"></textarea></div>
              <button class="btn btn-primary" type="submit" <?= empty($selectedOrdersSummary['summary']) ? 'disabled' : '' ?>>Tạo PO từ đơn đã chọn</button>
            </form>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">Danh sách PO</div></div>
          <div class="card-body">
            <form class="filter-bar" method="get"><input type="hidden" name="tab" value="purchase_plan"><div class="form-group"><label>Tìm PO</label><input class="form-input" name="plan_q" value="<?= h((string)($_GET['plan_q'] ?? '')) ?>"></div><div class="form-group"><label>Status</label><select class="form-select" name="plan_status"><option value="">Tất cả</option><?php foreach (['draft','ordered','partial_received','received','closed','cancelled'] as $ps): ?><option value="<?= h($ps) ?>" <?= (string)($_GET['plan_status'] ?? '') === $ps ? 'selected' : '' ?>><?= h(plan_status_label($ps)) ?></option><?php endforeach; ?></select></div><button class="btn btn-outline" type="submit">Lọc PO</button></form>
          </div>
          <div class="table-wrap"><table class="data-table"><thead><tr><th>PO</th><th>Ngày tạo</th><th>Status</th><th class="text-right">Đơn</th><th class="text-right">Dòng</th><th class="text-right">Planned</th><th class="text-right">Received</th><th>Action</th></tr></thead><tbody>
          <?php foreach ($plans as $plan): $planStatus = (string)($plan['status'] ?? 'draft'); ?><tr><td class="mono"><a href="<?= h(current_admin_url(['tab' => 'purchase_plan', 'view_plan_id' => $plan['plan_id'], 'view_order_id' => null])) ?>"><?= h($plan['plan_id']) ?></a></td><td><?= h($plan['created_at']) ?></td><td><span class="badge badge-src-mixed"><?= h(plan_status_label($planStatus)) ?></span></td><td class="text-right"><?= (int)($plan['orders_count'] ?? 0) ?></td><td class="text-right"><?= (int)($plan['items_count'] ?? 0) ?></td><td class="text-right"><?= h(decimal_display($plan['qty_planned_uom_total'] ?? 0)) ?></td><td class="text-right"><?= h(decimal_display($plan['qty_received_uom_total'] ?? 0)) ?></td><td><div class="action-group"><a class="btn btn-xs btn-outline" href="<?= h(current_admin_url(['tab' => 'purchase_plan', 'view_plan_id' => $plan['plan_id'], 'view_order_id' => null])) ?>">Xem</a><a class="btn btn-xs btn-outline" href="<?= h(tab_url('receive_po', ['receive_plan_id' => $plan['plan_id']])) ?>">Nhập tồn</a></div></td></tr><?php endforeach; ?>
          <?php if (!$plans): ?><tr><td colspan="8" class="empty-state">Chưa có PO.</td></tr><?php endif; ?>
          </tbody></table></div>
        </div>
      </div>

      <?php if (!empty($selectedPlan['plan_id'])): $planStatus = (string)($selectedPlan['status'] ?? 'draft'); $planLocked = in_array($planStatus, ['received','closed','cancelled'], true); ?>
      <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="po-modal-title">
        <div class="admin-modal modal-wide">
          <div class="modal-header">
            <div id="po-modal-title" class="modal-title">Chi tiết / sửa PO</div>
            <a class="modal-close" href="<?= h(modal_close_url()) ?>" aria-label="Đóng">×</a>
          </div>
          <div class="modal-body">
          <div class="stack">
            <div><strong class="mono"><?= h($selectedPlan['plan_id']) ?></strong> · <?= h(plan_status_label($planStatus)) ?> · <?= h($selectedPlan['supplier_scope'] ?? '') ?></div>
            <div class="muted"><?= h($selectedPlan['order_from_date'] ?? '') ?> → <?= h($selectedPlan['order_to_date'] ?? '') ?></div>
            <?php if (!$planLocked): ?>
              <form method="post" class="action-group">
                <?= csrf_field() ?><input type="hidden" name="action" value="update_purchase_plan_status"><input type="hidden" name="plan_id" value="<?= h($selectedPlan['plan_id']) ?>">
                <?php if ($planStatus === 'draft'): ?><button class="btn btn-primary" name="new_status" value="ordered" type="submit">Chuyển ordered</button><button class="btn btn-outline danger-text" name="new_status" value="cancelled" type="submit" onclick="return confirm('Hủy PO và clear các đơn đã gộp?')">Hủy PO</button><?php endif; ?>
                <?php if (in_array($planStatus, ['ordered','partial_received'], true)): ?><button class="btn btn-outline" name="new_status" value="closed" type="submit">Khóa PO</button><?php endif; ?>
              </form>
            <?php endif; ?>
          </div>
          <div class="grid-2" style="margin-top:16px">
            <div>
              <h3 class="card-title">Đơn trong PO</h3>
              <div class="table-wrap"><table class="data-table"><thead><tr><th>Đơn</th><th>Khách</th><th class="text-right">Dòng PO</th><th>Chi tiết</th></tr></thead><tbody>
              <?php foreach (($selectedPlan['orders'] ?? []) as $poOrder): ?><tr><td class="mono"><?= h($poOrder['order_id']) ?></td><td><?= h($poOrder['customer_name']) ?><div class="muted"><?= h($poOrder['customer_phone']) ?></div></td><td class="text-right"><?= (int)$poOrder['plan_items_count'] ?></td><td><a class="btn btn-xs btn-outline" href="<?= h(current_admin_url(['tab' => 'purchase_plan', 'view_order_id' => $poOrder['order_id'], 'view_plan_id' => null])) ?>">Xem</a></td></tr><?php endforeach; ?>
              <?php if (empty($selectedPlan['orders'])): ?><tr><td colspan="4" class="empty-state">PO chưa có đơn liên quan.</td></tr><?php endif; ?>
              </tbody></table></div>
            </div>
            <div>
              <h3 class="card-title">Copy gửi NCC</h3>
              <textarea class="copy-box" readonly><?= h(admin_copy_purchase_plan_text($pdo, (string)$selectedPlan['plan_id'], '')) ?></textarea>
            </div>
          </div>
          <div class="table-wrap" style="margin-top:16px"><table class="data-table"><thead><tr><th>Nguồn</th><th>Sản phẩm</th><th>UOM</th><th class="text-right">Need</th><th class="text-right">Planned</th><th class="text-right">Received</th><th class="text-right">Còn lại</th><th class="text-right">Cost</th><th>Action</th></tr></thead><tbody>
          <?php foreach (($selectedPlan['items'] ?? []) as $item): $itemId = (int)$item['plan_item_id']; $remaining = max(0, (float)$item['qty_planned_uom'] - (float)$item['qty_received_uom']); ?>
            <tr>
              <td><?= h($item['source_location']) ?></td><td><?= h($item['product_name_snapshot']) ?></td><td><?= h($item['uom_label_snapshot']) ?></td><td class="text-right"><?= h(decimal_display($item['qty_needed_uom'])) ?></td>
              <td class="text-right"><input form="plan-item-<?= $itemId ?>" class="form-input" style="width:100px" type="number" step="0.001" min="<?= h(decimal_display($item['qty_received_uom'])) ?>" name="qty_planned_uom[<?= $itemId ?>]" value="<?= h(decimal_display($item['qty_planned_uom'])) ?>" <?= $planLocked ? 'readonly' : '' ?>></td>
              <td class="text-right"><?= h(decimal_display($item['qty_received_uom'])) ?></td><td class="text-right"><?= h(decimal_display($remaining)) ?></td>
              <td class="text-right"><input form="plan-item-<?= $itemId ?>" class="form-input" style="width:110px" type="number" min="0" name="cost_per_uom_vnd[<?= $itemId ?>]" value="<?= (int)$item['cost_per_uom_vnd'] ?>" <?= $planLocked ? 'readonly' : '' ?>></td>
              <td><div class="action-group">
                <form id="plan-item-<?= $itemId ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_purchase_plan_items"><input type="hidden" name="plan_id" value="<?= h($selectedPlan['plan_id']) ?>"></form>
                <button form="plan-item-<?= $itemId ?>" class="btn btn-xs btn-primary" type="submit" <?= $planLocked ? 'disabled' : '' ?>>Lưu</button>
                <form method="post" class="inline-form" onsubmit="return confirm('Xóa dòng PO này?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_purchase_plan_item"><input type="hidden" name="plan_id" value="<?= h($selectedPlan['plan_id']) ?>"><input type="hidden" name="plan_item_id" value="<?= $itemId ?>"><button class="btn btn-xs btn-outline danger-text" type="submit" <?= ($planLocked || (float)$item['qty_received_uom'] > 0) ? 'disabled' : '' ?>>Xóa</button></form>
              </div></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($selectedPlan['items'])): ?><tr><td colspan="9" class="empty-state">PO chưa có dòng sản phẩm.</td></tr><?php endif; ?>
          </tbody></table></div>
          <form method="post" class="filter-bar" style="margin-top:16px">
            <?= csrf_field() ?><input type="hidden" name="action" value="add_purchase_plan_item"><input type="hidden" name="plan_id" value="<?= h($selectedPlan['plan_id']) ?>">
            <div class="form-group"><label>Thêm sản phẩm</label><select class="form-select" name="product_id" required><option value="">Chọn sản phẩm</option><?php foreach ($productsData['products'] as $p): ?><option value="<?= h($p['product_id']) ?>"><?= h($p['product_name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>UOM</label><select class="form-select" name="uom_id" required><option value="">Chọn UOM</option><?php foreach ($productsData['uoms'] as $uom): ?><option value="<?= h($uom['uom_id']) ?>"><?= h($uom['product_name']) ?> · <?= h($uom['uom_label']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Nguồn</label><select class="form-select" name="source_location"><option>Unknown</option><option>Binh Dinh</option><option>Gia Lai</option></select></div>
            <div class="form-group"><label>Qty planned</label><input class="form-input" type="number" step="0.001" min="0.001" name="qty_planned_uom" required></div>
            <div class="form-group"><label>Cost/UOM</label><input class="form-input" type="number" min="0" name="cost_per_uom_vnd" value="0"></div>
            <button class="btn btn-primary" type="submit" <?= $planLocked ? 'disabled' : '' ?>>Thêm dòng PO</button>
          </form>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($selectedOrder): $selectedOrderStatus = (string)($selectedOrder['status'] ?? 'unknown'); ?>
      <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="po-order-modal-title">
        <div class="admin-modal modal-wide">
          <div class="modal-header">
            <div id="po-order-modal-title" class="modal-title">Hóa đơn chi tiết</div>
            <a class="modal-close" href="<?= h(modal_close_url()) ?>" aria-label="Đóng">×</a>
          </div>
          <div class="modal-body">
            <div class="stack">
              <div><strong class="mono"><?= h($selectedOrder['order_id'] ?? '') ?></strong> · <span class="badge <?= h(status_badge_class($selectedOrderStatus)) ?>"><?= h(admin_status_label($selectedOrderStatus)) ?></span> · <span class="badge <?= h(order_plan_badge_class($selectedOrder['plan_status'] ?? 'unplanned')) ?>"><?= h(admin_order_plan_status_label($selectedOrder['plan_status'] ?? 'unplanned')) ?></span></div>
              <div>Ngày đặt: <strong><?= h($selectedOrder['created_at'] ?? '') ?></strong></div>
              <div><?= h($selectedOrder['customer_name'] ?? '') ?> · <?= h($selectedOrder['customer_phone'] ?? '') ?></div>
              <div class="muted"><?= h((string)($selectedOrder['customer_address'] ?? '')) ?></div>
              <?php if (!empty($selectedOrder['note'])): ?><div class="muted"><?= nl2br(h((string)$selectedOrder['note'])) ?></div><?php endif; ?>
            </div>
            <div class="table-wrap" style="margin-top:16px"><table class="data-table">
              <thead><tr><th>STT</th><th>Sản phẩm</th><th>UOM khách mua</th><th class="text-right">qty_uom</th><th class="text-right">conversion</th><th class="text-right">qty_base</th><th class="text-right">Đơn giá</th><th class="text-right">Thành tiền</th><th>PO</th></tr></thead>
              <tbody>
              <?php foreach (($selectedOrder['items'] ?? []) as $item): ?>
                <tr>
                  <td><?= (int)$item['line_no'] ?></td>
                  <td><?= h($item['product_name_snapshot']) ?><div class="muted"><?= h($item['source_location']) ?></div></td>
                  <td><?= h($item['uom_label_snapshot']) ?></td>
                  <td class="text-right"><?= h(decimal_display($item['qty_uom'])) ?></td>
                  <td class="text-right"><?= h(decimal_display($item['conversion_to_base_snapshot'])) ?></td>
                  <td class="text-right"><strong><?= h(decimal_display($item['qty_base'])) ?></strong></td>
                  <td class="text-right"><?= h(money_vnd($item['unit_price_vnd'])) ?></td>
                  <td class="text-right"><?= h(money_vnd($item['line_total_vnd'])) ?></td>
                  <td class="mono"><?= h($item['planned_plan_id'] ?: '--') ?><?php if (!empty($item['planned_at'])): ?><div class="muted"><?= h($item['planned_at']) ?></div><?php endif; ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table></div>
            <div class="stack" style="align-items:end;margin-top:14px">
              <div>Tạm tính: <strong><?= h(money_vnd($selectedOrder['subtotal_vnd'] ?? 0)) ?></strong></div>
              <div>Phí giao hàng: <strong><?= h(money_vnd($selectedOrder['shipping_fee_vnd'] ?? 0)) ?></strong></div>
              <div>Tổng tiền: <strong><?= h(money_vnd($selectedOrder['total_vnd'] ?? 0)) ?></strong></div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($tab === 'receive_po'): ?>
    <section>
      <div class="card">
        <div class="card-header"><div class="card-title">PO chưa nhận / nhận một phần</div></div>
        <div class="card-body">
          <form class="filter-bar" method="get">
            <input type="hidden" name="tab" value="receive_po">
            <div class="form-group"><label>Tìm PO</label><input class="form-input" name="plan_q" value="<?= h((string)($_GET['plan_q'] ?? '')) ?>"></div>
            <div class="form-group"><label>Status</label><select class="form-select" name="plan_status"><option value="">Mặc định chưa nhận</option><?php foreach (['draft','ordered','partial_received','received','closed','cancelled'] as $ps): ?><option value="<?= h($ps) ?>" <?= (string)($_GET['plan_status'] ?? '') === $ps ? 'selected' : '' ?>><?= h(plan_status_label($ps)) ?></option><?php endforeach; ?></select></div>
            <button class="btn btn-outline" type="submit">Tìm PO</button>
          </form>
        </div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>PO ID</th><th>Ngày tạo</th><th>Trạng thái</th><th class="text-right">Số đơn</th><th class="text-right">Số dòng</th><th class="text-right">Planned</th><th class="text-right">Received</th><th class="text-right">Còn lại</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($plans as $plan): $planStatus = (string)($plan['status'] ?? 'draft'); $remainingBase = max(0, (float)($plan['qty_planned_base_total'] ?? 0) - (float)($plan['qty_received_base_total'] ?? 0)); $canReceive = !in_array($planStatus, ['closed', 'cancelled'], true) && $remainingBase > 0.0001; ?>
          <tr>
            <td class="mono"><?= h($plan['plan_id']) ?></td>
            <td><?= h($plan['created_at']) ?></td>
            <td><span class="badge badge-src-mixed"><?= h(plan_status_label($planStatus)) ?></span></td>
            <td class="text-right"><?= (int)($plan['orders_count'] ?? 0) ?></td>
            <td class="text-right"><?= (int)($plan['items_count'] ?? 0) ?></td>
            <td class="text-right"><?= h(decimal_display($plan['qty_planned_uom_total'] ?? 0)) ?></td>
            <td class="text-right"><?= h(decimal_display($plan['qty_received_uom_total'] ?? 0)) ?></td>
            <td class="text-right"><strong><?= h(decimal_display($remainingBase)) ?></strong> base</td>
            <td><div class="action-group">
              <a class="btn btn-xs btn-outline" href="<?= h(current_admin_url(['tab' => 'receive_po', 'view_plan_id' => $plan['plan_id'], 'receive_plan_id' => null, 'view_order_id' => null, 'plan_id' => null])) ?>">Xem PO</a>
              <?php if ($canReceive): ?>
                <a class="btn btn-xs btn-success" href="<?= h(current_admin_url(['tab' => 'receive_po', 'receive_plan_id' => $plan['plan_id'], 'view_plan_id' => null, 'view_order_id' => null, 'plan_id' => null])) ?>">Nhập tồn</a>
              <?php else: ?>
                <span class="btn btn-xs btn-outline btn-disabled"><?= $planStatus === 'cancelled' ? 'Đã hủy' : 'Không còn hàng cần nhận' ?></span>
              <?php endif; ?>
            </div></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$plans): ?><tr><td colspan="9" class="empty-state">Chưa có PO phù hợp để nhập tồn.</td></tr><?php endif; ?>
        </tbody></table></div>
      </div>

      <?php if (!empty($selectedPlan['plan_id']) && isset($_GET['view_plan_id'])): $planStatus = (string)($selectedPlan['status'] ?? 'draft'); $planRemainingBase = array_reduce(($selectedPlan['items'] ?? []), static fn($carry, $item) => $carry + max(0, (float)$item['qty_planned_base'] - (float)$item['qty_received_base']), 0.0); ?>
      <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="receive-view-po-title">
        <div class="admin-modal modal-wide">
          <div class="modal-header"><div id="receive-view-po-title" class="modal-title">Xem PO: <?= h($selectedPlan['plan_id']) ?></div><a class="modal-close" href="<?= h(modal_close_url()) ?>" aria-label="Đóng">×</a></div>
          <div class="modal-body">
            <div class="stack">
              <div><strong class="mono"><?= h($selectedPlan['plan_id']) ?></strong> · <span class="badge badge-src-mixed"><?= h(plan_status_label($planStatus)) ?></span> · <?= h($selectedPlan['supplier_scope'] ?? '') ?></div>
              <div class="muted">Ngày tạo: <?= h($selectedPlan['created_at'] ?? '') ?> · Khoảng đơn: <?= h($selectedPlan['order_from_date'] ?? '') ?> → <?= h($selectedPlan['order_to_date'] ?? '') ?></div>
              <?php if (!empty($selectedPlan['note'])): ?><div class="muted"><?= nl2br(h((string)$selectedPlan['note'])) ?></div><?php endif; ?>
            </div>
            <h3 class="card-title" style="margin-top:18px">Đơn liên quan</h3>
            <p class="muted" style="margin:6px 0 10px">Bấm mũi tên để xem sản phẩm trong từng đơn.</p>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Order ID</th><th>Khách hàng</th><th>SĐT</th><th class="text-right">Tổng tiền</th><th>Trạng thái</th><th class="text-right">Dòng thuộc PO</th><th></th></tr></thead><tbody>
            <?php foreach (($selectedPlan['orders'] ?? []) as $poOrder): $poOrderStatus = (string)($poOrder['status'] ?? 'unknown'); $poOrderDomId = 'po-order-items-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($poOrder['order_id'] ?? '')); ?>
              <tr class="js-po-order-master-row" data-target="<?= h($poOrderDomId) ?>"><td class="mono"><?= h($poOrder['order_id']) ?></td><td><?= h($poOrder['customer_name']) ?></td><td><?= h($poOrder['customer_phone']) ?></td><td class="text-right"><?= h(money_vnd($poOrder['total_vnd'] ?? 0)) ?></td><td><span class="badge <?= h(status_badge_class($poOrderStatus)) ?>"><?= h(admin_status_label($poOrderStatus)) ?></span></td><td class="text-right"><?= (int)($poOrder['plan_items_count'] ?? count($poOrder['items'] ?? [])) ?></td><td class="text-right"><button class="btn btn-xs btn-outline js-po-order-toggle" type="button" data-target="<?= h($poOrderDomId) ?>" aria-expanded="false">▼</button></td></tr>
              <tr id="<?= h($poOrderDomId) ?>" class="po-order-items-row" hidden><td colspan="7" style="background:var(--gray-50)"><div style="background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius);padding:12px"><div class="table-wrap"><table class="data-table"><thead><tr><th>Sản phẩm</th><th>UOM</th><th>Nguồn</th><th class="text-right">Planned qty</th><th class="text-right">Received qty</th><th class="text-right">Còn lại</th><th class="text-right">Cost/UOM</th></tr></thead><tbody>
                <?php foreach (($poOrder['items'] ?? []) as $orderPoItem): ?>
                  <tr><td><?= h($orderPoItem['product_name'] ?? '') ?></td><td><?= h($orderPoItem['uom_label'] ?? '') ?></td><td><span class="badge <?= h(source_badge_class((string)($orderPoItem['source_location'] ?? 'Unknown'))) ?>"><?= h((string)($orderPoItem['source_location'] ?? 'Unknown')) ?></span></td><td class="text-right"><?= h(decimal_display($orderPoItem['qty_planned_uom'] ?? 0)) ?></td><td class="text-right"><?= h(decimal_display($orderPoItem['qty_received_uom'] ?? 0)) ?></td><td class="text-right"><strong><?= h(decimal_display($orderPoItem['remaining_qty'] ?? 0)) ?></strong></td><td class="text-right"><?= h(money_vnd($orderPoItem['cost_price_vnd'] ?? 0)) ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($poOrder['items'])): ?><tr><td colspan="7" class="empty-state">Đơn này chưa có dòng sản phẩm thuộc PO.</td></tr><?php endif; ?>
              </tbody></table></div></div></td></tr>
            <?php endforeach; ?>
            <?php if (empty($selectedPlan['orders'])): ?><tr><td colspan="7" class="empty-state">PO chưa có đơn liên quan.</td></tr><?php endif; ?>
            </tbody></table></div>
            <?php if (!empty($selectedPlan['outside_items'])): ?>
              <div style="margin-top:14px"><button class="btn btn-xs btn-outline js-po-outside-toggle" type="button" data-target="po-outside-items" aria-expanded="false">▼ Sản phẩm ngoài đơn</button></div>
              <div id="po-outside-items" class="table-wrap" style="margin-top:10px" hidden><table class="data-table"><thead><tr><th>Sản phẩm</th><th>UOM</th><th>Nguồn</th><th class="text-right">Planned qty</th><th class="text-right">Received qty</th><th class="text-right">Còn lại</th><th class="text-right">Cost/UOM</th></tr></thead><tbody>
                <?php foreach (($selectedPlan['outside_items'] ?? []) as $outsideItem): ?>
                  <tr><td><?= h($outsideItem['product_name'] ?? '') ?></td><td><?= h($outsideItem['uom_label'] ?? '') ?></td><td><span class="badge <?= h(source_badge_class((string)($outsideItem['source_location'] ?? 'Unknown'))) ?>"><?= h((string)($outsideItem['source_location'] ?? 'Unknown')) ?></span></td><td class="text-right"><?= h(decimal_display($outsideItem['qty_planned_uom'] ?? 0)) ?></td><td class="text-right"><?= h(decimal_display($outsideItem['qty_received_uom'] ?? 0)) ?></td><td class="text-right"><strong><?= h(decimal_display($outsideItem['remaining_qty'] ?? 0)) ?></strong></td><td class="text-right"><?= h(money_vnd($outsideItem['cost_price_vnd'] ?? 0)) ?></td></tr>
                <?php endforeach; ?>
              </tbody></table></div>
            <?php endif; ?>
            <h3 class="card-title" style="margin-top:18px">Lịch sử nhận hàng</h3>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Receipt</th><th>Lô</th><th>Sản phẩm</th><th class="text-right">qty_base</th><th>Ngày nhận</th><th>HSD</th><th>NCC</th></tr></thead><tbody>
            <?php foreach (($selectedPlan['receipt_items'] ?? []) as $ri): ?><tr><td class="mono"><?= h($ri['receipt_id']) ?></td><td class="mono"><?= h($ri['lot_id']) ?></td><td><?= h($ri['product_name'] ?? $ri['product_id']) ?><div class="muted mono"><?= h($ri['product_id']) ?></div></td><td class="text-right"><?= h(decimal_display($ri['qty_received_base'])) ?></td><td><?= h($ri['received_date']) ?></td><td><?= h($ri['expiry_date'] ?: '--') ?></td><td><?= h($ri['supplier_name'] ?: '--') ?></td></tr><?php endforeach; ?>
            <?php if (empty($selectedPlan['receipt_items'])): ?><tr><td colspan="7" class="empty-state">Chưa có lần nhận hàng.</td></tr><?php endif; ?>
            </tbody></table></div>
          </div>
          <div class="modal-footer"><a class="btn btn-outline" href="<?= h(modal_close_url()) ?>">Đóng</a><?php if (!in_array($planStatus, ['closed', 'cancelled'], true) && $planRemainingBase > 0.0001): ?><a class="btn btn-success" href="<?= h(current_admin_url(['tab' => 'receive_po', 'receive_plan_id' => $selectedPlan['plan_id'], 'view_plan_id' => null, 'view_order_id' => null, 'plan_id' => null])) ?>">Nhập tồn</a><?php endif; ?></div>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($selectedPlan['plan_id']) && isset($_GET['receive_plan_id'])): $receiveStatus = (string)($selectedPlan['status'] ?? 'draft'); $receiveLocked = in_array($receiveStatus, ['closed','cancelled'], true); $receiveRemainingBase = array_reduce(($selectedPlan['items'] ?? []), static fn($carry, $item) => $carry + max(0, (float)$item['qty_planned_base'] - (float)$item['qty_received_base']), 0.0); ?>
      <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="receive-po-modal-title">
        <div class="admin-modal modal-wide">
          <div class="modal-header"><div id="receive-po-modal-title" class="modal-title">Nhập tồn theo PO: <?= h($selectedPlan['plan_id']) ?></div><a class="modal-close" href="<?= h(modal_close_url()) ?>" aria-label="Đóng">×</a></div>
          <form method="post">
            <div class="modal-body">
              <?= csrf_field() ?><input type="hidden" name="action" value="receive_purchase_plan"><input type="hidden" name="return_tab" value="receive_po"><input type="hidden" name="plan_id" value="<?= h($selectedPlan['plan_id']) ?>">
              <div class="stack" style="margin-bottom:16px"><strong class="mono"><?= h($selectedPlan['plan_id']) ?></strong><span><?= h(plan_status_label($receiveStatus)) ?> · <?= h($selectedPlan['supplier_scope'] ?? '') ?></span><?php if ($receiveRemainingBase <= 0.0001): ?><span class="alert alert-warning">PO này không còn số lượng cần nhận.</span><?php endif; ?></div>
              <div class="table-wrap"><table class="data-table"><thead><tr><th>Sản phẩm</th><th>UOM</th><th>Nguồn</th><th class="text-right">Planned</th><th class="text-right">Received</th><th class="text-right">Còn lại</th><th>Receiving qty</th><th>Ngày nhận</th><th>HSD</th><th>Cost/UOM</th><th>NCC</th><th>Ghi chú</th></tr></thead><tbody>
              <?php foreach (($selectedPlan['items'] ?? []) as $item): $itemId = (int)$item['plan_item_id']; $remaining = max(0, (float)$item['qty_planned_uom'] - (float)$item['qty_received_uom']); $lineDisabled = $receiveLocked || $remaining <= 0.0001; $today = date('Y-m-d'); $defaultExpiry = admin_auto_expiry_date($today, (int)($item['shelf_life_value'] ?? 0), (string)($item['shelf_life_unit'] ?? '')); ?>
                <tr><td><?= h($item['product_name_snapshot']) ?></td><td><?= h($item['uom_label_snapshot']) ?></td><td><?= h($item['source_location']) ?></td><td class="text-right"><?= h(decimal_display($item['qty_planned_uom'])) ?></td><td class="text-right"><?= h(decimal_display($item['qty_received_uom'])) ?></td><td class="text-right"><strong><?= h(decimal_display($remaining)) ?></strong></td><td><input class="form-input" style="width:100px" type="number" step="0.001" min="0" max="<?= h(decimal_display($remaining)) ?>" name="receive_qty_uom[<?= $itemId ?>]" value="<?= h(decimal_display($remaining)) ?>" <?= $lineDisabled ? 'disabled' : '' ?>></td><td><input class="form-input js-receive-date" type="date" name="received_date[<?= $itemId ?>]" value="<?= h($today) ?>" data-expiry-target="expiry-<?= $itemId ?>" data-shelf-value="<?= (int)($item['shelf_life_value'] ?? 0) ?>" data-shelf-unit="<?= h((string)($item['shelf_life_unit'] ?? '')) ?>" <?= $lineDisabled ? 'disabled' : '' ?>></td><td><input id="expiry-<?= $itemId ?>" class="form-input js-expiry-date" type="date" name="expiry_date[<?= $itemId ?>]" value="<?= h($defaultExpiry) ?>" data-auto-expiry="1" <?= $lineDisabled ? 'disabled' : '' ?>></td><td><input class="form-input" style="width:110px" type="number" min="0" name="cost_per_uom_vnd[<?= $itemId ?>]" value="<?= (int)$item['cost_per_uom_vnd'] ?>" <?= $lineDisabled ? 'disabled' : '' ?>></td><td><input class="form-input" name="supplier_name[<?= $itemId ?>]" <?= $lineDisabled ? 'disabled' : '' ?>></td><td><input class="form-input" name="line_note[<?= $itemId ?>]" <?= $lineDisabled ? 'disabled' : '' ?>></td></tr>
              <?php endforeach; ?>
              <?php if (empty($selectedPlan['items'])): ?><tr><td colspan="12" class="empty-state">PO chưa có dòng để nhận.</td></tr><?php endif; ?>
              </tbody></table></div>
              <div style="margin-top:16px"><button class="btn btn-outline" type="button" id="toggle-extra-line">+ Thêm dòng ngoài PO</button></div>
              <div id="extra-line-panel" class="receive-extra-panel card" style="margin-top:16px" hidden><div class="card-header"><div class="card-title">Dòng ngoài PO</div></div><div class="card-body grid-3">
                <div class="form-group"><label>Sản phẩm</label><select class="form-select" name="extra_product_id" id="extra_product_id"><option value="">Không thêm</option><?php foreach ($productsData['products'] as $p): if ((int)($p['is_active'] ?? 0) !== 1) { continue; } ?><option value="<?= h($p['product_id']) ?>"><?= h($p['product_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>UOM</label><select class="form-select" name="extra_uom_id" id="extra_uom_id"><option value="">Chọn UOM</option></select></div>
                <div class="form-group"><label>Qty UOM</label><input id="extra_qty_uom" class="form-input" type="number" step="0.001" min="0" name="extra_qty_uom" value="0"></div>
                <div class="form-group"><label>Nguồn</label><select id="extra_source_location" class="form-select" name="extra_source_location"><option>Unknown</option><option>Binh Dinh</option><option>Gia Lai</option></select></div>
                <div class="form-group"><label>Ngày nhận</label><input id="extra_received_date" class="form-input" type="date" name="extra_received_date" value="<?= h(date('Y-m-d')) ?>"></div>
                <div class="form-group"><label>HSD</label><input id="extra_expiry_date" class="form-input js-expiry-date" type="date" name="extra_expiry_date" data-auto-expiry="1"></div>
                <div class="form-group"><label>Cost/UOM</label><input id="extra_cost_per_uom_vnd" class="form-input" type="number" min="0" name="extra_cost_per_uom_vnd" value="0"></div>
                <div class="form-group"><label>NCC</label><input class="form-input" name="extra_supplier_name"></div>
                <div class="form-group"><label>Ghi chú</label><input class="form-input" name="extra_note" placeholder="Hàng giao thêm ngoài PO"></div>
              </div></div>
              <div class="form-group" style="margin-top:16px"><label>Ghi chú receipt</label><textarea name="receipt_note"></textarea></div>
              <label><input type="checkbox" name="auto_close" value="1"> Tự đóng PO nếu nhận đủ</label>
            </div>
            <div class="modal-footer"><a class="btn btn-outline" href="<?= h(modal_close_url()) ?>">Đóng</a><button class="btn btn-success" type="submit" <?= ($receiveLocked || $receiveRemainingBase <= 0.0001) ? 'disabled' : '' ?>>Xác nhận nhập tồn</button></div>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($tab === 'inventory'): ?>
    <section>
      <div class="card">
        <div class="card-header toolbar">
          <div><div class="card-title">Tổng tồn kho</div><div class="muted">Click mã hoặc tên sản phẩm để xem lô và lịch sử movement.</div></div>
          <a class="btn btn-primary" href="<?= h(current_admin_url(['tab' => 'inventory', 'add_stock' => '1', 'inventory_product_id' => null, 'inventory_section' => null])) ?>">+ Thêm nhập tồn</a>
        </div>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Product ID</th><th>Sản phẩm</th><th>Base UOM</th><th class="text-right">On hand</th><th class="text-right">Reserved</th><th class="text-right">Available</th><th>HSD gần nhất</th><th>Trạng thái</th><th>Action</th></tr></thead>
          <tbody>
          <?php foreach ($inventory['summary'] as $row): $available = (float)($row['qty_base_available'] ?? 0); $nearest = (string)($row['nearest_expiry_date'] ?? ''); $stockBadge = 'badge-received'; $stockLabel = 'Ổn'; if ($available <= 0.0001) { $stockBadge = 'badge-cancelled'; $stockLabel = 'Hết tồn'; } elseif ($nearest !== '' && $nearest <= date('Y-m-d', strtotime('+7 days'))) { $stockBadge = 'badge-ordered'; $stockLabel = 'Cần chú ý'; } $inventoryUrl = current_admin_url(['tab' => 'inventory', 'inventory_product_id' => $row['product_id'], 'inventory_section' => 'lots', 'add_stock' => null]); ?>
            <tr><td class="mono"><a href="<?= h($inventoryUrl) ?>"><?= h($row['product_id']) ?></a></td><td><a href="<?= h($inventoryUrl) ?>"><?= h($row['product_name']) ?></a></td><td><?= h($row['base_uom_label']) ?></td><td class="text-right"><?= h(decimal_display($row['qty_base_on_hand'])) ?></td><td class="text-right"><?= h(decimal_display($row['qty_base_reserved'])) ?></td><td class="text-right"><strong><?= h(decimal_display($available)) ?></strong></td><td><?= h($nearest ?: '--') ?></td><td><span class="badge <?= h($stockBadge) ?>"><?= h($stockLabel) ?></span></td><td><a class="btn btn-xs btn-outline" href="<?= h($inventoryUrl) ?>">Xem lô</a></td></tr>
          <?php endforeach; ?>
          <?php if (empty($inventory['summary'])): ?><tr><td colspan="9" class="empty-state">Chưa có dữ liệu tồn kho.</td></tr><?php endif; ?>
          </tbody>
        </table></div>
      </div>

      <?php if (isset($_GET['add_stock'])): ?>
      <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="stock-modal-title">
        <div class="admin-modal modal-wide">
          <div class="modal-header"><div id="stock-modal-title" class="modal-title">Thêm nhập tồn</div><a class="modal-close" href="<?= h(modal_close_url()) ?>" aria-label="Đóng">×</a></div>
          <form method="post">
            <div class="modal-body">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="receive_stock">
              <input type="hidden" name="return_tab" value="inventory">
              <div class="form-grid">
                <div class="form-group"><label>Sản phẩm</label><select class="form-select" name="product_id" id="stock_product_id" required><option value="">Chọn sản phẩm</option><?php foreach ($productsData['products'] as $product): if ((int)($product['is_active'] ?? 0) !== 1) { continue; } ?><option value="<?= h($product['product_id']) ?>"><?= h($product['product_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>UOM nhập</label><select class="form-select" name="uom_id" id="stock_uom_id" required><option value="">Chọn UOM</option></select></div>
                <div class="form-group"><label>Số lượng UOM</label><input id="stock_qty_uom" class="form-input" type="number" step="0.001" min="0.001" name="qty_uom" value="1" required></div>
                <div class="form-group"><label>Nguồn</label><select id="stock_source_location" class="form-select" name="source_location"><option>Unknown</option><option>Binh Dinh</option><option>Gia Lai</option></select></div>
                <div class="form-group"><label>Ngày nhập</label><input id="stock_received_date" class="form-input" type="date" name="received_date" value="<?= h(date('Y-m-d')) ?>" required></div>
                <div class="form-group"><label>HSD</label><input id="stock_expiry_date" class="form-input js-stock-expiry" type="date" name="expiry_date" data-auto-expiry="1"></div>
                <div class="form-group"><label>Cost/UOM</label><input id="stock_cost_per_uom" class="form-input" type="number" min="0" step="1" name="cost_per_uom_vnd" value="0"></div>
                <div class="form-group"><label>Cost/base unit</label><input id="stock_cost_per_base" class="form-input" type="number" min="0" step="1" name="cost_per_base_unit_vnd" value="0" readonly></div>
                <div class="form-group"><label>NCC</label><input class="form-input" name="supplier_name"></div>
                <div class="form-group span-2"><label>Ghi chú</label><textarea name="note"></textarea></div>
              </div>
            </div>
            <div class="modal-footer"><a class="btn btn-outline" href="<?= h(modal_close_url()) ?>">Đóng</a><button class="btn btn-success" type="submit">Nhập lô</button></div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($inventoryProductId !== '' && !$selectedInventoryProduct): ?>
        <div class="modal-backdrop" role="dialog" aria-modal="true"><div class="admin-modal"><div class="modal-header"><div class="modal-title">Tồn kho</div><a class="modal-close" href="<?= h(modal_close_url()) ?>" aria-label="Đóng">×</a></div><div class="modal-body"><div class="empty-state">Không tìm thấy sản phẩm tồn kho cần xem.</div></div></div></div>
      <?php endif; ?>

      <?php if ($selectedInventoryProduct): $inv = $selectedInventoryProduct; ?>
      <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="inventory-product-modal-title">
        <div class="admin-modal modal-wide">
          <div class="modal-header"><div id="inventory-product-modal-title" class="modal-title">Tồn kho: <?= h($inv['product_id']) ?> - <?= h($inv['product_name']) ?></div><a class="modal-close" href="<?= h(modal_close_url()) ?>" aria-label="Đóng">×</a></div>
          <div class="modal-body">
            <div class="summary-strip">
              <div class="summary-item"><span>Product ID</span><strong class="mono"><?= h($inv['product_id']) ?></strong></div>
              <div class="summary-item"><span>Base UOM</span><strong><?= h($inv['base_uom_label']) ?></strong></div>
              <div class="summary-item"><span>On hand</span><strong><?= h(decimal_display($inv['qty_base_on_hand'])) ?></strong></div>
              <div class="summary-item"><span>Reserved</span><strong><?= h(decimal_display($inv['qty_base_reserved'])) ?></strong></div>
              <div class="summary-item"><span>Available</span><strong><?= h(decimal_display($inv['qty_base_available'])) ?></strong></div>
              <div class="summary-item"><span>HSD gần nhất</span><strong><?= h($inv['nearest_expiry_date'] ?: '--') ?></strong></div>
            </div>
            <div class="subtabs">
              <a class="subtab <?= $inventorySection === 'lots' ? 'active' : '' ?>" href="<?= h(current_admin_url(['tab' => 'inventory', 'inventory_product_id' => $inv['product_id'], 'inventory_section' => 'lots'])) ?>">Chi tiết lô</a>
              <a class="subtab <?= $inventorySection === 'history' ? 'active' : '' ?>" href="<?= h(current_admin_url(['tab' => 'inventory', 'inventory_product_id' => $inv['product_id'], 'inventory_section' => 'history'])) ?>">Lịch sử</a>
            </div>
            <?php if ($inventorySection === 'lots'): ?>
              <div class="table-wrap"><table class="data-table"><thead><tr><th>Lot ID</th><th>Ngày nhập</th><th>HSD</th><th class="text-right">On hand</th><th class="text-right">Reserved</th><th class="text-right">Available</th><th>NCC</th><th class="text-right">Cost/base</th><th>Ghi chú</th><th>Action</th></tr></thead><tbody>
              <?php foreach (($inv['lots'] ?? []) as $lot): $lotAvailable = (float)$lot['qty_base_on_hand'] - (float)$lot['qty_base_reserved']; $lotDomId = 'edit-lot-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$lot['lot_id']); ?>
                <tr><td class="mono"><?= h($lot['lot_id']) ?></td><td><?= h($lot['received_date']) ?></td><td><?= h($lot['expiry_date'] ?: '--') ?></td><td class="text-right"><?= h(decimal_display($lot['qty_base_on_hand'])) ?></td><td class="text-right"><?= h(decimal_display($lot['qty_base_reserved'])) ?></td><td class="text-right"><strong><?= h(decimal_display($lotAvailable)) ?></strong></td><td><?= h($lot['supplier_name'] ?: '--') ?></td><td class="text-right"><?= h(money_vnd($lot['cost_per_base_unit_vnd'])) ?></td><td><?= h((string)($lot['note'] ?? '')) ?></td><td><div class="action-group"><?php if (admin_can_manage_config()): ?><button class="btn btn-xs btn-outline js-lot-edit" type="button" data-target="<?= h($lotDomId) ?>">Điều chỉnh</button><form method="post" class="inline-form js-void-lot-form"><?= csrf_field() ?><input type="hidden" name="action" value="void_lot"><input type="hidden" name="return_tab" value="inventory"><input type="hidden" name="return_inventory_product_id" value="<?= h($inv['product_id']) ?>"><input type="hidden" name="lot_id" value="<?= h($lot['lot_id']) ?>"><input type="hidden" name="reason" class="js-void-reason"><button class="btn btn-xs btn-outline danger-text js-void-lot" type="button" <?= (float)$lot['qty_base_reserved'] > 0.0001 ? 'disabled' : '' ?>>Đóng lô</button></form><?php else: ?><span class="muted">Chỉ xem</span><?php endif; ?></div></td></tr>
                <?php if (admin_can_manage_config()): ?><tr id="<?= h($lotDomId) ?>" class="lot-edit-row" hidden><td colspan="10" style="background:var(--gray-50)"><form method="post" class="form-grid compact"><?= csrf_field() ?><input type="hidden" name="action" value="adjust_lot"><input type="hidden" name="return_tab" value="inventory"><input type="hidden" name="return_inventory_product_id" value="<?= h($inv['product_id']) ?>"><input type="hidden" name="lot_id" value="<?= h($lot['lot_id']) ?>"><div class="form-group"><label>Tồn mới base</label><input class="form-input" type="number" step="0.001" min="<?= h(decimal_display($lot['qty_base_reserved'])) ?>" name="new_qty_base" value="<?= h(decimal_display($lot['qty_base_on_hand'])) ?>" required></div><div class="form-group span-2"><label>Lý do</label><input class="form-input" name="reason" placeholder="Bắt buộc nhập lý do rõ ràng" required></div><div class="actions-row"><button class="btn btn-success" type="submit">Lưu</button><button class="btn btn-outline js-lot-cancel" type="button" data-target="<?= h($lotDomId) ?>">Hủy</button></div></form></td></tr><?php endif; ?>
              <?php endforeach; ?>
              <?php if (empty($inv['lots'])): ?><tr><td colspan="10" class="empty-state">Sản phẩm này chưa có lô tồn kho.</td></tr><?php endif; ?>
              </tbody></table></div>
            <?php else: ?>
              <div class="table-wrap"><table class="data-table"><thead><tr><th>Thời gian</th><th>Type</th><th>Ref</th><th>Lot ID</th><th class="text-right">Qty base</th><th>Ghi chú</th></tr></thead><tbody>
              <?php foreach (($inv['movements'] ?? []) as $m): ?><tr><td><?= h($m['created_at']) ?></td><td><span class="badge badge-src-mixed"><?= h($m['movement_type']) ?></span></td><td><?= h($m['ref_type']) ?> <?= h($m['ref_id']) ?></td><td class="mono"><?= h($m['lot_id'] ?: '--') ?></td><td class="text-right"><?= h(decimal_display($m['qty_base'])) ?></td><td><?= h((string)$m['note']) ?></td></tr><?php endforeach; ?>
              <?php if (empty($inv['movements'])): ?><tr><td colspan="6" class="empty-state">Chưa có lịch sử movement cho sản phẩm này.</td></tr><?php endif; ?>
              </tbody></table></div>
            <?php endif; ?>
          </div>
          <div class="modal-footer"><a class="btn btn-outline" href="<?= h(modal_close_url()) ?>">Đóng</a></div>
        </div>
      </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($tab === 'products'): ?>
    <section>
      <?php if (!admin_can_manage_config()): ?><div class="alert alert-warning">Staff chỉ được xem sản phẩm/ảnh, không được sửa hoặc upload.</div><?php endif; ?>
      <form class="filter-bar" method="get">
        <input type="hidden" name="tab" value="products">
        <div class="form-group"><label>Tìm sản phẩm</label><input class="form-input" name="product_q" value="<?= h((string)$productFilters['q']) ?>" placeholder="Tên, ID, slug"></div>
        <div class="form-group"><label>Danh mục</label><select class="form-select" name="category_id"><option value="">Tất cả</option><?php foreach ($productsData['categories'] as $category): ?><option value="<?= h($category['category_id']) ?>" <?= $productFilters['category_id'] === $category['category_id'] ? 'selected' : '' ?>><?= h($category['category_name']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Trạng thái</label><select class="form-select" name="active"><option value="">Tất cả</option><option value="1" <?= $productFilters['active'] === '1' ? 'selected' : '' ?>>Đang hoạt động</option><option value="0" <?= $productFilters['active'] === '0' ? 'selected' : '' ?>>Ngưng hoạt động</option></select></div>
        <button class="btn btn-outline" type="submit">Lọc sản phẩm</button>
        <a class="btn btn-primary" href="<?= h(current_admin_url(['tab' => 'products', 'mode' => 'create', 'product_id' => null, 'product_section' => null])) ?>">+ Thêm sản phẩm</a>
      </form>
      <?php if ($productMode === 'create'): ?>
      <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="create-product-modal-title">
        <div class="admin-modal">
          <div class="modal-header">
            <div id="create-product-modal-title" class="modal-title">Thêm sản phẩm</div>
            <a class="modal-close" href="<?= h(modal_close_url()) ?>" aria-label="Đóng">×</a>
          </div>
          <div class="modal-body">
          <form method="post" class="grid-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="create_product">
            <div class="form-group"><label>Product ID</label><input class="form-input" name="product_id" placeholder="pro_014" required></div>
            <div class="form-group"><label>Tên sản phẩm</label><input class="form-input" name="product_name" required></div>
            <div class="form-group"><label>Slug</label><input class="form-input" name="product_slug" placeholder="ten-san-pham" required></div>
            <div class="form-group"><label>Danh mục</label><select class="form-select" name="category_id" required><?php foreach ($productsData['categories'] as $category): ?><option value="<?= h($category['category_id']) ?>"><?= h($category['category_name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Nguồn</label><select class="form-select" name="default_source"><option>Binh Dinh</option><option>Gia Lai</option><option>Unknown</option></select></div>
            <div class="form-group"><label>Base UOM label</label><input class="form-input" name="base_uom_label" required></div>
            <div class="form-group"><label>Mô tả ngắn</label><input class="form-input" name="short_description"></div>
            <label><input type="checkbox" name="is_active" value="1" checked> Đang hoạt động</label>
            <button class="btn btn-primary" type="submit" <?= admin_can_manage_config() ? '' : 'disabled' ?>>Tạo sản phẩm</button>
          </form>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <div class="card">
        <div class="card-header"><div class="card-title">Danh sách sản phẩm</div></div>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Ảnh</th><th>Product ID</th><th>Tên sản phẩm</th><th>Category</th><th>Nguồn</th><th>UOM mặc định</th><th class="text-right">Giá mặc định</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
          <tbody>
          <?php foreach ($productList as $product): ?>
            <tr>
              <td><img class="thumb" src="<?= h(app_image_path($product['base_image_path'] ?? '')) ?>" alt="<?= h($product['product_name']) ?>"></td>
              <td class="mono"><?= h($product['product_id']) ?></td>
              <td><?= h($product['product_name']) ?><div class="muted"><?= h($product['product_slug']) ?></div></td>
              <td><?= h($product['category_name']) ?></td>
              <td><span class="badge <?= h(source_badge_class($product['default_source'])) ?>"><?= h($product['default_source']) ?></span></td>
              <td><?= h($product['default_uom_label'] ?? '--') ?></td>
              <td class="text-right"><?= h(money_vnd($product['default_price_vnd'] ?? 0)) ?></td>
              <td><span class="badge <?= (int)$product['is_active'] === 1 ? 'badge-received' : 'badge-done' ?>"><?= (int)$product['is_active'] === 1 ? 'Đang hoạt động' : 'Ngưng hoạt động' ?></span></td>
              <td><div class="action-group">
                <a class="btn btn-xs btn-outline" href="<?= h(current_admin_url(['tab' => 'products', 'mode' => 'edit', 'product_id' => $product['product_id'], 'product_section' => null])) ?>">Chỉnh sửa</a>
                <?php if (admin_can_manage_config()): ?>
                <form method="post" class="inline-form" onsubmit="<?= (int)$product['is_active'] === 1 ? "return confirm('Ngưng hoạt động sản phẩm này? Nếu còn tồn, sản phẩm sẽ không bán trên storefront nhưng dữ liệu vẫn giữ.')" : 'return true' ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="product_id" value="<?= h($product['product_id']) ?>"><input type="hidden" name="is_active" value="<?= (int)$product['is_active'] === 1 ? 0 : 1 ?>"><button class="btn btn-xs btn-outline" type="submit"><?= (int)$product['is_active'] === 1 ? 'Ngưng hoạt động' : 'Kích hoạt lại' ?></button></form>
                <form method="post" class="inline-form" onsubmit="return confirm('Chỉ xóa được sản phẩm chưa có giao dịch/tồn kho/PO. Tiếp tục?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_product"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="product_id" value="<?= h($product['product_id']) ?>"><button class="btn btn-xs btn-outline danger-text" type="submit">Xóa</button></form>
                <?php endif; ?>
              </div></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$productList): ?><tr><td colspan="9" class="empty-state">Không tìm thấy sản phẩm.</td></tr><?php endif; ?>
          </tbody>
        </table></div>
      </div>
      <?php if ($productMode === 'edit' && !$selectedProduct): ?><div class="modal-backdrop" role="dialog" aria-modal="true"><div class="admin-modal"><div class="modal-header"><div class="modal-title">Chỉnh sửa sản phẩm</div><a class="modal-close" href="<?= h(modal_close_url()) ?>" aria-label="Đóng">×</a></div><div class="modal-body"><div class="empty-state">Không tìm thấy sản phẩm cần chỉnh sửa.</div></div></div></div><?php endif; ?>
      <?php if ($selectedProduct): ?>
      <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="edit-product-modal-title">
        <div class="admin-modal modal-wide">
          <div class="modal-header">
            <div id="edit-product-modal-title" class="modal-title">Chỉnh sửa: <?= h($selectedProduct['product_name']) ?></div>
            <a class="modal-close" href="<?= h(modal_close_url()) ?>" aria-label="Đóng">×</a>
          </div>
          <div class="modal-body">
          <div class="subtabs">
            <?php foreach (['basic' => 'Thông tin cơ bản', 'images' => 'Hình ảnh sản phẩm', 'uom' => 'UOM sản phẩm', 'inventory' => 'Tồn kho liên quan'] as $sectionId => $label): ?>
              <a class="subtab <?= $productSection === $sectionId ? 'active' : '' ?>" href="<?= h(current_admin_url(['tab' => 'products', 'mode' => 'edit', 'product_id' => $selectedProduct['product_id'], 'product_section' => $sectionId])) ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
          </div>
          <?php if ($productSection === 'basic'): ?>
            <form method="post" class="grid-3">
              <?= csrf_field() ?><input type="hidden" name="action" value="update_product"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="product_id" value="<?= h($selectedProduct['product_id']) ?>">
              <div class="form-group"><label>Product ID</label><input class="form-input mono" value="<?= h($selectedProduct['product_id']) ?>" readonly></div>
              <div class="form-group"><label>Tên sản phẩm</label><input class="form-input" name="product_name" value="<?= h($selectedProduct['product_name']) ?>" required></div>
              <div class="form-group"><label>Slug</label><input class="form-input" name="product_slug" value="<?= h($selectedProduct['product_slug']) ?>" required></div>
              <div class="form-group"><label>Danh mục</label><select class="form-select" name="category_id"><?php foreach ($productsData['categories'] as $category): ?><option value="<?= h($category['category_id']) ?>" <?= $selectedProduct['category_id'] === $category['category_id'] ? 'selected' : '' ?>><?= h($category['category_name']) ?></option><?php endforeach; ?></select></div>
              <div class="form-group"><label>Nguồn hàng</label><select class="form-select" name="default_source"><?php foreach (['Binh Dinh','Gia Lai','Unknown'] as $source): ?><option value="<?= h($source) ?>" <?= $selectedProduct['default_source'] === $source ? 'selected' : '' ?>><?= h($source) ?></option><?php endforeach; ?></select></div>
              <div class="form-group"><label>Base UOM label</label><input class="form-input" name="base_uom_label" value="<?= h($selectedProduct['base_uom_label']) ?>" required></div>
              <div class="form-group"><label>Shelf life</label><input class="form-input" type="number" min="0" name="shelf_life_value" value="<?= (int)$selectedProduct['shelf_life_value'] ?>"></div>
              <div class="form-group"><label>Đơn vị HSD</label><select class="form-select" name="shelf_life_unit"><option value="" <?= $selectedProduct['shelf_life_unit'] === '' ? 'selected' : '' ?>>Không</option><option value="days" <?= $selectedProduct['shelf_life_unit'] === 'days' ? 'selected' : '' ?>>Ngày</option><option value="months" <?= $selectedProduct['shelf_life_unit'] === 'months' ? 'selected' : '' ?>>Tháng</option></select></div>
              <div class="form-group"><label>Trạng thái</label><select class="form-select" name="is_active"><option value="1" <?= (int)$selectedProduct['is_active'] === 1 ? 'selected' : '' ?>>Đang hoạt động</option><option value="0" <?= (int)$selectedProduct['is_active'] === 0 ? 'selected' : '' ?>>Ngưng hoạt động</option></select></div>
              <div class="form-group"><label>Mô tả ngắn</label><textarea name="short_description"><?= h((string)$selectedProduct['short_description']) ?></textarea></div>
              <div class="form-group"><label>Mô tả đầy đủ</label><textarea name="full_description"><?= h((string)$selectedProduct['full_description']) ?></textarea></div>
              <div class="form-group"><label>Thành phần</label><textarea name="ingredients"><?= h((string)$selectedProduct['ingredients']) ?></textarea></div>
              <button class="btn btn-primary" type="submit" <?= admin_can_manage_config() ? '' : 'disabled' ?>>Lưu thông tin cơ bản</button>
            </form>
          <?php elseif ($productSection === 'images'): ?>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Ảnh</th><th>Path</th><th>Alt</th><th>Sort</th><th>Ảnh chính</th><th>Active</th><th>Action</th></tr></thead><tbody>
            <?php foreach (($selectedProduct['images'] ?? []) as $image): ?>
              <tr><td><img class="thumb thumb-lg" src="<?= h(app_image_path($image['image_path'])) ?>" alt="<?= h($image['image_alt']) ?>"></td><td class="mono"><?= h($image['image_path']) ?></td><td colspan="3"><form method="post" class="action-group"><?= csrf_field() ?><input type="hidden" name="action" value="save_product_image"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="product_id" value="<?= h($selectedProduct['product_id']) ?>"><input type="hidden" name="image_id" value="<?= (int)$image['image_id'] ?>"><input class="form-input" name="image_alt" value="<?= h((string)$image['image_alt']) ?>" style="width:180px"><input class="form-input" type="number" name="sort_order" value="<?= (int)$image['sort_order'] ?>" style="width:80px"><label><input type="checkbox" name="is_base" value="1" <?= (int)$image['is_base'] ? 'checked' : '' ?>> Ảnh chính</label><label><input type="checkbox" name="is_active" value="1" <?= (int)$image['is_active'] ? 'checked' : '' ?>> Active</label><button class="btn btn-xs btn-primary" type="submit" <?= admin_can_manage_config() ? '' : 'disabled' ?>>Sửa</button></form></td><td><?= (int)$image['is_active'] ? 'Có' : 'Không' ?></td><td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="set_base_image"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="product_id" value="<?= h($selectedProduct['product_id']) ?>"><input type="hidden" name="image_id" value="<?= (int)$image['image_id'] ?>"><button class="btn btn-xs btn-outline" type="submit" <?= admin_can_manage_config() ? '' : 'disabled' ?>>Đặt làm ảnh chính</button></form><form method="post" class="inline-form" onsubmit="return confirm('Ẩn ảnh này?')"><?= csrf_field() ?><input type="hidden" name="action" value="inactive_product_image"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="product_id" value="<?= h($selectedProduct['product_id']) ?>"><input type="hidden" name="image_id" value="<?= (int)$image['image_id'] ?>"><button class="btn btn-xs btn-outline danger-text" type="submit" <?= admin_can_manage_config() ? '' : 'disabled' ?>>Xóa/Ẩn</button></form></td></tr>
            <?php endforeach; ?>
            <?php if (empty($selectedProduct['images'])): ?><tr><td colspan="7" class="empty-state">Sản phẩm chưa có ảnh. Storefront sẽ dùng placeholder.</td></tr><?php endif; ?>
            </tbody></table></div>
            <div class="grid-2" style="margin-top:16px">
              <form method="post" enctype="multipart/form-data" class="card-body stack">
                <?= csrf_field() ?><input type="hidden" name="action" value="upload_product_image"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="product_id" value="<?= h($selectedProduct['product_id']) ?>">
                <div class="form-group"><label>Upload jpg/png/webp</label><input class="form-input" type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp" required></div>
                <div class="form-group"><label>Alt text</label><input class="form-input" name="image_alt"></div>
                <div class="form-group"><label>Sort order</label><input class="form-input" type="number" name="sort_order" value="10"></div>
                <label><input type="checkbox" name="is_base" value="1"> Đặt làm ảnh chính</label>
                <button class="btn btn-primary" type="submit" <?= admin_can_manage_config() ? '' : 'disabled' ?>>+ Thêm ảnh</button>
              </form>
              <form method="post" class="card-body stack">
                <?= csrf_field() ?><input type="hidden" name="action" value="save_product_image"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="product_id" value="<?= h($selectedProduct['product_id']) ?>"><input type="hidden" name="image_id" value="0">
                <div class="form-group"><label>Nhập path ảnh</label><input class="form-input" name="image_path" placeholder="products_image/ten-anh.jpg" required></div>
                <div class="form-group"><label>Alt text</label><input class="form-input" name="image_alt"></div>
                <div class="form-group"><label>Sort order</label><input class="form-input" type="number" name="sort_order" value="10"></div>
                <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
                <label><input type="checkbox" name="is_base" value="1"> Đặt làm ảnh chính</label>
                <button class="btn btn-primary" type="submit" <?= admin_can_manage_config() ? '' : 'disabled' ?>>+ Thêm path ảnh</button>
              </form>
            </div>
          <?php elseif ($productSection === 'uom'): ?>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>UOM ID</th><th>Nhãn UOM</th><th class="text-right">conversion</th><th class="text-right">Giá bán</th><th class="text-right">Giá vốn</th><th>Flags</th><th>Refs</th><th>Actions</th></tr></thead><tbody>
            <?php foreach (($selectedProduct['uoms'] ?? []) as $uom): $uomRefs = (int)$uom['order_refs'] + (int)$uom['movement_refs']; ?>
              <tr>
                <td class="mono"><?= h($uom['uom_id']) ?></td>
                <td colspan="6"><form method="post" class="action-group"><?= csrf_field() ?><input type="hidden" name="return_tab" value="products"><input type="hidden" name="action" value="save_uom"><input type="hidden" name="uom_id" value="<?= h($uom['uom_id']) ?>"><input type="hidden" name="product_id" value="<?= h($selectedProduct['product_id']) ?>"><input class="form-input" name="uom_label" value="<?= h($uom['uom_label']) ?>" style="width:150px" required><input class="form-input" type="number" step="0.001" min="0.001" name="conversion_to_base" value="<?= h(decimal_display($uom['conversion_to_base'])) ?>" style="width:110px" <?= $uomRefs > 0 ? 'readonly' : '' ?> required><input class="form-input" type="number" min="0" name="unit_price_vnd" value="<?= (int)$uom['unit_price_vnd'] ?>" style="width:110px"><input class="form-input" type="number" min="0" name="cost_price_vnd" value="<?= (int)$uom['cost_price_vnd'] ?>" style="width:110px"><input class="form-input" type="number" name="sort_order" value="<?= (int)$uom['sort_order'] ?>" style="width:76px"><label><input type="checkbox" name="is_base_unit" value="1" <?= (int)$uom['is_base_unit'] ? 'checked' : '' ?>> Base</label><label><input type="checkbox" name="is_default" value="1" <?= (int)$uom['is_default'] ? 'checked' : '' ?>> Default</label><label><input type="checkbox" name="is_sellable" value="1" <?= (int)$uom['is_sellable'] ? 'checked' : '' ?>> Sellable</label><label><input type="checkbox" name="is_purchasable" value="1" <?= (int)$uom['is_purchasable'] ? 'checked' : '' ?>> Purchasable</label><label><input type="checkbox" name="is_active" value="1" <?= (int)$uom['is_active'] ? 'checked' : '' ?>> Active</label><input class="form-input" name="note" value="<?= h((string)$uom['note']) ?>" placeholder="Note" style="width:180px"><button class="btn btn-xs btn-primary" type="submit" <?= admin_can_manage_config() ? '' : 'disabled' ?>>Sửa</button></form><div class="muted"><?= (int)$uom['is_default'] ? 'Default ' : '' ?><?= (int)$uom['is_base_unit'] ? 'Base ' : '' ?><?= (int)$uom['is_active'] ? 'Active' : 'Inactive' ?> · <?= (int)$uom['order_refs'] ?> order / <?= (int)$uom['movement_refs'] ?> movement</div></td>
                <td><form method="post" class="inline-form" onsubmit="return confirm('Ngưng hoạt động UOM này?')"><?= csrf_field() ?><input type="hidden" name="action" value="deactivate_uom"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="product_id" value="<?= h($selectedProduct['product_id']) ?>"><input type="hidden" name="uom_id" value="<?= h($uom['uom_id']) ?>"><button class="btn btn-xs btn-outline danger-text" type="submit" <?= admin_can_manage_config() ? '' : 'disabled' ?>>Ngưng hoạt động</button></form><form method="post" class="inline-form" onsubmit="return confirm('Chỉ xóa UOM chưa có giao dịch. Tiếp tục?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_uom"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="product_id" value="<?= h($selectedProduct['product_id']) ?>"><input type="hidden" name="uom_id" value="<?= h($uom['uom_id']) ?>"><button class="btn btn-xs btn-outline danger-text" type="submit" <?= admin_can_manage_config() ? '' : 'disabled' ?>>Xóa</button></form></td>
              </tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <form method="post" class="filter-bar" style="margin-top:16px">
              <?= csrf_field() ?><input type="hidden" name="action" value="save_uom"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="product_id" value="<?= h($selectedProduct['product_id']) ?>">
              <div class="form-group"><label>UOM ID</label><input class="form-input" name="uom_id" placeholder="<?= h($selectedProduct['product_id']) ?>_NEW" required></div>
              <div class="form-group"><label>Nhãn UOM</label><input class="form-input" name="uom_label" required></div>
              <div class="form-group"><label>conversion_to_base</label><input class="form-input" type="number" step="0.001" min="0.001" name="conversion_to_base" value="1" required></div>
              <div class="form-group"><label>Giá bán</label><input class="form-input" type="number" min="0" name="unit_price_vnd" value="0"></div>
              <div class="form-group"><label>Giá vốn</label><input class="form-input" type="number" min="0" name="cost_price_vnd" value="0"></div>
              <label><input type="checkbox" name="is_base_unit" value="1"> Base</label><label><input type="checkbox" name="is_default" value="1"> Default</label><label><input type="checkbox" name="is_sellable" value="1" checked> Sellable</label><label><input type="checkbox" name="is_purchasable" value="1" checked> Purchasable</label><label><input type="checkbox" name="is_active" value="1" checked> Active</label>
              <button class="btn btn-primary" type="submit" <?= admin_can_manage_config() ? '' : 'disabled' ?>>+ Thêm UOM</button>
            </form>
          <?php else: ?>
            <?php $sum = $selectedProduct['inventory_summary'] ?: []; ?>
            <div class="kpi-grid"><div class="kpi-card"><div class="kpi-label">On hand</div><div class="kpi-value"><?= h(decimal_display($sum['qty_base_on_hand'] ?? 0)) ?></div></div><div class="kpi-card"><div class="kpi-label">Reserved</div><div class="kpi-value"><?= h(decimal_display($sum['qty_base_reserved'] ?? 0)) ?></div></div><div class="kpi-card"><div class="kpi-label">Available</div><div class="kpi-value"><?= h(decimal_display($sum['qty_base_available'] ?? 0)) ?></div></div></div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Lô</th><th>Ngày nhập</th><th>HSD</th><th class="text-right">On hand</th><th class="text-right">Reserved</th></tr></thead><tbody><?php foreach (($selectedProduct['lots'] ?? []) as $lot): ?><tr><td class="mono"><?= h($lot['lot_id']) ?></td><td><?= h($lot['received_date']) ?></td><td><?= h($lot['expiry_date'] ?: '--') ?></td><td class="text-right"><?= h(decimal_display($lot['qty_base_on_hand'])) ?></td><td class="text-right"><?= h(decimal_display($lot['qty_base_reserved'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
          <?php endif; ?>
          </div>
      </div>
      </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($tab === 'settings'): ?>
    <section>
      <?php if (!admin_can_manage_config()): ?><div class="alert alert-warning">Staff không được sửa cấu hình.</div><?php endif; ?>
      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">Cấu hình bán hàng</div></div>
          <div class="card-body">
            <?php foreach (['store_name' => 'Tên cửa hàng', 'free_ship_threshold' => 'Ngưỡng miễn phí ship', 'default_shipping_zone_id' => 'Vùng giao mặc định', 'store_phone' => 'SĐT cửa hàng', 'zalo_link' => 'Link Zalo'] as $key => $label): ?>
              <form method="post" class="filter-bar">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_setting">
                <input type="hidden" name="return_tab" value="settings">
                <input type="hidden" name="setting_key" value="<?= h($key) ?>">
                <div class="form-group" style="flex:1"><label><?= h($label) ?></label>
                <?php if ($key === 'default_shipping_zone_id'): ?>
                  <select class="form-select" name="setting_value"><?php foreach ($zones as $zone): ?><option value="<?= h($zone['zone_id']) ?>" <?= ($settings[$key] ?? '') === $zone['zone_id'] ? 'selected' : '' ?>><?= h($zone['zone_name']) ?> · <?= h(money_vnd($zone['fee_vnd'])) ?></option><?php endforeach; ?></select>
                <?php else: ?>
                  <input class="form-input" name="setting_value" value="<?= h((string)($settings[$key] ?? '')) ?>">
                <?php endif; ?>
                </div>
                <button class="btn btn-primary" type="submit" <?= admin_can_manage_config() ? '' : 'disabled' ?>>Lưu</button>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">Shipping zones</div></div>
          <div class="card-body stack">
            <form method="post" class="filter-bar">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save_shipping_zone">
              <input type="hidden" name="return_tab" value="settings">
              <div class="form-group" style="min-width:140px"><label>Mã vùng</label><input class="form-input" name="zone_id" placeholder="ZONE_NEW" required></div>
              <div class="form-group" style="min-width:180px;flex:1"><label>Tên vùng</label><input class="form-input" name="zone_name" required></div>
              <div class="form-group" style="width:130px"><label>Phí ship</label><input class="form-input" type="number" min="0" name="fee_vnd" value="0"></div>
              <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
              <label><input type="checkbox" name="is_default" value="1"> Default</label>
              <button class="btn btn-primary" type="submit" <?= admin_can_manage_config() ? '' : 'disabled' ?>>Thêm vùng</button>
            </form>
            <?php foreach ($zones as $zone): ?>
              <form method="post" class="filter-bar">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_shipping_zone">
                <input type="hidden" name="return_tab" value="settings">
                <div class="form-group" style="min-width:140px"><label>Mã vùng</label><input class="form-input mono" name="zone_id" value="<?= h($zone['zone_id']) ?>" readonly></div>
                <div class="form-group" style="min-width:180px;flex:1"><label>Tên vùng</label><input class="form-input" name="zone_name" value="<?= h($zone['zone_name']) ?>" required></div>
                <div class="form-group" style="width:130px"><label>Phí ship</label><input class="form-input" type="number" min="0" name="fee_vnd" value="<?= (int)$zone['fee_vnd'] ?>"></div>
                <label><input type="checkbox" name="is_active" value="1" <?= (int)$zone['is_active'] ? 'checked' : '' ?>> Active</label>
                <label><input type="checkbox" name="is_default" value="1" <?= (int)$zone['is_default'] ? 'checked' : '' ?>> Default</label>
                <button class="btn btn-primary" type="submit" <?= admin_can_manage_config() ? '' : 'disabled' ?>>Lưu vùng</button>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>
  <script>
  (() => {
    const closeToast = (toast) => {
      if (!toast) {
        return;
      }
      toast.classList.add('is-hiding');
      window.setTimeout(() => toast.remove(), 180);
    };
    document.querySelectorAll('[data-toast]').forEach((toast) => {
      const timer = window.setTimeout(() => closeToast(toast), 10000);
      toast.querySelectorAll('[data-toast-close]').forEach((button) => {
        button.addEventListener('click', () => {
          window.clearTimeout(timer);
          closeToast(toast);
        });
      });
    });
  })();
  </script>
  <?php if ($tab === 'receive_po'): ?>
    <script>
    (() => {
      const products = <?= $receiveProductsJson ?>;
      const uoms = <?= $receiveUomsJson ?>;
      const byProduct = new Map(products.map((product) => [product.product_id, product]));
      const pad = (value) => String(value).padStart(2, '0');
      const formatDate = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
      const calculateExpiry = (receivedDate, shelfValue, shelfUnit) => {
        const value = Number(shelfValue || 0);
        if (!receivedDate || value <= 0 || !['days', 'months'].includes(shelfUnit || '')) {
          return '';
        }
        const date = new Date(`${receivedDate}T00:00:00`);
        if (Number.isNaN(date.getTime())) {
          return '';
        }
        if (shelfUnit === 'months') {
          date.setMonth(date.getMonth() + value);
        } else {
          date.setDate(date.getDate() + value);
        }
        return formatDate(date);
      };

      const togglePanel = (button) => {
        const target = document.getElementById(button.dataset.target || '');
        if (!target) {
          return;
        }
        target.hidden = !target.hidden;
        const expanded = !target.hidden;
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (button.classList.contains('js-po-order-toggle')) {
          button.textContent = expanded ? '▲' : '▼';
        } else if (button.classList.contains('js-po-outside-toggle')) {
          button.textContent = (expanded ? '▲ ' : '▼ ') + 'Sản phẩm ngoài đơn';
        }
      };

      document.querySelectorAll('.js-po-order-toggle, .js-po-outside-toggle').forEach((button) => {
        button.addEventListener('click', (event) => {
          event.stopPropagation();
          togglePanel(button);
        });
      });
      document.querySelectorAll('.js-po-order-master-row').forEach((row) => {
        row.addEventListener('click', (event) => {
          if (event.target.closest('button, a, input, select, textarea')) {
            return;
          }
          const button = row.querySelector('.js-po-order-toggle');
          if (button) {
            togglePanel(button);
          }
        });
      });

      document.querySelectorAll('.js-expiry-date').forEach((input) => {
        input.addEventListener('input', () => {
          input.dataset.autoExpiry = '0';
        });
      });

      document.querySelectorAll('.js-receive-date').forEach((input) => {
        const updateLineExpiry = () => {
          const target = document.getElementById(input.dataset.expiryTarget || '');
          if (!target || target.dataset.autoExpiry === '0') {
            return;
          }
          target.value = calculateExpiry(input.value, input.dataset.shelfValue, input.dataset.shelfUnit);
          target.dataset.autoExpiry = '1';
        };
        input.addEventListener('change', updateLineExpiry);
        updateLineExpiry();
      });

      const toggleExtra = document.getElementById('toggle-extra-line');
      const extraPanel = document.getElementById('extra-line-panel');
      const productSelect = document.getElementById('extra_product_id');
      const uomSelect = document.getElementById('extra_uom_id');
      const qtyInput = document.getElementById('extra_qty_uom');
      const sourceSelect = document.getElementById('extra_source_location');
      const receivedInput = document.getElementById('extra_received_date');
      const expiryInput = document.getElementById('extra_expiry_date');
      const costInput = document.getElementById('extra_cost_per_uom_vnd');

      const setExtraExpiry = () => {
        if (!productSelect || !expiryInput || expiryInput.dataset.autoExpiry === '0') {
          return;
        }
        const product = byProduct.get(productSelect.value);
        expiryInput.value = product ? calculateExpiry(receivedInput.value, product.shelf_life_value, product.shelf_life_unit) : '';
        expiryInput.dataset.autoExpiry = '1';
      };
      const fillUoms = () => {
        if (!productSelect || !uomSelect) {
          return;
        }
        const productId = productSelect.value;
        uomSelect.innerHTML = '<option value="">Chọn UOM</option>';
        uoms
          .filter((uom) => uom.product_id === productId && Number(uom.is_active) === 1 && Number(uom.is_purchasable) === 1)
          .forEach((uom) => {
            const option = document.createElement('option');
            option.value = uom.uom_id;
            option.textContent = `${uom.uom_label} · x${uom.conversion_to_base}`;
            option.dataset.cost = String(uom.cost_price_vnd || 0);
            uomSelect.appendChild(option);
          });
        if (uomSelect.options.length > 1) {
          uomSelect.selectedIndex = 1;
        }
        const selected = uomSelect.selectedOptions[0];
        costInput.value = selected ? (selected.dataset.cost || '0') : '0';
      };

      if (toggleExtra && extraPanel) {
        toggleExtra.addEventListener('click', () => {
          extraPanel.hidden = !extraPanel.hidden;
        });
      }
      if (productSelect) {
        productSelect.addEventListener('change', () => {
          const product = byProduct.get(productSelect.value);
          if (product && sourceSelect) {
            sourceSelect.value = ['Binh Dinh', 'Gia Lai', 'Unknown'].includes(product.default_source) ? product.default_source : 'Unknown';
          }
          if (qtyInput && productSelect.value && Number(qtyInput.value || 0) <= 0) {
            qtyInput.value = '1';
          } else if (qtyInput && !productSelect.value) {
            qtyInput.value = '0';
          }
          fillUoms();
          setExtraExpiry();
        });
      }
      if (uomSelect) {
        uomSelect.addEventListener('change', () => {
          const selected = uomSelect.selectedOptions[0];
          costInput.value = selected ? (selected.dataset.cost || '0') : '0';
        });
      }
      if (receivedInput) {
        receivedInput.addEventListener('change', setExtraExpiry);
      }
    })();
    </script>
  <?php endif; ?>
  <?php if ($tab === 'inventory'): ?>
    <script>
    (() => {
      const products = <?= $receiveProductsJson ?>;
      const uoms = <?= $receiveUomsJson ?>;
      const byProduct = new Map(products.map((product) => [product.product_id, product]));
      const pad = (value) => String(value).padStart(2, '0');
      const formatDate = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
      const calculateExpiry = (receivedDate, shelfValue, shelfUnit) => {
        const value = Number(shelfValue || 0);
        if (!receivedDate || value <= 0 || !['days', 'months'].includes(shelfUnit || '')) {
          return '';
        }
        const date = new Date(`${receivedDate}T00:00:00`);
        if (Number.isNaN(date.getTime())) {
          return '';
        }
        if (shelfUnit === 'months') {
          date.setMonth(date.getMonth() + value);
        } else {
          date.setDate(date.getDate() + value);
        }
        return formatDate(date);
      };

      const productSelect = document.getElementById('stock_product_id');
      const uomSelect = document.getElementById('stock_uom_id');
      const qtyInput = document.getElementById('stock_qty_uom');
      const sourceSelect = document.getElementById('stock_source_location');
      const receivedInput = document.getElementById('stock_received_date');
      const expiryInput = document.getElementById('stock_expiry_date');
      const costUomInput = document.getElementById('stock_cost_per_uom');
      const costBaseInput = document.getElementById('stock_cost_per_base');

      const selectedUom = () => uomSelect ? uoms.find((uom) => uom.uom_id === uomSelect.value) : null;
      const updateBaseCost = () => {
        const uom = selectedUom();
        const cost = Number(costUomInput?.value || 0);
        const conversion = Number(uom?.conversion_to_base || 1);
        if (costBaseInput) {
          costBaseInput.value = conversion > 0 ? String(Math.round(cost / conversion)) : '0';
        }
      };
      const updateExpiry = () => {
        if (!productSelect || !expiryInput || expiryInput.dataset.autoExpiry === '0') {
          return;
        }
        const product = byProduct.get(productSelect.value);
        expiryInput.value = product ? calculateExpiry(receivedInput.value, product.shelf_life_value, product.shelf_life_unit) : '';
        expiryInput.dataset.autoExpiry = '1';
      };
      const fillStockUoms = () => {
        if (!productSelect || !uomSelect) {
          return;
        }
        const productId = productSelect.value;
        uomSelect.innerHTML = '<option value="">Chọn UOM</option>';
        const candidates = uoms.filter((uom) => uom.product_id === productId && Number(uom.is_active) === 1 && Number(uom.is_purchasable) === 1);
        candidates.forEach((uom) => {
          const option = document.createElement('option');
          option.value = uom.uom_id;
          option.textContent = `${uom.uom_label} · x${uom.conversion_to_base}`;
          option.dataset.cost = String(uom.cost_price_vnd || 0);
          uomSelect.appendChild(option);
        });
        const defaultIndex = candidates.findIndex((uom) => Number(uom.is_default) === 1);
        if (candidates.length > 0) {
          uomSelect.selectedIndex = defaultIndex >= 0 ? defaultIndex + 1 : 1;
        }
        const selected = uomSelect.selectedOptions[0];
        if (costUomInput) {
          costUomInput.value = selected ? (selected.dataset.cost || '0') : '0';
        }
        updateBaseCost();
      };

      if (expiryInput) {
        expiryInput.addEventListener('input', () => {
          expiryInput.dataset.autoExpiry = '0';
        });
      }
      if (productSelect) {
        productSelect.addEventListener('change', () => {
          const product = byProduct.get(productSelect.value);
          if (product && sourceSelect) {
            sourceSelect.value = ['Binh Dinh', 'Gia Lai', 'Unknown'].includes(product.default_source) ? product.default_source : 'Unknown';
          }
          if (qtyInput && productSelect.value && Number(qtyInput.value || 0) <= 0) {
            qtyInput.value = '1';
          }
          if (expiryInput) {
            expiryInput.dataset.autoExpiry = '1';
          }
          fillStockUoms();
          updateExpiry();
        });
      }
      if (uomSelect) {
        uomSelect.addEventListener('change', () => {
          const selected = uomSelect.selectedOptions[0];
          if (costUomInput) {
            costUomInput.value = selected ? (selected.dataset.cost || '0') : '0';
          }
          updateBaseCost();
        });
      }
      if (costUomInput) {
        costUomInput.addEventListener('input', updateBaseCost);
      }
      if (receivedInput) {
        receivedInput.addEventListener('change', updateExpiry);
      }

      document.querySelectorAll('.js-lot-edit').forEach((button) => {
        button.addEventListener('click', () => {
          const target = document.getElementById(button.dataset.target || '');
          if (!target) {
            return;
          }
          target.hidden = !target.hidden;
          button.textContent = target.hidden ? 'Điều chỉnh' : 'Đang chỉnh';
        });
      });
      document.querySelectorAll('.js-lot-cancel').forEach((button) => {
        button.addEventListener('click', () => {
          const target = document.getElementById(button.dataset.target || '');
          if (target) {
            target.hidden = true;
          }
          document.querySelectorAll(`.js-lot-edit[data-target="${button.dataset.target}"]`).forEach((editButton) => {
            editButton.textContent = 'Điều chỉnh';
          });
        });
      });
      document.querySelectorAll('.js-void-lot').forEach((button) => {
        button.addEventListener('click', () => {
          const form = button.closest('form');
          if (!form) {
            return;
          }
          const reason = window.prompt('Nhập lý do đóng lô:');
          if (!reason || reason.trim().length < 5) {
            window.alert('Vui lòng nhập lý do rõ ràng, tối thiểu 5 ký tự.');
            return;
          }
          if (!window.confirm('Đóng lô sẽ tạo movement ADJUST về 0, không xóa lịch sử. Tiếp tục?')) {
            return;
          }
          const input = form.querySelector('.js-void-reason');
          if (input) {
            input.value = reason.trim();
          }
          form.submit();
        });
      });

      if (productSelect && productSelect.value) {
        fillStockUoms();
        updateExpiry();
      }
    })();
    </script>
  <?php endif; ?>
</main>
</body>
</html>
