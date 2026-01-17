# 🚀 Windows Server - Quick Start Guide

Panduan cepat untuk menjalankan SyncFlow API di Windows Server.

## ⚡ Quick Start (5 Menit)

### 1. Jalankan Automation Script

```powershell
# Buka PowerShell sebagai Administrator
cd C:\path\to\SyncFlow
.\setup-deploy.ps1
```

Script akan otomatis melakukan semua setup yang diperlukan!

### 2. Konfigurasi Database

Edit file `.env` dan set database credentials:
```env
DB_HOST=127.0.0.1
DB_DATABASE=syncflow
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 3. Start Server

```powershell
php artisan serve
```

Server akan berjalan di: `http://localhost:8000`

## ✅ Checklist Cepat

- [ ] PHP 8.1+ terinstall
- [ ] Composer terinstall
- [ ] MySQL berjalan
- [ ] Database `syncflow` sudah dibuat
- [ ] File `.env` sudah dikonfigurasi
- [ ] Script `setup-deploy.ps1` sudah dijalankan
- [ ] Server bisa diakses

## 🔑 Default Credentials

Setelah setup selesai:
- **Username**: `superadmin`
- **Password**: `admin#1234`

⚠️ **Ganti password di production!**

## 📚 Dokumentasi Lengkap

Untuk panduan detail, lihat: [WINDOWS_DEPLOYMENT.md](WINDOWS_DEPLOYMENT.md)

## 🆘 Troubleshooting

**Error: PHP not found**
- Install PHP 8.1+ dari https://windows.php.net/download/
- Tambahkan ke PATH

**Error: Composer not found**
- Install dari https://getcomposer.org/download/

**Error: Database connection failed**
- Pastikan MySQL service berjalan
- Cek credentials di `.env`

**Error: Permission denied**
- Jalankan PowerShell sebagai Administrator
- Set permissions: `icacls storage /grant IIS_IUSRS:(OI)(CI)F /T`

## 🎉 Selesai!

API siap digunakan di: `http://localhost:8000/api/v1`
