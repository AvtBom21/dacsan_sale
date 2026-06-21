# Đặc Sản Nhà Dân

Source chính thức nằm tại:

```text
C:\xampp\htdocs\Dacsan
```

## Chạy bằng XAMPP

1. Start Apache và MySQL trong XAMPP Control Panel.
2. Mở storefront:

```text
http://localhost/Dacsan/
```

3. Mở admin:

```text
http://localhost/Dacsan/admin
```

Tài khoản local được seed sẵn:

```text
admin / admin123
```

Đổi mật khẩu này ngay trước khi dùng ngoài môi trường local.

Entrypoint runtime:

- `public/index.php`: storefront
- `public/api/index.php`: public API
- `admin/index.php`: admin UI
- `admin/api/index.php`: admin API

`.htaccess` ở root đã route `/Dacsan/`, `/Dacsan/api`, `/Dacsan/admin`, `/Dacsan/admin/api`, và `/Dacsan/assets/*` về đúng entrypoint.

Nếu chạy bằng PHP built-in server thay cho Apache, phải dùng router:

```powershell
cd C:\xampp\htdocs\Dacsan
C:\xampp\php\php.exe -S 127.0.0.1:8765 router.php
```

## Chức năng admin

- `owner`: toàn quyền, gồm quản lý tài khoản quản trị.
- `admin`: quản lý đơn hàng, sản phẩm, kho, purchase plan và cấu hình cửa hàng.
- `staff`: xử lý đơn hàng và purchase plan theo quyền giới hạn.
- Đơn hàng: xem chi tiết, chuyển trạng thái, hủy, in hóa đơn và thông tin chuyển khoản.
- Sản phẩm: tạo/sửa/ngừng bán, quản lý UOM, giá và ảnh; không xóa vật lý dữ liệu lịch sử.
- Kho: nhập thủ công, điều chỉnh tồn có lịch sử movement và kiểm tra số lượng reserved.
- Purchase plan: xem trước nhu cầu, tạo PO, nhận một phần/toàn bộ, hủy và sao chép.
- Cài đặt: thông tin cửa hàng, ngân hàng/QR và vùng giao hàng.

## Database

Database mặc định:

- DB: `dac_san_nha_dan`
- User: `root`
- Password: rỗng
- Host: `127.0.0.1`
- Port: `3306`

Import lại database khi cần:

```powershell
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root < C:\xampp\htdocs\Dacsan\database\database.sql
```

Với database đã tồn tại, áp dụng migration admin mà không import lại toàn bộ seed:

```powershell
Get-Content C:\xampp\htdocs\Dacsan\database\migrations\20260620_admin_operations.sql |
  C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root dac_san_nha_dan
```

Config nằm ở `config/database.php` và có thể override bằng:

- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

## Thư mục runtime

- `app`: core, service, repository
- `admin`: admin entrypoint
- `config`: cấu hình app/database/session
- `database`: schema baseline
- `products_image`: ảnh sản phẩm được database tham chiếu
- `public`: storefront/public assets/API
- `storage`: log và dữ liệu runtime
- `views`: template PHP

## Kiểm tra nhanh

Syntax check toàn bộ PHP:

```powershell
Get-ChildItem C:\xampp\htdocs\Dacsan -Recurse -Filter *.php |
  ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```

Health check API:

```powershell
Invoke-WebRequest -UseBasicParsing http://localhost/Dacsan/api?action=db-health
```
