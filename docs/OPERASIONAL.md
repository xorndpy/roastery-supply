# Panduan Operasional KOFFLINE

Dokumen ini untuk admin/teknisi. Isinya hal-hal yang dijalankan di server, bukan di kode aplikasi.

> Jangan pernah menyertakan file `.env` saat mengirim project ke orang lain. Di dalamnya ada kunci rahasia (database, Midtrans, Turnstile, dll).

## 1. Pengaturan `.env` produksi

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.koffline.my.id
SESSION_SECURE_COOKIE=true
LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
```

Setelah mengubah `.env`:

```
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan app:preflight
```

`app:preflight` memeriksa pengaturan di atas, koneksi database, email, kunci Turnstile, dan umur backup terakhir.
Perintah ini keluar dengan kode 1 bila ada yang GAGAL, jadi bisa dipakai di skrip deploy.

## 2. Backup database harian

**Opsi A, paling mudah:** aktifkan backup otomatis dari panel hosting (cPanel: *Backup* atau *JetBackup*) dan unduh satu salinan berkala ke komputer atau penyimpanan lain.

**Opsi B, lewat cron (disarankan, bisa dipantau oleh `app:preflight`):**

1. Buat file `~/.my.cnf` di server (izin `600`) supaya sandi tidak tampil di cron:

   ```
   [client]
   user=NAMA_USER_DB
   password=SANDI_DB
   ```

2. Tambahkan di **Cron Jobs** (setiap hari 02.30). Ganti `NAMA_DB` dan jalur project:

   ```
   30 2 * * * cd /home/USER/koffline.com && mkdir -p storage/app/backups && mysqldump --single-transaction --quick NAMA_DB | gzip > storage/app/backups/db-$(date +\%F).sql.gz && find storage/app/backups -name 'db-*.sql.gz' -mtime +7 -delete
   ```

3. Backup di server yang sama **belum aman** bila server bermasalah. Salin juga ke tempat lain secara berkala
   (mis. Cloudflare R2 memakai `rclone`, atau unduh manual).
4. **Uji pemulihan** minimal tiap 3 bulan: buat database kosong, jalankan `gunzip -c db-TANGGAL.sql.gz | mysql NAMA_DB_UJI`.
   Backup yang belum pernah dicoba dipulihkan belum bisa dianggap backup.

Foto produk dan berkas lain ada di penyimpanan media (R2/storage). Pastikan itu juga punya salinan.

## 3. Pemantauan

- **Uptime:** daftarkan `https://www.koffline.my.id/up` di UptimeRobot (gratis) atau Better Stack, interval 5 menit, notifikasi ke email dan WhatsApp.
- **Error aplikasi:** pasang Sentry (ada paket gratis).
  ```
  composer require sentry/sentry-laravel
  php artisan sentry:publish --dsn=ISI_DSN_DARI_SENTRY
  ```
  Setiap error 500 akan masuk dashboard lengkap dengan lokasi kode, tanpa menunggu pelanggan melapor.
- **Log:** dengan `LOG_CHANNEL=daily` file log dirotasi harian dan dihapus setelah 14 hari (`storage/logs`).
- **Scheduler:** pastikan cron `* * * * * cd /home/USER/koffline.com && php artisan schedule:run >> /dev/null 2>&1` aktif. Pembatalan pesanan kedaluwarsa dan sinkron resi bergantung padanya.

## 4. Daftar periksa setiap rilis

1. Backup database sebelum rilis.
2. Unggah file, lalu `php artisan migrate --force`.
3. `php artisan optimize:clear` lalu `config:cache`, `route:cache`, `view:cache`.
4. `php artisan app:preflight` dan pastikan tidak ada yang GAGAL.
5. Buka beranda, katalog, satu produk, login, dan checkout. Cek halaman error dengan membuka alamat ngawur.
6. Lihat dashboard Sentry/UptimeRobot 10 menit setelah rilis.

## 5. Email: agar pesanan tidak masuk spam

Email konfirmasi pesanan dan instruksi bayar adalah email terpenting toko. Kalau masuk spam, pelanggan tidak jadi membayar.

1. **Pakai alamat pengirim di domain sendiri**, bukan Gmail. Isi di `.env`:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=...        MAIL_PORT=465        MAIL_SCHEME=smtps
   MAIL_USERNAME=no-reply@koffline.my.id     MAIL_PASSWORD=...
   MAIL_FROM_ADDRESS=no-reply@koffline.my.id
   MAIL_FROM_NAME="KOFFLINE"
   ```
2. **Pasang tiga catatan DNS** (di Cloudflare DNS, statusnya harus *DNS only*, bukan proxied). Nilai persisnya diberikan penyedia email Anda:
   | Jenis | Nama | Isi |
   |---|---|---|
   | TXT (SPF) | `@` | `v=spf1 include:<domain-penyedia> ~all` (hanya boleh ada **satu** catatan SPF) |
   | TXT (DKIM) | `<selector>._domainkey` | kunci publik dari penyedia email |
   | TXT (DMARC) | `_dmarc` | `v=DMARC1; p=none; rua=mailto:dmarc@koffline.my.id` |
   Di cPanel, menu **Email Deliverability** membuatkan SPF dan DKIM otomatis dan menunjukkan mana yang belum benar.
   Setelah 2 minggu tanpa masalah di laporan DMARC, naikkan `p=none` menjadi `p=quarantine`.
3. **Uji kirim:** `php artisan mail:test alamatAnda@gmail.com`. Perintah ini juga memperingatkan bila mailer masih `log` atau pengirim masih `example.com`.
4. **Cek skor spam:** kirim email uji ke alamat yang diberikan mail-tester.com, lalu usahakan skor 9/10 atau lebih.
5. Penyedia email transaksional (Brevo, Resend, Mailgun) biasanya lebih konsisten sampai ke kotak masuk dibanding email hosting biasa, terutama ke Gmail.
6. Tema email ada di `resources/views/vendor/mail/` (header, footer, dan `themes/koffline.css`). Alamat, kontak, dan NPWP di footer email diambil dari Pengaturan.
