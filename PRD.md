### 4.4 User Journey per Role

**Guest:**
Home → Katalog → Detail Produk (3D) → Add to Cart → Register/Login

**Customer:**
Login → Dashboard akun → Riwayat Order → Lacak Resi → Ajukan Retur

**Staff:**
Login → Daftar Pesanan → Konfirmasi Bayar → Input Resi → Update Stok

**Admin:**
Login → Dashboard → Kelola Produk → Kelola Pesanan → Kelola Konten → Laporan

**Super Admin:**
Semua akses Admin + Kelola User + Kelola Role + Lihat Log

---

## 5. Business Rules

1. **Stok berkurang** saat status order = `dikonfirmasi` (bukan saat checkout)
2. **Voucher**: max 1 per order, tidak bisa digabung
3. **Retur**: max 7 hari setelah status `selesai`, approve manual admin
4. **Invoice PDF**: auto-generate saat order dibuat
5. **Peringatan stok menipis**: threshold per-produk (default 5)
6. **Pembayaran expired**: auto-cancel order setelah 24 jam
7. **Duplicate order**: dicegah dengan idempotency key saat checkout
8. **Harga**: snapshot harga di order_items (harga bisa berubah, order lama tetap)
9. **Voucher expired**: validasi ulang saat checkout, kasih notice
10. **Retur setelah 7 hari**: tolak otomatis

---

## 6. Fitur per Modul (dengan Acceptance Criteria)

### 6.1 Katalog

**User story**: Sebagai admin, saya bisa kelola produk dengan lengkap.

**Fitur:**

- [ ] CRUD Produk (nama, SKU, harga, deskripsi, gambar, model 3D)
- [ ] CRUD Kategori (nested, max 2 level)
- [ ] CRUD Merek
- [ ] Upload gambar produk (multi-image, max 5)
- [ ] Upload model 3D (.glb, max 10MB)
- [ ] Manajemen stok: riwayat masuk/keluar, peringatan menipis
- [ ] Import/export produk via Excel
- [ ] Search + filter (kategori, merek, harga, stok)
- [ ] Soft delete

**Acceptance Criteria:**

- Admin bisa tambah produk dalam < 2 menit
- Stok berkurang otomatis saat order dikonfirmasi
- Peringatan muncul saat stok <= threshold
- 3D viewer tampil di detail produk (lazy load)

---

### 6.2 Penjualan

**User story**: Sebagai staff, saya bisa kelola pesanan dari masuk sampai selesai.

**Fitur:**

- [ ] List pesanan + filter (status, tanggal, customer)
- [ ] Detail pesanan + timeline status
- [ ] Konfirmasi pembayaran (upload bukti / verifikasi manual)
- [ ] Input resi pengiriman + tracking
- [ ] Proses retur/refund (approve/reject manual)
- [ ] Generate invoice PDF
- [ ] Update status: menunggu_bayar → dikonfirmasi → diproses → dikirim → selesai/batal
- [ ] Auto-cancel order jika bayar expired 24 jam

**Status Order:**

| Status           | Trigger                                |
| ---------------- | -------------------------------------- |
| `menunggu_bayar` | Order dibuat                           |
| `dikonfirmasi`   | Pembayaran diverifikasi                |
| `diproses`       | Admin mulai proses                     |
| `dikirim`        | Resi diinput                           |
| `selesai`        | Customer terima                        |
| `batal`          | Dibatalkan customer/admin atau expired |

**Acceptance Criteria:**

- Status hanya bisa maju, tidak bisa mundur (kecuali batal)
- Stok berkurang saat `dikonfirmasi`
- Invoice PDF auto-generate
- Notifikasi email tiap perubahan status

---

### 6.3 Pelanggan

**Fitur:**

- [ ] List pelanggan + search
- [ ] Detail pelanggan + riwayat belanja
- [ ] Segmentasi (baru, aktif, dormant)
- [ ] Export data pelanggan

**Acceptance Criteria:**

- Admin bisa lihat total belanja per pelanggan
- Riwayat order tampil lengkap

---

### 6.4 Pemasaran

**Fitur:**

- [ ] CRUD Voucher (kode, tipe: %, nominal, min belanja, kuota, expired)
- [ ] CRUD Banner hero (drag-drop urutan, jadwal tayang)
- [ ] CRUD Testimoni (approve/reject)

**Acceptance Criteria:**

- Voucher max 1 per order
- Banner tampil sesuai jadwal
- Testimoni hanya tampil setelah di-approve

---

### 6.5 Tampilan Website (CMS)

**Fitur:**

- [ ] CRUD FAQ (kategori + item)
- [ ] CRUD Partner (logo, link)
- [ ] CRUD Cabang & peta (integrasi Google Maps, lat/long)
- [ ] Pengaturan umum (kontak, sosmed, logo, meta)
- [ ] Pesan masuk dari form kontak

**Acceptance Criteria:**

- Semua konten web bisa diubah dari panel (no hardcode)
- Peta tampil di halaman cabang
- Pesan kontak masuk ke dashboard

---

### 6.6 Laporan

**Fitur:**

- [ ] Penjualan harian/bulanan (chart)
- [ ] Produk terlaris
- [ ] Export Excel/PDF
- [ ] Filter by tanggal, kategori, status

**Acceptance Criteria:**

- Chart tampil < 2s
- Export Excel/PDF berhasil
- Data akurat sesuai database

---

### 6.7 Administrasi

**Fitur:**

- [ ] Kelola User (CRUD, assign role)
- [ ] Role & Hak Akses (matrix)
- [ ] Log Aktivitas (spatie/laravel-activitylog)
- [ ] Pesan Masuk (dari form kontak)

**Acceptance Criteria:**

- Hanya Super Admin bisa kelola user & role
- Log mencatat semua aksi admin
- Pesan masuk bisa ditandai sudah dibaca

---

## 7. Database Schema (ERD)

### Tabel Utama

**users**

- id, name, email, password, phone, email_verified_at, timestamps, soft_deletes

**roles** (spatie)

- id, name, guard_name, timestamps

**permissions** (spatie)

- id, name, guard_name, timestamps

**model_has_roles** (spatie)

- role_id, model_type, model_id

**model_has_permissions** (spatie)

- permission_id, model_type, model_id

**role_has_permissions** (spatie)

- permission_id, role_id

**categories**

- id, parent_id (nullable), name, slug, description, image, is_active, timestamps, soft_deletes

**brands**

- id, name, slug, logo, description, is_active, timestamps, soft_deletes

**products**

- id, category_id, brand_id, name, slug, sku, description, price, weight, stock, stock_threshold, model_3d, is_active, is_featured, timestamps, soft_deletes

**product_images**

- id, product_id, image, is_primary, sort_order, timestamps

**stock_movements**

- id, product_id, type (in/out/adjustment), quantity, reference_type (order/manual), reference_id, note, user_id, timestamps

**customers**

- id, user_id, name, email, phone, address, city, province, postal_code, timestamps, soft_deletes

**orders**

- id, order_number, customer_id, user_id (nullable), status, subtotal, discount, shipping_cost, total, voucher_id (nullable), note, timestamps, soft_deletes

**order_items**

- id, order_id, product_id, product_name, product_sku, price, quantity, subtotal, timestamps

**payments**

- id, order_id, method, amount, proof_image, status (pending/verified/rejected), verified_by, verified_at, note, timestamps

**shipments**

- id, order_id, courier, service, tracking_number, cost, status, shipped_at, delivered_at, timestamps

**returns**

- id, order_id, reason, status (pending/approved/rejected), refund_amount, approved_by, approved_at, note, timestamps

**vouchers**

- id, code, type (percentage/fixed), value, min_purchase, max_discount (nullable), quota, used_count, start_at, end_at, is_active, timestamps, soft_deletes

**banners**

- id, title, image, link, sort_order, start_at, end_at, is_active, timestamps

**testimonials**

- id, customer_name, customer_photo, content, rating, is_approved, timestamps

**faqs**

- id, category, question, answer, sort_order, is_active, timestamps

**partners**

- id, name, logo, link, sort_order, is_active, timestamps

**branches**

- id, name, address, phone, latitude, longitude, maps_url, is_active, timestamps

**settings**

- id, key, value, group, timestamps

**contact_messages**

- id, name, email, phone, subject, message, is_read, replied_at, timestamps

**activity_log** (spatie)

- id, log_name, description, subject_type, subject_id, causer_type, causer_id, properties, timestamps

### Relasi Utama

- User → Role (many to many via model_has_roles)
- User → Customer (one to one)
- Category → Category (self, parent)
- Category → Product (one to many)
- Brand → Product (one to many)
- Product → ProductImage (one to many)
- Product → StockMovement (one to many)
- Customer → Order (one to many)
- Order → OrderItem (one to many)
- Order → Payment (one to one)
- Order → Shipment (one to one)
- Order → Return (one to one)
- Voucher → Order (one to many)

---

## 8. Non-Functional Requirements

### 8.1 Performance

- Halaman katalog load < 2s
- Model 3D lazy load (tidak blocking)
- Query database optimized (eager loading, index)
- Cache untuk data statis (kategori, merek)

### 8.2 Security

- HTTPS wajib
- CSRF protection (default Laravel)
- XSS protection (Blade auto-escape)
- Rate limit login (5x per menit)
- Password hashing (bcrypt)
- Role-based access control (spatie)
- Validasi input semua form (FormRequest)

### 8.3 SEO

- Meta tags dinamis per halaman
- Sitemap.xml
- Slug URL yang SEO-friendly
- Open Graph tags

### 8.4 Responsive

- Mobile-first untuk customer
- Desktop-optimized untuk admin panel
- Breakpoint: sm, md, lg, xl

---

## 9. Integrasi Pihak Ketiga

| Kebutuhan         | Tool                | Versi |
| ----------------- | ------------------- | ----- |
| Payment Gateway   | Midtrans / Xendit   | v1.5  |
| Shipping (ongkir) | RajaOngkir          | v1.5  |
| Maps              | Google Maps API     | v1    |
| Email             | SMTP / Mailgun      | v1    |
| WhatsApp          | Fonnte / Wablas     | v2    |
| Storage           | Local / S3          | v1    |
| 3D Model          | format .glb / .gltf | v1    |

**Catatan**: v1 pembayaran manual (transfer + upload bukti).
Payment gateway masuk v1.5.

---

## 10. Format Data Penting

| Data          | Format                   | Contoh               |
| ------------- | ------------------------ | -------------------- |
| SKU Produk    | RS-[KATEGORI]-[NUMBER]   | RS-ESP-001           |
| Nomor Pesanan | ORD-[YYYYMMDD]-[NUMBER]  | ORD-20260115-0042    |
| Invoice       | INV/[YYYYMM]/[NUMBER]    | INV/202601/0042      |
| File 3D       | .glb, max 10MB           | espresso-machine.glb |
| Gambar Produk | .jpg/.png/.webp, max 2MB | product-01.jpg       |
| Slug          | lowercase, dash          | espresso-machine-pro |

---

## 11. Edge Cases

| Kasus                                 | Handling                                |
| ------------------------------------- | --------------------------------------- |
| Stok habis saat checkout              | Tampilkan pesan, tawarkan produk serupa |
| Pembayaran expired                    | Auto-cancel setelah 24 jam              |
| Customer double-click checkout        | Cegah duplicate order (idempotency)     |
| Upload model 3D gagal                 | Fallback ke gambar biasa                |
| Voucher expired saat checkout         | Validasi ulang, kasih notice            |
| Refund setelah 7 hari                 | Tolak otomatis                          |
| Order dibatalkan setelah dikonfirmasi | Stok kembali otomatis                   |
| Stok tidak cukup saat konfirmasi      | Tolak konfirmasi, minta cek stok        |
| Upload gambar > 2MB                   | Validasi, tolak dengan pesan            |
| Session expired saat checkout         | Redirect login, simpan cart             |

---

## 12. Success Metrics

| Metrik                      | Target    |
| --------------------------- | --------- |
| Konversi katalog → checkout | > 3%      |
| Waktu load halaman katalog  | < 2s      |
| Waktu checkout selesai      | < 5 menit |
| Waktu admin input produk    | < 2 menit |
| Error rate transaksi        | < 0.5%    |
| Uptime                      | 99%       |
| Kepuasan customer (survey)  | > 4/5     |

---

## 13. Prioritas MoSCoW

### Must (MVP) — Target 6-8 minggu

- Auth + Role + Permission matrix
- Katalog (produk, kategori, merek, gambar)
- Stok (masuk/keluar, peringatan menipis)
- Cart + Checkout
- Order management (status flow)
- Pembayaran manual (upload bukti)
- Pengiriman + resi manual
- Invoice PDF
- CMS dasar (FAQ, kontak, banner, settings)
- Laporan dasar (penjualan harian)
- Admin panel + role

### Should (v1.5)

- Voucher
- Testimoni
- Cabang & peta
- Export Excel/PDF lengkap
- Integrasi Midtrans
- Integrasi RajaOngkir
- 3D viewer di detail produk

### Could (v2)

- Retur/refund otomatis
- Notifikasi WhatsApp
- Review produk
- Wishlist
- Multi-bahasa

### Won't (v1)

- Modul SDM (payroll, absensi, cuti)
- Blog/Artikel
- Mobile app native
- Marketplace integration
- Affiliate
- Multi-currency

---

## 14. Milestone

| Milestone             | Durasi   | Deliverable                                |
| --------------------- | -------- | ------------------------------------------ |
| **M1: Fondasi**       | 2 minggu | Setup Laravel, Breeze, role, layout, auth  |
| **M2: Katalog**       | 2 minggu | CRUD produk, kategori, merek, stok, gambar |
| **M3: Transaksi**     | 2 minggu | Cart, checkout, order, pembayaran, invoice |
| **M4: CMS + Laporan** | 2 minggu | CMS, laporan, polish, testing              |
| **M5: Launch**        | 1 minggu | Deploy, bug fix, monitoring                |

**Total**: ~9 minggu untuk MVP.

---

## 15. Out of Scope (v1)

Fitur berikut **TIDAK** dikerjakan di v1:

- Modul SDM (karyawan, absensi, cuti, payroll)
- Blog/Artikel
- Multi-bahasa (ID only)
- Mobile app native
- Marketplace integration (Tokopedia, Shopee)
- Affiliate program
- Multi-currency
- Live chat
- Wishlist
- Review produk
- Notifikasi WhatsApp
- Retur otomatis (v1 manual approve)
- Payment gateway otomatis (v1 manual)

**Alasan**: Fokus ke core e-commerce dulu. Fitur di atas masuk v1.5 atau v2.

---

## 16. Catatan Teknis

### Struktur Folder
