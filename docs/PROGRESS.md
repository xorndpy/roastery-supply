# Catatan Progres Pengembangan (bahan laporan)

Dokumen PRD (requirements) menjadi acuan. Pengembangan dikerjakan per batch (inkremen) per modul;
setiap batch diverifikasi dengan tes otomatis (`php artisan test`) dan temuan bug dikembalikan ke batch berikutnya.

| Batch | Cakupan | Bukti verifikasi |
|---|---|---|
| 1-8 | Fondasi: autentikasi, role/permission (seeder), skema database, katalog publik, 3D viewer | - |
| 9 | CRUD produk, dashboard & profil customer | - |
| 10 | CRUD kategori & merek | - |
| 11 | Verifikasi pembayaran admin | - |
| 12 | Laporan penjualan + chart + export Excel/PDF | - |
| 12.5 | **Perbaikan alur transaksi**: OrderService (stok & status terpusat, transaksi + lock), cart guest, idempotency checkout, snapshot alamat, auto-cancel 24 jam, invoice PDF, notifikasi email, upload ulang bukti ditolak | 61 tes |
| 13 | Stok (in/out/opname + riwayat), pengiriman, user, role matrix (RBAC berbasis permission), activity log | 76 tes |
| 14 | Login Google (OAuth 2.0), setup SMTP + `mail:test`, bukti bayar privat, detail pembayaran admin | 100 tes |
| 15 | CMS & halaman publik: pengaturan situs, banner (jadwal + drag-drop), testimoni (moderasi + dari order selesai), FAQ, partner, cabang (peta), pesan masuk, pelanggan (segmen + export) | 128 tes |
| 16 | Voucher (aturan standar e-commerce, terintegrasi checkout) + Retur/refund (customer & admin); refund mengurangi pendapatan di dashboard & laporan | 174 tes |
| 17 | Alamat terstruktur (38 provinsi, 514 kab/kota), tarif pengiriman per wilayah (admin), ongkir di checkout dihitung server, gratis ongkir, snapshot alamat/layanan | 202 tes |

## Bug yang ditemukan lewat pengujian (untuk bab Pengujian)
1. Stok bisa terpotong dua kali (logika terduplikasi di 3 tempat) -> dipusatkan di OrderService.
2. Customer tidak bisa upload ulang bukti yang ditolak (view menyembunyikan form).
3. Alamat order lama ikut berubah saat profil diedit -> snapshot alamat di tabel orders.
4. `UpdateProductRequest` salah folder -> edit produk crash (Class not found).
5. Model `StockMovement` memakai SoftDeletes tanpa kolom `deleted_at` -> halaman stok error.
6. Stok bisa diubah dari form produk tanpa jejak -> read-only, ubah lewat menu Stok.
7. Halaman detail pembayaran admin adalah salinan halaman daftar -> error 500.
8. Bukti transfer dapat diakses lewat URL publik -> dipindah ke storage privat + otorisasi.
9. Staff tidak bisa masuk panel; matriks role tidak berpengaruh (middleware role kasar) -> middleware per-permission.

10. Footer berisi kontak karangan dan rekening bayar di-hardcode (`BCA 1234567890`) -> semua dari Pengaturan; seeder lama berisi data palsu dibersihkan lewat migrasi.
11. Kolom produk/halaman publik tidak mengikuti PRD "tanpa hardcode" -> home, about, FAQ, cabang, kontak berbasis database.

12. Customer bisa mengajukan retur tanpa validasi apa pun (tanpa cek status selesai, batas 7 hari, atau duplikat) dan halaman retur tidak ada -> aturan PRD ditegakkan di ReturnService.
13. Diskon voucher di checkout selalu Rp 0 (belum terhubung) -> VoucherService + kuota dikembalikan saat order batal / kedaluwarsa.

14. Ongkir selalu Rp 0 dan kota/provinsi berupa teks bebas -> dropdown wilayah + tarif per wilayah; ongkir dihitung ulang di server (tidak percaya angka dari browser).

## Keputusan desain penting
- Ongkir = max(tarif minimum, tarif per kg x berat tagihan); berat dibulatkan ke atas ke kg, minimal 1 kg; produk tanpa berat dianggap 1 kg; tarif kota mengalahkan tarif provinsi.
- Gratis ongkir dinilai dari belanja SETELAH diskon voucher. Bila belum ada tarif sama sekali, checkout berjalan tanpa ongkir dan admin diberi peringatan.
- Arsitektur ShippingService dibuat agar RajaOngkir dapat dipasang belakangan tanpa mengubah checkout.
- Voucher: kode tidak peka huruf besar/kecil; persen (dengan diskon maksimum) atau nominal; minimum belanja; kuota total; batas per user (default 1x); satu kode per order, tidak bisa digabung; divalidasi ulang saat order dibuat (kuota dikunci); kode & jenis dikunci setelah dipakai.
- Retur: hanya order selesai, <= 7 hari sejak selesai (`orders.completed_at`), satu per order, wajib foto bukti (1-3, disimpan privat) dan rekening refund; admin menyetujui (nominal <= total, opsi stok kembali) atau menolak (alasan wajib); refund ditransfer manual lalu ditandai selesai.
- Pendapatan bersih = pendapatan - refund retur yang disetujui.
- Segmen pelanggan: baru (<=30 hari), aktif (belanja <=90 hari), dormant (pernah belanja, >90 hari), belum beli. "Belanja" = order yang pembayarannya sudah dikonfirmasi.
- Testimoni dari customer hanya dari order `selesai`, maksimal satu per order, tampil setelah disetujui admin.
- Stok berkurang saat pembayaran dikonfirmasi (bukan saat checkout); batal sesudah itu mengembalikan stok.
- Order `menunggu_bayar` > 24 jam otomatis batal, kecuali ada bukti bayar yang sedang menunggu verifikasi.
- Login Google: akun lokal yang emailnya belum terverifikasi dibatalkan password-nya saat digabung (anti pembajakan akun).
