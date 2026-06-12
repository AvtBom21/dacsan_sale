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

Entrypoint runtime:

- `public/index.php`: storefront
- `public/api/index.php`: public API
- `admin/index.php`: admin UI
- `admin/api/index.php`: admin API

`.htaccess` ở root đã route `/Dacsan/`, `/Dacsan/api`, `/Dacsan/admin`, `/Dacsan/admin/api`, và `/Dacsan/assets/*` về đúng entrypoint.

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
