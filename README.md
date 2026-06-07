# Đặc Sản Nhà Dân

## Project chính thức

Source chính thức hiện nằm tại `C:\xampp\htdocs\Dacsan`.

Entrypoint storefront là:

```text
C:\xampp\htdocs\Dacsan\public\index.php
```

Root `index.php` cũ đã được loại khỏi runtime. Khi chạy bằng XAMPP subfolder, `.htaccess` ở root trỏ:

- `/Dacsan/` -> `public/index.php`
- `/Dacsan/api` -> `public/api/index.php`
- `/Dacsan/admin` -> `admin/index.php`
- `/Dacsan/admin/api` -> `admin/api/index.php`
- `/Dacsan/assets/*` -> `public/assets/*`

Không sửa root `index.php`; file đó không còn là entrypoint.

## Chạy local bằng XAMPP

1. Start Apache và MySQL trong XAMPP Control Panel.
2. Đảm bảo project nằm ở:

```text
C:\xampp\htdocs\Dacsan
```

3. Mở storefront:

```text
http://localhost/Dacsan/
```

Direct public URL cũng dùng được:

```text
http://localhost/Dacsan/public/index.php
```

4. Mở admin:

```text
http://localhost/Dacsan/admin
```

## Import database

Database mặc định là `dac_san_nha_dan`, user `root`, password rỗng.

Import bằng phpMyAdmin hoặc PowerShell:

```powershell
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root < C:\xampp\htdocs\Dacsan\database\database.sql
```

File seed gốc `dac_san_nha_dan_schema_seed.sql` vẫn được giữ ở root để tham chiếu.

## Cấu hình database

Config database nằm tại:

```text
C:\xampp\htdocs\Dacsan\config\database.php
```

Có thể override bằng environment variables:

- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

## Media landing page

Media cho 4 section chinh cua storefront nam tai:

```text
C:\xampp\htdocs\Dacsan\public\assets\media\sections
```

Mapping hien tai:

- `hero-highland.mp4`: section 1, hero video.
- `story-coast.mp4`: section 2, storytelling video.
- `gia-lai-cows.jpeg`: section 3, anh nen Gia Lai.
- `binh-dinh-underwater.jpeg`: section 4, anh nen Binh Dinh.
- `highland-origin.jpeg` va `binh-dinh-boats.jpeg`: poster/fallback/card image.

Media trung o root da duoc chuyen khoi runtime va backup tai:

```text
C:\xampp\htdocs\Dacsan\_asset_backup_20260607_0429
```

## Chạy smoke tests

```powershell
C:\xampp\php\php.exe tests\db_smoke.php
C:\xampp\php\php.exe tests\catalog_smoke.php
C:\xampp\php\php.exe tests\checkout_smoke.php
C:\xampp\php\php.exe tests\admin_smoke.php
C:\xampp\php\php.exe tests\storefront_smoke.php
```

Syntax check toàn bộ PHP:

```powershell
Get-ChildItem C:\xampp\htdocs\Dacsan -Recurse -Filter *.php |
  ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```

## Legacy source

Source monolithic cũ đã được backup tại:

```text
C:\xampp\htdocs\Dacsan\_legacy_backup_20260606_2103
```

Các file/thư mục cũ đã được đưa vào backup và không còn nằm trong runtime:

- `admin.php`
- `helpers.php`
- root `index.php`
- root `config`
- root `migrations`

Các thư mục/file được giữ lại ở root vì vẫn cần cho app hoặc dữ liệu:

- `products_image`: ảnh sản phẩm DB đang tham chiếu.
- `public/assets/media`: media dùng cho landing page mới.
- `database`: schema baseline của source modular.
- `dac_san_nha_dan_schema_seed.sql`: seed/schema gốc để đối chiếu.
- `storage`: storage runtime của app.
# dacsan_sale
