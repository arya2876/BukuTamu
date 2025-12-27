# 📚 Buku Tamu Digital (Guest Book)

Aplikasi Buku Tamu Digital dengan fitur CRUD lengkap, QR Code unik untuk setiap tamu, dan sistem verifikasi kehadiran.

## 🌟 Fitur

- ✅ **Multi-Page Layout** - Halaman terpisah untuk setiap fungsi
- ✅ **QR Code Generator** - Setiap tamu mendapat QR Code unik
- ✅ **QR Code Scanner** - Scan untuk verifikasi kehadiran
- ✅ **Check-In System** - Catat kehadiran tamu
- ✅ **CRUD Lengkap** - Create, Read, Update, Delete
- ✅ **Search & Filter** - Cari tamu dengan mudah
- ✅ **Dark Theme Modern** - Glassmorphism design
- ✅ **Responsive** - Desktop & Mobile

---

## 📱 Halaman

| Halaman | URL | Fungsi |
|---------|-----|--------|
| **Home** | `index.html` | Landing page dengan statistik |
| **Input** | `input.html` | Tambah tamu + Generate QR Code |
| **Kelola** | `manage.html` | Daftar tamu, search, edit, hapus |
| **Scan** | `scan.html` | Scanner QR untuk verifikasi |

---

## 🚀 Instalasi

### 1. Start XAMPP
Buka XAMPP Control Panel dan start:
- ✅ **Apache**
- ✅ **MySQL**

### 2. Setup Database
```
http://localhost/Projek14%20PWD/api/setup.php
```

### 3. Akses Aplikasi
```
http://localhost/Projek14%20PWD/index.html
```

---

## 📁 Struktur Project

```
Projek14 PWD/
├── index.html          # Landing page
├── input.html          # Form input + QR Code
├── manage.html         # Kelola daftar tamu
├── scan.html           # Scanner QR Code
├── README.md
├── css/
│   └── style.css       # Custom styling
├── js/
│   ├── shared.js       # Shared functions
│   ├── input.js        # Input page logic
│   ├── manage.js       # Manage page logic
│   └── scan.js         # Scanner logic
└── api/
    ├── config.php      # Database config
    ├── guests.php      # API endpoints
    └── setup.php       # Database setup
```

---

## 🔐 Format QR Code

```
BUKUTAMU-{guest_id}-{unique_token}
```

Contoh: `BUKUTAMU-5-a3f8b2c1d4e5f6g7`

---

## 📡 API Endpoints

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/guests.php` | Daftar semua tamu |
| GET | `?action=stats` | Statistik tamu |
| GET | `?action=verify&code=XXX` | Verifikasi QR |
| POST | `/api/guests.php` | Tambah tamu baru |
| POST | `?action=checkin` | Check-in tamu |
| PUT | `/api/guests.php` | Update data tamu |
| DELETE | `?id=1` | Hapus tamu |

---

## 🎨 Tech Stack

- HTML5, CSS3, JavaScript (ES6+)
- Bootstrap 5.3
- PHP 7.4+
- MySQL
- QRCode.js (Generate QR)
- html5-qrcode (Scan QR)

---

## 📝 License

MIT License
