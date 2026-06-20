# Complete Admin Operations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hoàn thiện admin PHP hiện tại thành hệ thống vận hành có chi tiết, action, hóa đơn in, quản lý sản phẩm, kho, PO, cài đặt và phân quyền.

**Architecture:** Giữ server-rendered PHP và MySQL hiện tại. Logic nghiệp vụ nằm trong service/repository, `admin/index.php` chỉ định tuyến trang, `admin/api/index.php` chỉ định tuyến API, còn `admin.js` cung cấp lớp gọi API và tương tác form. Tất cả mutation dùng CSRF, authorization phía server và transaction khi thay đổi nhiều bảng hoặc tồn kho.

**Tech Stack:** PHP 8.x, PDO MySQL/MariaDB, vanilla JavaScript, HTML/CSS, XAMPP Apache/MySQL, custom PHP regression tests, Playwright tạm thời ngoài repository cho browser QA.

---

## File map

**Create**

- `app/Services/AdminAuthorizationService.php`: ma trận quyền owner/admin/staff.
- `app/Services/AdminProductService.php`: validation và transaction thêm/sửa sản phẩm, UOM, ảnh.
- `app/Services/AdminInventoryService.php`: nhập kho thủ công và điều chỉnh lot.
- `app/Services/AdminSettingsService.php`: settings nghiệp vụ và vùng giao hàng.
- `app/Services/AdminUserService.php`: quản lý tài khoản và bảo vệ owner cuối cùng.
- `app/Services/UploadService.php`: kiểm tra và lưu JPEG/PNG/WebP an toàn.
- `app/Repositories/AdminProductRepository.php`: CRUD không xóa cho product/UOM/image.
- `app/Repositories/AdminSettingsRepository.php`: upsert settings và shipping zones.
- `views/admin/order-detail.php`: vận hành đơn và hóa đơn.
- `views/admin/product-detail.php`: chi tiết sản phẩm.
- `views/admin/product-form.php`: thêm/sửa sản phẩm, UOM và ảnh.
- `views/admin/purchase-plan-detail.php`: chi tiết, nhận hàng và hủy PO.
- `views/admin/inventory-lot.php`: chi tiết lot và điều chỉnh.
- `views/admin/admin-users.php`: quản lý tài khoản.
- `tests/TestSupport.php`: assertion helpers và kết nối test DB.
- `tests/AdminAuthorizationTest.php`
- `tests/AdminOrderViewTest.php`
- `tests/AdminProductServiceTest.php`
- `tests/AdminInventoryServiceTest.php`
- `tests/AdminSettingsServiceTest.php`
- `tests/AdminUserServiceTest.php`
- `tests/AdminApiSmokeTest.php`
- `database/migrations/20260620_admin_operations.sql`: seed settings thanh toán cho database đang tồn tại.

**Modify**

- `database/database.sql`: seed settings thanh toán.
- `admin/index.php`: route trang chi tiết và inject service/data đúng quyền.
- `admin/api/index.php`: endpoint mutation/detail mới và kiểm tra quyền.
- `app/Services/AdminAuthService.php`: expose role helpers nếu cần.
- `app/Services/AdminService.php`: pagination/detail data dùng chung.
- `app/Services/OrderService.php`: trả transition labels và giữ quy tắc trạng thái.
- `app/Services/InventoryService.php`: helper tạo lot/movement dùng chung.
- `app/Repositories/AdminDashboardRepository.php`: pagination và dữ liệu danh sách.
- `app/Repositories/AdminUserRepository.php`: list/create/update/count owner.
- `app/Repositories/InventoryRepository.php`: lock lot và signed adjustment.
- `app/Repositories/OrderRepository.php`: dữ liệu hóa đơn/PO liên quan.
- `app/Repositories/PurchasePlanRepository.php`: dữ liệu chi tiết nhận hàng.
- `views/admin/layout.php`: navigation theo quyền, toast/modal host, titles.
- `views/admin/orders.php`
- `views/admin/products.php`
- `views/admin/inventory.php`
- `views/admin/purchase-plans.php`
- `views/admin/settings.php`
- `views/admin/dashboard.php`
- `public/assets/js/admin.js`
- `public/assets/css/admin.css`
- `README.md`

## Test database rule

Không chạy mutation test trên `dac_san_nha_dan`. Tạo database `dac_san_nha_dan_test`, import baseline, chạy test, rồi drop database:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3306 -u root -e "DROP DATABASE IF EXISTS dac_san_nha_dan_test; CREATE DATABASE dac_san_nha_dan_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
Get-Content database\database.sql |
  ForEach-Object { $_ -replace 'dac_san_nha_dan', 'dac_san_nha_dan_test' } |
  & 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3306 -u root
$env:DB_DATABASE='dac_san_nha_dan_test'
```

Sau QA:

```powershell
Remove-Item Env:DB_DATABASE -ErrorAction SilentlyContinue
& 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3306 -u root -e "DROP DATABASE IF EXISTS dac_san_nha_dan_test;"
```

### Task 1: Build reusable test support and authorization policy

**Files:**

- Create: `tests/TestSupport.php`
- Create: `tests/AdminAuthorizationTest.php`
- Create: `app/Services/AdminAuthorizationService.php`
- Modify: `app/Services/AdminAuthService.php`

- [ ] **Step 1: Write the failing authorization test**

```php
<?php

declare(strict_types=1);

require __DIR__ . '/TestSupport.php';

use DacSanNhaDan\Core\AppException;
use DacSanNhaDan\Services\AdminAuthorizationService;

$policy = new AdminAuthorizationService();

assertTrue($policy->allows('owner', 'admin_users.manage'), 'Owner manages admin users.');
assertTrue($policy->allows('admin', 'products.manage'), 'Admin manages products.');
assertTrue($policy->allows('staff', 'orders.transition'), 'Staff transitions orders.');
assertFalse($policy->allows('staff', 'inventory.manage'), 'Staff cannot mutate inventory.');
assertThrows(
    static fn () => $policy->require('staff', 'settings.manage'),
    AppException::class,
    403
);

echo "Admin authorization tests passed.\n";
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```powershell
C:\xampp\php\php.exe tests\AdminAuthorizationTest.php
```

Expected: failure because `AdminAuthorizationService` does not exist.

- [ ] **Step 3: Implement the explicit permission matrix**

```php
<?php

declare(strict_types=1);

namespace DacSanNhaDan\Services;

use DacSanNhaDan\Core\AppException;

final class AdminAuthorizationService
{
    private const PERMISSIONS = [
        'owner' => ['*'],
        'admin' => [
            'dashboard.view', 'orders.view', 'orders.transition', 'orders.print',
            'products.view', 'products.manage', 'inventory.view', 'inventory.manage',
            'purchase_plans.view', 'purchase_plans.manage', 'settings.view',
            'settings.manage',
        ],
        'staff' => [
            'dashboard.view', 'orders.view', 'orders.transition', 'orders.print',
            'purchase_plans.view', 'purchase_plans.manage',
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
```

Add `role()` and `can()` helpers to `AdminAuthService` that delegate to this policy without changing login behavior.

- [ ] **Step 4: Run authorization tests**

Run:

```powershell
C:\xampp\php\php.exe tests\AdminAuthorizationTest.php
```

Expected: `Admin authorization tests passed.`

- [ ] **Step 5: Commit**

```powershell
git add app/Services/AdminAuthorizationService.php app/Services/AdminAuthService.php tests/TestSupport.php tests/AdminAuthorizationTest.php
git commit -m "feat: add admin role authorization"
```

### Task 2: Add admin detail routes and shared page loading

**Files:**

- Modify: `admin/index.php`
- Modify: `views/admin/layout.php`
- Modify: `public/assets/css/admin.css`
- Test: `tests/AdminOrderViewTest.php`

- [ ] **Step 1: Write a failing route/view test**

The test logs in through a test session or calls the page data resolver directly and asserts:

```php
assertSameValue('order-detail', admin_page_from_value('order-detail'));
assertSameValue('product-form', admin_page_from_value('product-form'));
assertSameValue('dashboard', admin_page_from_value('invalid'));
```

It also renders layout data for a staff user and asserts that product, inventory, settings and user-management mutation navigation is absent.

- [ ] **Step 2: Verify RED**

Run:

```powershell
C:\xampp\php\php.exe tests\AdminOrderViewTest.php
```

Expected: route names are rejected by the current allowlist.

- [ ] **Step 3: Add page allowlist and permission map**

Use these page permissions:

```php
$pagePermissions = [
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
```

Validate `id` with `/^[A-Za-z0-9_-]{1,80}$/`. Unknown or unauthorized pages must not silently expose data: unknown routes fall back to dashboard, unauthorized routes throw 403.

- [ ] **Step 4: Add shared layout primitives**

Add semantic classes for:

- `.page-actions`
- `.button`, `.button-secondary`, `.button-danger`
- `.status-pill[data-status]`
- `.detail-grid`
- `.panel`
- `.form-grid`
- `.field-error`
- `.toast-host`
- `.is-loading`

Do not redesign storefront files.

- [ ] **Step 5: Verify GREEN and PHP syntax**

```powershell
C:\xampp\php\php.exe tests\AdminOrderViewTest.php
Get-ChildItem admin,app,views -Recurse -Filter *.php |
  ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```

Expected: route test passes and all PHP files report no syntax errors.

- [ ] **Step 6: Commit**

```powershell
git add admin/index.php views/admin/layout.php public/assets/css/admin.css tests/AdminOrderViewTest.php
git commit -m "feat: add permission-aware admin detail routes"
```

### Task 3: Complete order detail, invoice and status actions

**Files:**

- Modify: `app/Repositories/OrderRepository.php`
- Modify: `app/Repositories/AdminDashboardRepository.php`
- Modify: `app/Services/AdminService.php`
- Modify: `app/Services/OrderService.php`
- Modify: `admin/api/index.php`
- Modify: `views/admin/orders.php`
- Create: `views/admin/order-detail.php`
- Modify: `public/assets/js/admin.js`
- Modify: `public/assets/css/admin.css`
- Test: `tests/AdminOrderViewTest.php`

- [ ] **Step 1: Add failing invoice data assertions**

For a seeded order, assert the detail payload includes:

```php
assertArrayHasKeyValue('items', $detail);
assertArrayHasKeyValue('allocations', $detail);
assertArrayHasKeyValue('movements', $detail);
assertArrayHasKeyValue('payment', $detail);
assertSameValue('Đặc Sản Nhà Dân', $detail['store']['store_name']);
assertSameValue(['confirmed', 'cancelled'], $detail['allowed_next_statuses']);
```

- [ ] **Step 2: Verify RED**

Run:

```powershell
$env:DB_DATABASE='dac_san_nha_dan_test'
C:\xampp\php\php.exe tests\AdminOrderViewTest.php
```

Expected: `payment`, `store` and `allowed_next_statuses` are missing.

- [ ] **Step 3: Extend order detail composition**

`AdminService::orderDetail()` must combine:

```php
return [
    ...$order,
    'allowed_next_statuses' => $orderService->getAllowedTransitions((string) $order['status']),
    'store' => [
        'store_name' => $settings['store_name'] ?? '',
        'store_phone' => $settings['store_phone'] ?? '',
        'zalo_link' => $settings['zalo_link'] ?? '',
    ],
    'payment' => [
        'bank_name' => $settings['bank_name'] ?? '',
        'bank_account_number' => $settings['bank_account_number'] ?? '',
        'bank_account_holder' => $settings['bank_account_holder'] ?? '',
        'bank_transfer_content' => str_replace('{order_id}', $orderId, $settings['bank_transfer_content'] ?? $orderId),
        'bank_qr_image_path' => $settings['bank_qr_image_path'] ?? '',
    ],
];
```

Keep item snapshot values as the invoice source.

- [ ] **Step 4: Build the order detail page**

The view must contain:

```html
<section class="order-operations no-print">...</section>
<article class="invoice" id="invoice">...</article>
<button type="button" data-print-invoice>In hóa đơn</button>
```

Invoice table columns: sản phẩm/UOM, số lượng, đơn giá, thành tiền. Add store, customer, delivery, totals, payment details and QR when configured.

- [ ] **Step 5: Wire status mutation**

Use one shared JSON helper in `admin.js`:

```js
async function adminRequest(action, options = {}) {
    const response = await fetch(`${window.DSND_ADMIN.apiBase}?action=${encodeURIComponent(action)}`, {
        credentials: 'same-origin',
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.DSND_ADMIN.csrfToken,
            ...(options.headers || {}),
        },
    });
    const payload = await response.json();
    if (!response.ok || payload.status !== 'ok') {
        throw new Error(payload.message || 'Không thể xử lý yêu cầu.');
    }
    return payload.data;
}
```

Status buttons POST `{order_id, new_status}` to `order-status`, disable during request, show confirmation for cancellation, then reload detail on success.

- [ ] **Step 6: Add print CSS**

```css
@media print {
    .sidebar,
    .topbar,
    .no-print,
    .toast-host {
        display: none !important;
    }

    .admin-shell,
    .content {
        display: block;
        padding: 0;
        background: #fff;
    }

    .invoice {
        width: 100%;
        border: 0;
        box-shadow: none;
    }
}
```

- [ ] **Step 7: Verify**

```powershell
C:\xampp\php\php.exe tests\AdminOrderViewTest.php
```

Then login at `http://localhost/Dacsan/admin`, open a seeded order, confirm invoice fields, execute one valid status transition only in test DB, and verify invalid transition returns 422.

- [ ] **Step 8: Commit**

```powershell
git add app/Repositories/OrderRepository.php app/Repositories/AdminDashboardRepository.php app/Services/AdminService.php app/Services/OrderService.php admin/api/index.php views/admin/orders.php views/admin/order-detail.php public/assets/js/admin.js public/assets/css/admin.css tests/AdminOrderViewTest.php
git commit -m "feat: add order operations and printable invoice"
```

### Task 4: Implement product create/update without deletion

**Files:**

- Create: `app/Repositories/AdminProductRepository.php`
- Create: `app/Services/AdminProductService.php`
- Create: `tests/AdminProductServiceTest.php`
- Modify: `admin/api/index.php`
- Modify: `admin/index.php`
- Modify: `views/admin/products.php`
- Create: `views/admin/product-detail.php`
- Create: `views/admin/product-form.php`
- Modify: `public/assets/js/admin.js`
- Modify: `public/assets/css/admin.css`

- [ ] **Step 1: Write failing product validation tests**

Test these cases independently:

```php
assertThrowsCreateProductWithNoBaseUom();
assertThrowsCreateProductWithTwoBaseUoms();
assertThrowsCreateProductWithBaseConversionNotOne();
assertThrowsCreateProductWithDuplicateSlug();
assertCreatesProductWithOneBaseAndOneDefaultUom();
assertUpdatePreservesProductId();
assertDeactivationKeepsProductRow();
```

Use product IDs prefixed `TEST_PRODUCT_` and clean them in `finally`.

- [ ] **Step 2: Verify RED**

```powershell
C:\xampp\php\php.exe tests\AdminProductServiceTest.php
```

Expected: service/repository classes do not exist.

- [ ] **Step 3: Implement repository transaction primitives**

Required methods:

```php
findProductDetail(string $productId): ?array
slugExists(string $slug, ?string $exceptProductId = null): bool
createProduct(array $product): void
updateProduct(string $productId, array $product): void
upsertUom(string $productId, array $uom): void
deactivateMissingUoms(string $productId, array $submittedUomIds): void
insertImage(array $image): int
updateImage(int $imageId, string $productId, array $image): void
deactivateMissingImages(string $productId, array $submittedImageIds): void
setProductActive(string $productId, bool $active): void
```

No delete methods are permitted.

- [ ] **Step 4: Implement service validation**

`save(array $payload, ?string $existingProductId = null): string` must:

- Validate ID, name, slug, category, source, shelf life.
- Load category name into `category_label`; never trust client label.
- Require one base UOM with conversion 1.
- Allow at most one active default UOM.
- Require non-negative prices and positive conversion.
- Use transaction for product and UOM/image metadata.
- Keep product ID immutable on update.

- [ ] **Step 5: Add product APIs**

Endpoints and permissions:

```text
GET  product-detail   products.view
POST product-save     products.manage
POST product-active   products.manage
```

`product-save` accepts JSON metadata first; image upload is a separate endpoint in Task 5.

- [ ] **Step 6: Build list/detail/form UI**

The form submits a payload shaped as:

```json
{
  "product_id": "pro_014",
  "product_name": "Tên sản phẩm",
  "product_slug": "ten-san-pham",
  "category_id": "Cate_001",
  "default_source": "Binh Dinh",
  "base_uom_label": "Gói 500g",
  "shelf_life_value": 4,
  "shelf_life_unit": "months",
  "is_active": 1,
  "uoms": [
    {
      "uom_id": "pro_014_500G",
      "uom_label": "Gói 500g",
      "conversion_to_base": 1,
      "unit_price_vnd": 100000,
      "cost_price_vnd": 70000,
      "is_base_unit": 1,
      "is_default": 1,
      "is_sellable": 1,
      "is_purchasable": 1,
      "is_active": 1,
      "sort_order": 1
    }
  ]
}
```

There must be no delete product button or API.

- [ ] **Step 7: Verify GREEN**

```powershell
C:\xampp\php\php.exe tests\AdminProductServiceTest.php
```

Manually create, edit, deactivate and reactivate a `TEST_PRODUCT_UI` product in test DB, then verify existing order snapshots are unchanged.

- [ ] **Step 8: Commit**

```powershell
git add app/Repositories/AdminProductRepository.php app/Services/AdminProductService.php admin/api/index.php admin/index.php views/admin/products.php views/admin/product-detail.php views/admin/product-form.php public/assets/js/admin.js public/assets/css/admin.css tests/AdminProductServiceTest.php
git commit -m "feat: add non-destructive product management"
```

### Task 5: Add safe image and payment QR uploads

**Files:**

- Create: `app/Services/UploadService.php`
- Modify: `admin/api/index.php`
- Modify: `views/admin/product-form.php`
- Modify: `views/admin/settings.php`
- Modify: `public/assets/js/admin.js`
- Test: `tests/AdminProductServiceTest.php`

- [ ] **Step 1: Write failing upload tests**

Create temporary files outside the repo and assert:

```php
assertAcceptsJpegPngWebp();
assertRejectsPhpDisguisedAsJpeg();
assertRejectsFileOverFiveMegabytes();
assertGeneratedNameMatches('/^products_image\/[a-z0-9-]+_[a-f0-9]{12}\.(jpg|png|webp)$/');
assertStoredPathStaysInsideProductsImage();
```

- [ ] **Step 2: Verify RED**

```powershell
C:\xampp\php\php.exe tests\AdminProductServiceTest.php
```

Expected: upload assertions fail because `UploadService` is missing.

- [ ] **Step 3: Implement upload validation**

`UploadService::storeImage(array $file, string $prefix): array` must:

- Require `UPLOAD_ERR_OK`.
- Limit size to 5 MiB.
- Read MIME using `finfo(FILEINFO_MIME_TYPE)`.
- Allow only `image/jpeg`, `image/png`, `image/webp`.
- Generate lowercase safe prefix plus 12 random hex characters.
- Resolve and verify destination under `<root>/products_image`.
- Use `move_uploaded_file`.
- Return `['path' => 'products_image/...', 'absolute_path' => '...']`.

- [ ] **Step 4: Add multipart endpoints**

```text
POST product-image-upload  products.manage
POST payment-qr-upload     settings.manage
```

For product image upload, insert `product_images` metadata only after file storage succeeds. If DB insert fails, remove the new file using `Remove-Item` equivalent in PHP (`unlink`) only for the exact generated absolute path.

- [ ] **Step 5: Wire previews**

Use `FormData`, do not set `Content-Type` manually, send CSRF through `X-CSRF-Token`, and refresh gallery after upload.

- [ ] **Step 6: Verify and clean artifacts**

Upload one temporary test image, verify browser rendering, deactivate its image row, then delete the exact `TEST_` file and database row created by the test. Confirm no `.php`, `.phtml`, `.phar` or double-extension upload exists.

- [ ] **Step 7: Commit**

```powershell
git add app/Services/UploadService.php admin/api/index.php views/admin/product-form.php views/admin/settings.php public/assets/js/admin.js tests/AdminProductServiceTest.php
git commit -m "feat: add safe admin image uploads"
```

### Task 6: Implement manual stock receipt and lot adjustments

**Files:**

- Create: `app/Services/AdminInventoryService.php`
- Modify: `app/Repositories/InventoryRepository.php`
- Modify: `app/Services/InventoryService.php`
- Modify: `admin/api/index.php`
- Modify: `admin/index.php`
- Modify: `views/admin/inventory.php`
- Create: `views/admin/inventory-lot.php`
- Modify: `public/assets/js/admin.js`
- Test: `tests/AdminInventoryServiceTest.php`

- [ ] **Step 1: Write failing inventory transaction tests**

Test:

```php
assertManualReceiptCreatesLotAndInMovement();
assertPositiveAdjustmentUpdatesLotAndCreatesSignedMovement();
assertNegativeAdjustmentUpdatesLotAndCreatesSignedMovement();
assertNegativeAdjustmentCannotDropBelowReserved();
assertFailedAdjustmentLeavesLotAndMovementCountUnchanged();
```

- [ ] **Step 2: Verify RED**

```powershell
C:\xampp\php\php.exe tests\AdminInventoryServiceTest.php
```

- [ ] **Step 3: Add repository locking and signed adjustment**

Required methods:

```php
findLotForUpdate(string $lotId): ?array
adjustLotOnHand(string $lotId, float $deltaBase): void
movementsForLot(string $lotId, int $limit = 200): array
```

`adjustLotOnHand` SQL must enforce:

```sql
qty_base_on_hand + :delta >= qty_base_reserved
AND qty_base_on_hand + :delta >= 0
```

- [ ] **Step 4: Implement admin inventory service**

`receiveManual(array $payload): string`:

- Validate product/UOM relationship.
- Compute `qty_base = qty_uom * conversion_to_base`.
- Create lot and `IN/MANUAL` movement in one transaction.

`adjustLot(string $lotId, float $deltaBase, string $reason): string`:

- Reject zero delta.
- Require reason of 3–500 characters.
- Lock lot.
- Apply signed delta.
- Create `ADJUST/MANUAL` movement using the same signed `qty_base`.
- Roll back on any error.

- [ ] **Step 5: Add APIs and views**

```text
GET  inventory-lot-detail inventory.view
POST inventory-receive    inventory.manage
POST inventory-adjust     inventory.manage
```

The inventory page has `Nhập kho thủ công`; lot rows link to detail. Lot detail shows current quantities, dates, supplier, cost and movements, plus adjustment form for admin/owner.

- [ ] **Step 6: Verify GREEN**

```powershell
C:\xampp\php\php.exe tests\AdminInventoryServiceTest.php
```

Check before/after lot quantity and matching movement in test DB. Remove test lots and movements in test cleanup only.

- [ ] **Step 7: Commit**

```powershell
git add app/Services/AdminInventoryService.php app/Repositories/InventoryRepository.php app/Services/InventoryService.php admin/api/index.php admin/index.php views/admin/inventory.php views/admin/inventory-lot.php public/assets/js/admin.js tests/AdminInventoryServiceTest.php
git commit -m "feat: add manual inventory operations"
```

### Task 7: Complete purchase plan UI and receipt workflow

**Files:**

- Modify: `app/Repositories/PurchasePlanRepository.php`
- Modify: `app/Services/PurchasePlanService.php`
- Modify: `admin/api/index.php`
- Modify: `admin/index.php`
- Modify: `views/admin/orders.php`
- Modify: `views/admin/purchase-plans.php`
- Create: `views/admin/purchase-plan-detail.php`
- Modify: `public/assets/js/admin.js`
- Modify: `public/assets/css/admin.css`
- Test: `tests/AdminApiSmokeTest.php`

- [ ] **Step 1: Write failing PO flow test**

In test DB:

1. Create or reset two test orders to `confirmed`.
2. Call preview and assert grouped quantities.
3. Create PO and assert linked orders become `ordered`.
4. Receive one partial line and assert `partial_received`.
5. Receive remaining quantities and assert `received`.
6. Assert linked orders become `received`.
7. Assert cancel is rejected after receipt.

- [ ] **Step 2: Verify RED**

Run:

```powershell
C:\xampp\php\php.exe tests\AdminApiSmokeTest.php --case=po-flow
```

Expected: UI/detail contract or helper assertions are missing.

- [ ] **Step 3: Normalize PO detail payload**

Each item must include:

```php
$item['qty_remaining_uom'] = max(
    0,
    (float) $item['qty_planned_uom'] - (float) $item['qty_received_uom']
);
$item['can_receive'] = $item['qty_remaining_uom'] > 0.0001;
```

Detail must also include `can_cancel`, `can_receive`, linked orders, receipts and receipt items grouped by receipt.

- [ ] **Step 4: Build PO creation flow from orders**

Checkboxes are enabled only for `confirmed` orders with unplanned items. `Xem trước PO` opens a preview panel, and `Tạo PO` requires confirmation plus optional note.

- [ ] **Step 5: Build PO detail and receipt form**

For each remaining item collect:

- `qty_received_uom`
- `cost_per_uom_vnd`
- `received_date`
- `expiry_date`
- `supplier_name`
- `note`

POST the exact `items` array accepted by `receive-po`. Copy button uses `navigator.clipboard.writeText()` with fallback textarea selection.

- [ ] **Step 6: Verify GREEN**

```powershell
C:\xampp\php\php.exe tests\AdminApiSmokeTest.php --case=po-flow
```

Browser-check preview, create, detail, copy, partial receive, full receive and forbidden cancel.

- [ ] **Step 7: Commit**

```powershell
git add app/Repositories/PurchasePlanRepository.php app/Services/PurchasePlanService.php admin/api/index.php admin/index.php views/admin/orders.php views/admin/purchase-plans.php views/admin/purchase-plan-detail.php public/assets/js/admin.js public/assets/css/admin.css tests/AdminApiSmokeTest.php
git commit -m "feat: complete purchase plan operations"
```

### Task 8: Add payment settings and shipping-zone management

**Files:**

- Create: `database/migrations/20260620_admin_operations.sql`
- Modify: `database/database.sql`
- Create: `app/Repositories/AdminSettingsRepository.php`
- Create: `app/Services/AdminSettingsService.php`
- Modify: `admin/api/index.php`
- Modify: `admin/index.php`
- Modify: `views/admin/settings.php`
- Modify: `public/assets/js/admin.js`
- Test: `tests/AdminSettingsServiceTest.php`

- [ ] **Step 1: Write failing settings tests**

Test:

```php
assertUpsertsKnownPaymentSettings();
assertRejectsUnknownSettingKey();
assertCreatesShippingZone();
assertUpdatesShippingZoneFee();
assertOnlyOneZoneIsDefault();
assertCannotSetInactiveZoneAsDefault();
assertDeactivationMovesDefaultToAnotherActiveZoneOrFails();
```

- [ ] **Step 2: Verify RED**

```powershell
C:\xampp\php\php.exe tests\AdminSettingsServiceTest.php
```

- [ ] **Step 3: Add migration and baseline settings**

```sql
INSERT INTO settings (setting_key, setting_value, note) VALUES
  ('bank_name', '', 'Tên ngân hàng nhận chuyển khoản'),
  ('bank_account_number', '', 'Số tài khoản nhận chuyển khoản'),
  ('bank_account_holder', '', 'Chủ tài khoản nhận chuyển khoản'),
  ('bank_transfer_content', 'THANH TOAN {order_id}', 'Nội dung chuyển khoản; hỗ trợ {order_id}'),
  ('bank_qr_image_path', '', 'Ảnh QR chuyển khoản trong products_image')
ON DUPLICATE KEY UPDATE note = VALUES(note);
```

Apply migration once to current database after tests pass.

- [ ] **Step 4: Implement allowlisted settings service**

Only these keys are writable:

```php
private const EDITABLE_KEYS = [
    'store_name', 'store_phone', 'zalo_link', 'free_ship_threshold',
    'default_shipping_zone_id', 'bank_name', 'bank_account_number',
    'bank_account_holder', 'bank_transfer_content', 'bank_qr_image_path',
];
```

Shipping-zone save and default changes run in one transaction. Setting a default first clears other defaults, then activates and defaults the selected zone.

- [ ] **Step 5: Add settings APIs**

```text
POST settings-update       settings.manage
POST shipping-zone-save    settings.manage
POST shipping-zone-active  settings.manage
POST shipping-zone-default settings.manage
```

- [ ] **Step 6: Build settings forms**

Split page into:

- Store/contact.
- Payment/bank/QR.
- Shipping zones.

Do not expose arbitrary key editing.

- [ ] **Step 7: Verify and apply migration**

```powershell
C:\xampp\php\php.exe tests\AdminSettingsServiceTest.php
Get-Content -Raw database\migrations\20260620_admin_operations.sql |
  & 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3306 -u root dac_san_nha_dan
```

Expected: tests pass and five payment keys exist in production DB without changing existing values.

- [ ] **Step 8: Commit**

```powershell
git add database/database.sql database/migrations/20260620_admin_operations.sql app/Repositories/AdminSettingsRepository.php app/Services/AdminSettingsService.php admin/api/index.php admin/index.php views/admin/settings.php public/assets/js/admin.js tests/AdminSettingsServiceTest.php
git commit -m "feat: add payment and shipping settings"
```

### Task 9: Add owner-only admin user management

**Files:**

- Modify: `app/Repositories/AdminUserRepository.php`
- Create: `app/Services/AdminUserService.php`
- Modify: `admin/api/index.php`
- Modify: `admin/index.php`
- Create: `views/admin/admin-users.php`
- Modify: `views/admin/layout.php`
- Modify: `public/assets/js/admin.js`
- Test: `tests/AdminUserServiceTest.php`

- [ ] **Step 1: Write failing user-management tests**

Test:

```php
assertOwnerCreatesStaffWithPasswordHash();
assertOwnerChangesRole();
assertOwnerResetsPassword();
assertCannotDeactivateLastActiveOwner();
assertCannotDemoteLastActiveOwner();
assertAdminRoleCannotCallOwnerEndpoints();
```

- [ ] **Step 2: Verify RED**

```powershell
C:\xampp\php\php.exe tests\AdminUserServiceTest.php
```

- [ ] **Step 3: Add repository methods**

```php
listUsers(): array
findByIdForUpdate(int $adminId): ?array
usernameExists(string $username, ?int $exceptAdminId = null): bool
create(array $user): int
updateProfile(int $adminId, string $fullName, string $role, bool $active): void
updatePassword(int $adminId, string $hash): void
countActiveOwners(): int
```

- [ ] **Step 4: Implement service safeguards**

- Username: 3–80 characters, letters/numbers/`._-`.
- Password: minimum 10 characters.
- Roles restricted to owner/admin/staff.
- Hash using `password_hash(PASSWORD_DEFAULT)`.
- Lock target user and count active owners in the same transaction before demote/deactivate.

- [ ] **Step 5: Add owner-only page and APIs**

```text
GET  admin-users
POST admin-user-create
POST admin-user-update
POST admin-user-password
```

Every endpoint calls `require($role, 'admin_users.manage')`.

- [ ] **Step 6: Verify GREEN**

```powershell
C:\xampp\php\php.exe tests\AdminUserServiceTest.php
```

Login as owner and verify the page. Login as a temporary staff test account and verify page/API return 403, then remove the temporary test account.

- [ ] **Step 7: Commit**

```powershell
git add app/Repositories/AdminUserRepository.php app/Services/AdminUserService.php admin/api/index.php admin/index.php views/admin/admin-users.php views/admin/layout.php public/assets/js/admin.js tests/AdminUserServiceTest.php
git commit -m "feat: add owner-only admin user management"
```

### Task 10: Add pagination, labels, responsive behavior and dashboard links

**Files:**

- Modify: `app/Repositories/AdminDashboardRepository.php`
- Modify: `app/Services/AdminService.php`
- Modify: `views/admin/dashboard.php`
- Modify: `views/admin/orders.php`
- Modify: `views/admin/products.php`
- Modify: `views/admin/inventory.php`
- Modify: `views/admin/purchase-plans.php`
- Modify: `public/assets/css/admin.css`
- Modify: `public/assets/js/admin.js`
- Test: `tests/AdminApiSmokeTest.php`

- [ ] **Step 1: Write failing list contract tests**

Assert every list response includes:

```php
[
    'items' => [],
    'pagination' => [
        'page' => 1,
        'per_page' => 50,
        'total' => 0,
        'total_pages' => 0,
    ],
    'filters' => [],
]
```

Also assert Vietnamese status labels are provided centrally rather than duplicated in views.

- [ ] **Step 2: Verify RED**

```powershell
C:\xampp\php\php.exe tests\AdminApiSmokeTest.php --case=list-contracts
```

- [ ] **Step 3: Implement bounded pagination**

- `page >= 1`
- `per_page` allowed 20, 50, 100; default 50; max 100.
- Use separate `COUNT(*)` queries matching each filter.
- Use integer-interpolated `LIMIT` and `OFFSET` only after bounds validation.

- [ ] **Step 4: Add dashboard deep links and responsive tables**

Metric cards link to filtered pages. On mobile:

- Sidebar becomes a horizontal/compact navigation.
- Page actions wrap.
- Forms become one column.
- Tables remain horizontally scrollable.
- Invoice remains readable at 360 px.

- [ ] **Step 5: Verify GREEN**

```powershell
C:\xampp\php\php.exe tests\AdminApiSmokeTest.php --case=list-contracts
```

- [ ] **Step 6: Commit**

```powershell
git add app/Repositories/AdminDashboardRepository.php app/Services/AdminService.php views/admin/dashboard.php views/admin/orders.php views/admin/products.php views/admin/inventory.php views/admin/purchase-plans.php public/assets/css/admin.css public/assets/js/admin.js tests/AdminApiSmokeTest.php
git commit -m "feat: polish admin lists and responsive navigation"
```

### Task 11: Full verification on XAMPP and artifact cleanup

**Files:**

- Modify: `README.md`
- Verify only: all changed PHP/JS/CSS/views

- [ ] **Step 1: Run all retained PHP regression tests**

```powershell
$env:DB_DATABASE='dac_san_nha_dan_test'
Get-ChildItem tests -Filter *Test.php |
  ForEach-Object { C:\xampp\php\php.exe $_.FullName }
```

Expected: every test exits 0 with a pass message.

- [ ] **Step 2: Run syntax and static checks**

```powershell
Get-ChildItem . -Recurse -Filter *.php |
  ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
git diff --check
```

Expected: no syntax errors and no whitespace errors.

- [ ] **Step 3: Run API smoke checks**

Verify:

- unauthenticated mutation returns 401;
- staff forbidden endpoints return 403;
- missing CSRF returns 419;
- invalid ID/payload returns 422;
- missing object returns 404;
- valid detail endpoints return 200.

- [ ] **Step 4: Run temporary Playwright QA outside repository**

Browser plugin is unavailable, so create a temporary script under `$env:TEMP`, not the repo. Test:

```text
login
→ order list
→ order invoice detail
→ print media screenshot
→ product detail/form
→ inventory lot detail
→ PO detail
→ settings
→ owner users
```

Viewports:

- 1440×900
- 390×844

Capture console errors, page errors and screenshots under `$env:TEMP\dsnd-admin-qa`.

- [ ] **Step 5: Validate transaction invariants**

Run SQL checks:

```sql
SELECT COUNT(*) FROM inventory_lots WHERE qty_base_on_hand < 0;
SELECT COUNT(*) FROM inventory_lots WHERE qty_base_reserved > qty_base_on_hand;
SELECT product_id, SUM(is_base_unit = 1 AND is_active = 1) AS bases
FROM product_uoms GROUP BY product_id HAVING bases <> 1;
SELECT product_id, SUM(is_default = 1 AND is_active = 1) AS defaults
FROM product_uoms GROUP BY product_id HAVING defaults > 1;
SELECT COUNT(*) FROM shipping_zones WHERE is_default = 1 AND is_active = 1;
```

Expected: first four queries return zero violations; final query returns exactly 1.

- [ ] **Step 6: Clean temporary test state**

1. Delete all test DB rows with explicit `TEST_` IDs using table-order-aware SQL.
2. Delete only generated `TEST_` upload files whose resolved paths are inside `products_image`.
3. Remove `$env:TEMP\dsnd-admin-qa`.
4. Drop `dac_san_nha_dan_test`.
5. Confirm repository contains no screenshots, traces, Playwright scripts, test uploads or node dependencies.

- [ ] **Step 7: Update README**

Document:

- admin URL and roles;
- payment/shipping setup;
- product and image management;
- inventory manual operations;
- PO workflow;
- test DB command;
- migration command.

- [ ] **Step 8: Final repository audit**

```powershell
git status --short
git diff --stat HEAD
rg --files | rg "playwright|screenshot|trace|TEST_|node_modules|package-lock"
```

Expected: only intended source/docs/test changes remain; the final `rg` finds no QA artifacts.

- [ ] **Step 9: Commit**

```powershell
git add README.md
git commit -m "docs: document completed admin operations"
```

## Completion checklist

- [ ] Order detail renders a complete printable invoice with payment data and QR.
- [ ] Order transitions obey the state machine and permissions.
- [ ] Product create/update/active works with immutable IDs and no deletion path.
- [ ] UOM invariants are enforced.
- [ ] Product and QR uploads validate MIME, size and path.
- [ ] Manual receipts and signed lot adjustments are transactional and audited.
- [ ] PO preview/create/detail/receive/cancel works.
- [ ] Store, payment and shipping-zone settings are editable.
- [ ] Owner/admin/staff permissions are enforced server-side.
- [ ] Lists are paginated and responsive.
- [ ] All PHP tests, syntax checks, API smoke checks and browser flows pass.
- [ ] Test database, test rows, temporary uploads, screenshots and scripts are removed.
