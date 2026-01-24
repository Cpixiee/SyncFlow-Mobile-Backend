# 🪟 Windows Server Deployment Guide - SyncFlow API

Panduan lengkap untuk deploy SyncFlow API di Windows Server dengan automation script.

---

## 📋 Daftar Isi

1. [Persyaratan Sistem](#persyaratan-sistem)
2. [Instalasi Otomatis](#instalasi-otomatis)
3. [Instalasi Manual](#instalasi-manual)
4. [Konfigurasi Database](#konfigurasi-database)
5. [Konfigurasi Web Server](#konfigurasi-web-server)
6. [Testing & Verifikasi](#testing--verifikasi)
7. [Troubleshooting](#troubleshooting)

---

## 🖥️ Persyaratan Sistem

### Minimum Requirements

- **OS**: Windows Server 2016 atau lebih baru
- **PHP**: 8.1 atau lebih tinggi
- **Composer**: Versi terbaru
- **Database**: MySQL 5.7+ atau MariaDB 10.3+
- **RAM**: Minimum 2GB (disarankan 4GB+)
- **Storage**: Minimum 5GB free space

### Software yang Diperlukan

1. **PHP 8.1+** dengan ekstensi:
   - `mysqli`
   - `pdo_mysql`
   - `mbstring`
   - `openssl`
   - `curl`
   - `fileinfo`
   - `gd`
   - `zip`
   - `xml`
   - `json`

2. **Composer** (PHP Package Manager)

3. **MySQL/MariaDB** atau database server lainnya

4. **Web Server** (opsional):
   - IIS dengan URL Rewrite module
   - Apache
   - Atau gunakan PHP built-in server untuk development

---

## 🚀 Instalasi Otomatis

### Metode 1: Menggunakan Automation Script (Recommended)

Script PowerShell akan otomatis:
- ✅ Mengecek dan menginstall dependencies
- ✅ Setup environment configuration
- ✅ Install Composer packages
- ✅ Run database migrations
- ✅ Run database seeders
- ✅ Optimize Laravel untuk production
- ✅ Setup storage directories

#### Langkah-langkah:

1. **Buka PowerShell sebagai Administrator**

2. **Jalankan script automation:**
   ```powershell
   cd C:\path\to\SyncFlow
   .\setup-deploy.ps1
   ```

3. **Ikuti instruksi di layar:**
   - Script akan mengecek apakah PHP, Composer, dan MySQL sudah terinstall
   - Jika belum, script akan memberikan panduan instalasi
   - Anda akan diminta untuk mengkonfigurasi file `.env`
   - Script akan otomatis menjalankan semua setup yang diperlukan

4. **Setelah selesai, server siap digunakan!**

---

## 🔧 Instalasi Manual

Jika Anda lebih suka melakukan instalasi manual atau automation script tidak berjalan:

### Step 1: Install PHP

1. Download PHP 8.1+ dari: https://windows.php.net/download/
2. Extract ke `C:\php`
3. Copy `php.ini-development` menjadi `php.ini`
4. Edit `php.ini` dan enable ekstensi berikut:
   ```ini
   extension=mysqli
   extension=pdo_mysql
   extension=mbstring
   extension=openssl
   extension=curl
   extension=fileinfo
   extension=gd
   extension=zip
   ```
5. Tambahkan `C:\php` ke PATH environment variable

### Step 2: Install Composer

1. Download Composer dari: https://getcomposer.org/download/
2. Install Composer (akan otomatis detect PHP)
3. Atau download `composer.phar` dan simpan di folder project

### Step 3: Install MySQL/MariaDB

**Opsi A: Menggunakan XAMPP**
- Download XAMPP: https://www.apachefriends.org/
- Install dan start MySQL service

**Opsi B: Menggunakan Laragon**
- Download Laragon: https://laragon.org/
- Install dan start MySQL service

**Opsi C: Standalone MySQL**
- Download MySQL: https://dev.mysql.com/downloads/mysql/
- Install dan configure MySQL service

### Step 4: Clone/Download Project

```powershell
cd C:\
git clone <repository-url> SyncFlow
cd SyncFlow
```

### Step 5: Setup Environment

```powershell
# Copy environment file
copy .env.example .env

# Edit .env file dengan text editor
notepad .env
```

Konfigurasi minimal di `.env`:
```env
APP_NAME=SyncFlow
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-server-ip:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=syncflow
DB_USERNAME=root
DB_PASSWORD=your_password

JWT_SECRET=
JWT_TTL=60
```

### Step 6: Install Dependencies

```powershell
# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Ensure required packages
composer require nxp/math-executor --no-interaction
composer require phpoffice/phpspreadsheet --no-interaction
composer require dompdf/dompdf --no-interaction
composer require barryvdh/laravel-dompdf --no-interaction
```

### Step 7: Generate Keys

```powershell
# Generate application key
php artisan key:generate

# Generate JWT secret
php artisan jwt:secret
```

### Step 8: Setup Database

```powershell
# Create database (via MySQL command line atau phpMyAdmin)
mysql -u root -p
CREATE DATABASE syncflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;

# Run migrations
php artisan migrate --force
```

### Step 9: Run Seeders

```powershell
# Run all seeders (REQUIRED)
php artisan db:seed --class=QuarterSeeder --force
php artisan db:seed --class=ProductCategorySeeder --force
php artisan db:seed --class=MeasurementInstrumentSeeder --force
php artisan db:seed --class=ToolSeeder --force
php artisan db:seed --class=SuperAdminSeeder --force
php artisan db:seed --class=LoginUserSeeder --force

# Or run all at once
php artisan db:seed --force
```

### Step 10: Activate Quarter

```powershell
# Activate Q4 2024 (or any available quarter)
php artisan quarter:activate

# Or via tinker
php artisan tinker
>>> $quarter = App\Models\Quarter::where('year', 2024)->where('name', 'Q4')->first();
>>> $quarter->setAsActive();
```

### Step 11: Optimize Laravel

```powershell
# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Cache for production
php artisan config:cache
php artisan route:cache
```

### Step 12: Setup Storage

```powershell
# Create storage link
php artisan storage:link

# Create required directories
mkdir storage\app\private\reports\master_files
mkdir storage\app\temp
```

---

## 🗄️ Konfigurasi Database

### Membuat Database

```sql
CREATE DATABASE syncflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'syncflow_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON syncflow.* TO 'syncflow_user'@'localhost';
FLUSH PRIVILEGES;
```

### Update .env

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=syncflow
DB_USERNAME=syncflow_user
DB_PASSWORD=your_secure_password
```

---

## 🌐 Konfigurasi Web Server

### Opsi 1: PHP Built-in Server (Development)

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

Server akan berjalan di: `http://localhost:8000`

### Opsi 2: IIS (Production)

1. **Install IIS dan URL Rewrite Module**
   - Install IIS dari Windows Features
   - Download URL Rewrite: https://www.iis.net/downloads/microsoft/url-rewrite

2. **Create Website di IIS**
   - Open IIS Manager
   - Right-click Sites → Add Website
   - Site name: `SyncFlow API`
   - Physical path: `C:\path\to\SyncFlow\public`
   - Binding: Port 80 (atau port lain)

3. **Configure web.config**
   File `web.config` sudah ada di folder `public`, pastikan isinya:
   ```xml
   <?xml version="1.0" encoding="UTF-8"?>
   <configuration>
       <system.webServer>
           <rewrite>
               <rules>
                   <rule name="Imported Rule 1" stopProcessing="true">
                       <match url="^(.*)/$" ignoreCase="false" />
                       <conditions>
                           <add input="{REQUEST_FILENAME}" matchType="IsDirectory" ignoreCase="false" negate="true" />
                       </conditions>
                       <action type="Redirect" redirectType="Permanent" url="/{R:1}" />
                   </rule>
                   <rule name="Imported Rule 2" stopProcessing="true">
                       <match url="^" ignoreCase="false" />
                       <conditions>
                           <add input="{REQUEST_FILENAME}" matchType="IsDirectory" ignoreCase="false" negate="true" />
                           <add input="{REQUEST_FILENAME}" matchType="IsFile" ignoreCase="false" negate="true" />
                       </conditions>
                       <action type="Rewrite" url="index.php" />
                   </rule>
               </rules>
           </rewrite>
       </system.webServer>
   </configuration>
   ```

4. **Set Permissions**
   ```powershell
   # Give IIS_IUSRS full access to storage and cache
   icacls "C:\path\to\SyncFlow\storage" /grant IIS_IUSRS:(OI)(CI)F /T
   icacls "C:\path\to\SyncFlow\bootstrap\cache" /grant IIS_IUSRS:(OI)(CI)F /T
   ```

### Opsi 3: Apache (Production)

1. **Install Apache** (via XAMPP atau standalone)

2. **Enable mod_rewrite**

3. **Configure Virtual Host**
   ```apache
   <VirtualHost *:80>
       ServerName syncflow.local
       DocumentRoot "C:/path/to/SyncFlow/public"
       
       <Directory "C:/path/to/SyncFlow/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

4. **Create .htaccess** (sudah ada di folder `public`)

---

## ✅ Testing & Verifikasi

### 1. Test API Endpoint

```powershell
# Test login endpoint
curl -X POST http://localhost:8000/api/v1/login `
  -H "Content-Type: application/json" `
  -d '{"username":"superadmin","password":"admin#1234"}'
```

### 2. Test Health Check

```powershell
# Check if API is responding
curl http://localhost:8000/api/v1/login
```

### 3. Default Credentials

Setelah seeder berjalan, default credentials:
- **Username**: `superadmin`
- **Password**: `admin#1234`

⚠️ **PENTING**: Ganti password default di production!

---

## 🔍 Troubleshooting

### Error: "Class 'PDO' not found"

**Solusi:**
- Enable `extension=pdo_mysql` di `php.ini`
- Restart web server

### Error: "No application encryption key has been specified"

**Solusi:**
```powershell
php artisan key:generate
```

### Error: "SQLSTATE[HY000] [2002] Connection refused"

**Solusi:**
- Pastikan MySQL service berjalan
- Cek `DB_HOST` di `.env` (gunakan `127.0.0.1` bukan `localhost`)
- Cek firewall tidak memblokir port 3306

### Error: "The stream or file could not be opened"

**Solusi:**
- Set permissions untuk folder `storage` dan `bootstrap/cache`
- Pastikan web server user memiliki write access

### Error: "Route [login] not defined"

**Solusi:**
```powershell
php artisan route:clear
php artisan route:cache
php artisan config:clear
php artisan config:cache
```

### Error: "No active quarter"

**Solusi:**
```powershell
# Activate a quarter
php artisan quarter:activate

# Or via tinker
php artisan tinker
>>> App\Models\Quarter::first()->setAsActive();
```

### Composer Memory Limit Error

**Solusi:**
```powershell
# Increase PHP memory limit
php -d memory_limit=512M composer install
```

### Permission Denied pada Storage

**Solusi (Windows):**
```powershell
# Give full access to IIS user
icacls "storage" /grant IIS_IUSRS:(OI)(CI)F /T
icacls "bootstrap\cache" /grant IIS_IUSRS:(OI)(CI)F /T
```

---

## 📝 Checklist Deployment

Gunakan checklist ini untuk memastikan semua langkah sudah dilakukan:

- [ ] PHP 8.1+ terinstall dengan semua ekstensi yang diperlukan
- [ ] Composer terinstall dan bisa dijalankan
- [ ] MySQL/MariaDB terinstall dan berjalan
- [ ] Database `syncflow` sudah dibuat
- [ ] File `.env` sudah dikonfigurasi dengan benar
- [ ] `APP_KEY` sudah di-generate
- [ ] `JWT_SECRET` sudah di-generate
- [ ] Composer dependencies sudah diinstall
- [ ] Migrations sudah dijalankan
- [ ] Seeders sudah dijalankan (semua 6 seeder)
- [ ] Quarter sudah diaktifkan
- [ ] Storage link sudah dibuat
- [ ] Storage directories sudah dibuat
- [ ] Permissions sudah diset dengan benar
- [ ] Laravel sudah dioptimize (config:cache, route:cache)
- [ ] Web server sudah dikonfigurasi
- [ ] API bisa diakses dan merespon
- [ ] Login endpoint berfungsi dengan default credentials
- [ ] Password default sudah diganti

---

## 🔐 Security Checklist

- [ ] `APP_DEBUG=false` di production
- [ ] `APP_ENV=production` di production
- [ ] Password database yang kuat
- [ ] Password superadmin sudah diganti
- [ ] JWT secret yang kuat dan unik
- [ ] SSL/HTTPS dikonfigurasi (untuk production)
- [ ] Firewall dikonfigurasi dengan benar
- [ ] File `.env` tidak di-commit ke Git
- [ ] Storage permissions sudah diset dengan benar

---

## 📞 Support

Jika mengalami masalah:

1. Cek log file: `storage\logs\laravel.log`
2. Cek error di browser/Postman
3. Pastikan semua requirements sudah terpenuhi
4. Cek dokumentasi Laravel: https://laravel.com/docs

---

## 🎉 Selesai!

Setelah semua langkah selesai, API SyncFlow sudah siap digunakan di Windows Server!

**API Base URL**: `http://your-server-ip:8000/api/v1`

**Default SuperAdmin Login**:
- Username: `superadmin`
- Password: `admin#1234`

⚠️ **Jangan lupa ganti password default di production!**
