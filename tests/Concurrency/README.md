# Uji konkurensi pembayaran

Membuktikan bahwa notifikasi ganda, klik admin yang bersamaan, dan rebutan stok **tidak** menghasilkan stok ganda
atau pesanan ganda. Harus dijalankan di **MySQL/MariaDB** (InnoDB): SQLite tidak punya kunci baris, sehingga tidak
membuktikan apa pun tentang produksi.

## Menjalankan

1. Arahkan `.env` ke database MySQL/MariaDB KOSONG khusus uji, isi kunci Midtrans apa saja (hanya untuk tanda tangan
   uji), lalu `php artisan migrate:fresh --force` dan seed `PermissionSeeder`, `RoleSeeder`, `RolePermissionSeeder`, `UserSeeder`.
   `QUEUE_CONNECTION=sync` agar pemrosesan terjadi di dalam request.
2. Nyalakan server dengan banyak worker agar request benar-benar paralel:

       PHP_CLI_SERVER_WORKERS=12 php artisan serve --port=8001 --no-reload

3. Jalankan:

       php tests/Concurrency/harness.php          # semua skenario
       php tests/Concurrency/harness.php basic    # K-1 s.d. K-5
       php tests/Concurrency/harness.php race     # K-6 (balapan 20 putaran)

Variabel `CONC_URL` mengganti alamat server (default `http://127.0.0.1:8001`).
**Jangan jalankan di database yang berisi data asli**: skrip membuat produk, pesanan, dan pembayaran uji.

## Skenario

| Kode | Skenario | Yang dibuktikan |
|---|---|---|
| K-1 | 16 notifikasi identik serentak | 1 event, stok dikurangi sekali |
| K-2 | 6 webhook + 6 klik admin serentak | satu pemenang, yang kalah tercatat jelas |
| K-3 | stok 1, dua pesanan, 16 notifikasi | hanya satu terkonfirmasi, stok tidak negatif, uang yang kalah ditahan |
| K-4 | 40 pesanan x 3 notifikasi, 24 serentak | semua tepat sekali, ukuran throughput |
| K-5 | 20 notifikasi dengan tanda tangan palsu | semua 403, tidak ada perubahan |
| K-6 | balapan webhook vs admin, 20 putaran, urutan diacak | invarian terjaga di setiap putaran |
