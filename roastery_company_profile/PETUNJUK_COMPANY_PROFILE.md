# Company Profile + Toko + Insight + Keranjang

    php artisan migrate            # WAJIB: tabel articles, milestones, dan izin admin
    php artisan config:clear
    php artisan view:clear
    npm run build
    php artisan db:seed --class=ContentSeeder   # OPSIONAL: 3 artikel edukasi + 4 milestone CONTOH (ganti dengan data asli)

## Yang berubah
1. **Pemisahan situs.** Profil perusahaan (Beranda, Tentang, Artikel, Cabang, FAQ, Kontak) tanpa login/keranjang.
   Toko = /katalog (semua halaman belanja, keranjang, checkout, akun) dengan navbar sendiri: pencarian, keranjang, Masuk/Daftar, dan tautan "Kembali ke website".
2. **Beranda** fokus perkenalan: hero besar tanpa 3D (foto: Admin > Pengaturan > Tampilan > Background Beranda), perkenalan + layanan, banner lebar (Admin > Banner, maks. 2),
   milestone S, merek, testimoni, 3 artikel terbaru + "Lihat Semua Artikel", ajakan ke katalog. Kategori dan produk dipindah ke toko.
   Teks hero/perkenalan: Admin > Pengaturan > Tampilan.
3. **Insight** (/artikel): slider artikel pilihan, pencarian, kategori, detail artikel dengan foto dan video YouTube/Vimeo.
   Admin > Artikel: tulis (Markdown), sisipkan foto, sampul, video, jadwal tayang, slider. Kategori diubah di config/blog.php.
4. **Milestone** berbentuk S: Admin > Milestone. Beranda memuat 4 pertama, halaman Tentang memuat semua. Tulis hanya pencapaian yang benar.
5. **Keranjang gaya marketplace**: jumlah tersimpan otomatis (tanpa tombol Perbarui), melebihi stok otomatis disesuaikan dengan pesan "Stok tersedia hanya N", semua pesan berbahasa Indonesia.
6. Izin admin baru: view/create/edit/delete untuk articles dan milestones (otomatis untuk admin dan super_admin lewat migrasi).

## Ukuran gambar
Hero 1920x1080 (subjek di kanan) | Banner 1900x600 (rasio lebar) | Sampul artikel 1600x900 | Foto kategori 900x700.

## Berkas yang menimpa milik Anda
`routes/web.php`, `config/cms.php`, `resources/css/app.css`, dan `resources/js/app.js` memuat semua pembaruan sebelumnya juga.
Bila sudah Anda ubah, tambahkan hanya bagian barunya.
