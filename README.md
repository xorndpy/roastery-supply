# Roastery Supply — E-Commerce Mesin Kopi

> Tugas Akhir [kelompok 5] — []  
> Program Studi [sistem informasi] — [TI]  
> [Nama Universitas] — [2024]

Platform e-commerce untuk penjualan mesin kopi profesional (espresso machine, grinder, milk frother) dengan fitur **viewer 3D interaktif** untuk melihat detail produk.

---

## 📖 Deskripsi

Roastery Supply adalah aplikasi web e-commerce yang dirancang untuk memenuhi kebutuhan kedai kopi dalam membeli mesin kopi profesional. Aplikasi ini menyediakan pengalaman belanja yang modern dengan dukungan tampilan 3D untuk produk mesin kopi.

### Fitur Utama

**Customer:**

- Katalog produk dengan filter kategori, merek, harga
- Detail produk dengan **viewer 3D** (model-viewer)
- Keranjang belanja
- Checkout dengan alamat pengiriman
- Upload bukti pembayaran
- Riwayat pesanan + tracking resi
- Profil pengguna

**Admin:**

- Dashboard dengan statistik & chart penjualan
- Manajemen produk (CRUD, upload gambar + model 3D)
- Manajemen kategori & merek
- Manajemen pesanan (konfirmasi pembayaran → proses → kirim → selesai)
- Manajemen stok + peringatan stok menipis
- Manajemen pelanggan
- Laporan penjualan (export Excel/PDF)
- Manajemen user & role (RBAC)

---

## 🛠️ Tech Stack

| Layer          | Teknologi                        |
| -------------- | -------------------------------- |
| Backend        | Laravel 12 (PHP 8.4+)            |
| Template       | Blade                            |
| Styling        | Tailwind CSS                     |
| Interaktivitas | Alpine.js + Livewire 3           |
| Database       | MySQL 8                          |
| Auth           | Laravel Breeze + Sanctum         |
| Permission     | spatie/laravel-permission        |
| Activity Log   | spatie/laravel-activitylog       |
| 3D Viewer      | `<model-viewer>` (web component) |
| PDF            | barryvdh/laravel-dompdf          |
| Excel          | maatwebsite/excel                |
| Chart          | Chart.js                         |

---

## 📸 Screenshot

### Halaman Customer

**Home Page:**
![Home](docs/screenshots/home.png)

**Katalog Produk:**
![Katalog](docs/screenshots/katalog.png)

**Detail Produk dengan 3D Viewer:**
![Detail Produk](docs/screenshots/detail-produk.png)

**Keranjang Belanja:**
![Cart](docs/screenshots/cart.png)

**Checkout:**
![Checkout](docs/screenshots/checkout.png)

### Halaman Admin

**Dashboard Admin:**
![Dashboard Admin](docs/screenshots/admin-dashboard.png)

**Manajemen Produk:**
![Produk](docs/screenshots/admin-produk.png)

**Manajemen Pesanan:**
![Pesanan](docs/screenshots/admin-pesanan.png)

> _Catatan: Buat folder `docs/screenshots/` di root project, taruh screenshot di sana._

---

## 🚀 Cara Install

### Prasyarat

- PHP 8.4+
- Composer
- Node.js 18+ & npm
- MySQL 8+
- Laragon / XAMPP / Docker

### Langkah Instalasi

**1. Clone repository**

```bash
git clone https://github.com/[username]/roastery-supply.git
cd roastery-supply
```
