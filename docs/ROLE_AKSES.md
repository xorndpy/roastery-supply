# Role & Hak Akses (acuan)

| Kemampuan | Super Admin | Admin | Staff | Gudang |
|---|:-:|:-:|:-:|:-:|
| Kelola user, role, matriks hak akses, activity log | ✔ | – | – | – |
| Laporan penjualan & ekspor | ✔ | ✔ | – | – |
| Lihat nominal uang (omzet, harga, refund, bukti bayar) | ✔ | ✔ | ✔ | – |
| Verifikasi / tolak pembayaran | ✔ | ✔ | ✔ | – |
| Batalkan pesanan | ✔ | ✔ | – | – |
| Proses (kemas) pesanan | ✔ | ✔ | ✔ | ✔ |
| Kirim pesanan & input resi | ✔ | ✔ | ✔ | ✔ |
| Tandai pesanan SELESAI | ✔ | ✔ | ✔ | – |
| Stok: lihat & kelola (masuk/keluar/opname) | ✔ | ✔ | ✔ | ✔ |
| Retur: lihat | ✔ | ✔ | ✔ | ✔ |
| Retur: setujui / tolak / tandai refund | ✔ | ✔ | – | – |
| Produk, kategori, merek: tambah/ubah/hapus | ✔ | ✔ | – | – |
| Voucher, banner, testimoni, FAQ, artikel, cabang, pengaturan | ✔ | ✔ | – | – |
| Pelanggan: lihat | ✔ | ✔ | ✔ | – |
| Pesan kontak: lihat & balas | ✔ | ✔ | ✔ | – |

Prinsip: Gudang hanya mengerjakan barang fisik (kemas, kirim, stok) dan tidak melihat uang.
Menutup pesanan ("selesai") memulai batas retur 7 hari dan membuka testimoni, jadi hanya Staff ke atas.

Catatan teknis:
- Super Admin lolos semua pengecekan lewat `Gate::before` (AppServiceProvider).
- Permission `process_orders` dan `complete_orders` menggantikan penggunaan `edit_orders` pada tombol Proses / Selesai.
- Masuk ke `/admin` dibatasi middleware `role:staff|admin|super_admin|gudang` (routes/web.php). Role kustom
  yang dibuat dari matriks belum bisa masuk panel sebelum middleware itu diganti.

## Akun awal
`php artisan staff:setup --domain=domainanda.com --super-email=emailasli@anda.com`
Password acak ditampilkan sekali. Ganti di panel setelah login.
