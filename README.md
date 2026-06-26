# 🏠 Management Kost

Sistem Manajemen Kost berbasis **PHP Native** dan **MySQL** yang dirancang untuk membantu pemilik kost dalam mengelola data kamar, penghuni, pembayaran, tagihan, serta notifikasi secara efisien.

---

## 📌 Fitur Utama

- 🔐 Login Multi User
- 👤 Manajemen Data Penghuni
- 🏠 Manajemen Data Kost
- 🚪 Manajemen Kamar
- 💳 Pembayaran Tagihan
- 🧾 Riwayat Transaksi
- 📢 Notifikasi Penghuni
- 📝 Komplain Penghuni
- 📊 Dashboard Admin
- 📷 Upload Foto/Aset
- 💰 Integrasi Midtrans Payment Gateway
- 📅 Generate Tagihan Otomatis

---

## 🛠️ Teknologi

| Teknologi | Keterangan |
|-----------|------------|
| PHP Native | Backend |
| MySQL | Database |
| HTML5 | Frontend |
| CSS3 | Styling |
| JavaScript | Interaksi |
| Bootstrap | UI Framework |
| Midtrans | Payment Gateway |

---

## 📂 Struktur Project

```text
ManagementKost/
│
├── uploads/
├── dashboard.php
├── index.php
├── login.php
├── logout.php
├── kost.php
├── kamar.php
├── penghuni.php
├── tagihan.php
├── proses_bayar.php
├── profile.php
├── navbar.php
├── config.php
├── .gitignore
└── README.md
```

---

## ⚙️ Instalasi

### 1. Clone Repository

```bash
git clone https://github.com/ApipppX/kost-management-php-native.git
```

Masuk ke folder project

```bash
cd kost-management-php-native
```

---

### 2. Pindahkan ke Folder XAMPP

Salin project ke:

```
C:\xampp\htdocs\
```

Sehingga menjadi:

```
C:\xampp\htdocs\ManagementKost
```

---

### 3. Import Database

- Buka **phpMyAdmin**
- Buat database baru

```
db_manajemen_kost
```

- Import file SQL database.

---

### 4. Konfigurasi Database

Buat file

```
koneksi.php
```

Contoh konfigurasi:

```php
<?php

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_manajemen_kost";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi Database Gagal");
}
```

---

### 5. Konfigurasi Midtrans

Tambahkan konfigurasi berikut pada `koneksi.php`

```php
define('MIDTRANS_SERVER_KEY', 'YOUR_SERVER_KEY');
define('MIDTRANS_IS_PRODUCTION', false);
```

> **Catatan**
>
> Jangan pernah mengunggah **Server Key Midtrans**, API Key, atau password database ke repository GitHub.

---

### 6. Jalankan Project

Aktifkan:

- Apache
- MySQL

Kemudian buka browser

```
http://localhost/ManagementKost
```

---

## 📁 File yang Tidak Diunggah

Project menggunakan `.gitignore` untuk menghindari upload file sensitif seperti:

```
koneksi.php
auth-google-callback.php
uploads/
.vscode/
.idea/
```

---

## 🔒 Keamanan

Repository ini **tidak menyimpan**:

- Password Database
- Midtrans Production Server Key
- OAuth Client Secret
- API Key

Seluruh data sensitif harus disimpan pada file konfigurasi lokal yang tidak diunggah ke GitHub.

---

## 🚀 Pengembangan Selanjutnya

- Email Notification
- WhatsApp Notification
- QR Code Check-in
- Dashboard Statistik
- Export PDF
- Export Excel
- Multi Role User
- Backup Database
- Laporan Keuangan

---

## 👨‍💻 Developer

**Muhammad Afif**

GitHub:
https://github.com/ApipppX

---

## 📄 Lisensi

Project ini dibuat untuk tujuan pembelajaran dan pengembangan sistem manajemen kost.

Silakan digunakan, dimodifikasi, dan dikembangkan sesuai kebutuhan.
