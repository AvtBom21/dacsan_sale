# Database Baseline

This folder contains the current core MySQL/MariaDB schema and seed baseline for the PHP modular rebuild.

## Files

- `database.sql`: current schema and seed baseline used by the app.
- `README.md`: database setup and verification notes.

## Create And Import Database

The SQL file creates and selects the database:

```sql
CREATE DATABASE IF NOT EXISTS dac_san_nha_dan
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dac_san_nha_dan;
```

Important: `database.sql` drops the current baseline tables and views before recreating them. Back up any local data before importing.

## Import With phpMyAdmin

1. Start Apache and MySQL from XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin`.
3. Open the Import tab.
4. Choose `C:\xampp\htdocs\Dacsan\database\database.sql`.
5. Keep charset as `utf8mb4` when available.
6. Click Import.

## Import With Command Line

From PowerShell:

```powershell
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root < C:\xampp\htdocs\Dacsan\database\database.sql
```

If your MySQL user has a password:

```powershell
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root -p < C:\xampp\htdocs\Dacsan\database\database.sql
```

## App Database Config

The app reads database settings from `config/database.php`.

Default local settings:

```php
'host' => '127.0.0.1',
'port' => 3306,
'database' => 'dac_san_nha_dan',
'username' => 'root',
'password' => '',
'charset' => 'utf8mb4',
```

Do not hard-code production passwords. Use environment variables such as `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` for non-local environments.

## Verify Core Tables And Views

Expected core database objects:

- 18 base tables
- 2 views

Verify manually in MySQL:

```sql
SELECT COUNT(*) AS table_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'dac_san_nha_dan'
  AND TABLE_TYPE = 'BASE TABLE';

SELECT COUNT(*) AS view_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'dac_san_nha_dan'
  AND TABLE_TYPE = 'VIEW';

SHOW FULL TABLES FROM dac_san_nha_dan;
```

## API Health Check

With the PHP built-in server:

```powershell
cd C:\xampp\htdocs\Dacsan
C:\xampp\php\php.exe -S 127.0.0.1:8765 router.php
```

Then call:

```powershell
Invoke-WebRequest -UseBasicParsing http://127.0.0.1:8765/api?action=db-health
```

## If XAMPP MySQL Cannot Start

Do not modify or delete the MySQL data folder without a backup.

Check these first:

1. Open XAMPP Control Panel and click Logs next to MySQL.
2. Check `C:\xampp\mysql\data\mysql_error.log`.
3. Check whether port `3306` is already used:

```powershell
netstat -ano | findstr :3306
```

4. If another MySQL service is using the port, stop that service or change the XAMPP MySQL port.
5. Re-run:

```powershell
C:\xampp\mysql\bin\mysqladmin.exe -h 127.0.0.1 -P 3306 -u root ping
```
