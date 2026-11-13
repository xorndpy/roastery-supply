# Setup Login Google & Email (SMTP)

## A. Login dengan Google

> **Penting:** Google TIDAK menerima redirect URI berakhiran `.test` (domain otomatis Laragon).
> Untuk development pakai `localhost`.

1. Buka <https://console.cloud.google.com> → buat **project baru** (mis. "Roastery Supply").
2. **APIs & Services → OAuth consent screen**
   - User type: **External** → isi App name, User support email, Developer contact email.
   - Scopes: cukup `openid`, `email`, `profile` (tidak perlu verifikasi Google).
   - Selama status **Testing**, tambahkan email-email yang boleh login di **Test users**.
     Untuk umum: klik **Publish app** (aman untuk scope dasar di atas).
3. **APIs & Services → Credentials → Create Credentials → OAuth client ID**
   - Application type: **Web application**
   - **Authorized redirect URIs** (harus persis sama, termasuk http/https dan port):
     - development: `http://localhost:8000/auth/google/callback`
     - produksi: `https://domainmu.com/auth/google/callback`
4. Salin **Client ID** dan **Client secret** ke `.env`:
   ```
   APP_URL=http://localhost:8000
   GOOGLE_CLIENT_ID=xxxxxxxx.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxx
   GOOGLE_REDIRECT_URI=
   ```
5. Jalankan: `php artisan config:clear` lalu `php artisan serve` dan buka `http://localhost:8000/login`.
   Tombol **Lanjutkan dengan Google** muncul otomatis begitu ID & secret terisi.

Perilaku akun:
- Email Google belum pernah terdaftar → akun baru dengan role `customer`.
- Email sudah terdaftar → akun digabung (tidak ada akun ganda).
  Jika akun lama itu belum terverifikasi emailnya, password lamanya otomatis dibatalkan (anti pembajakan akun).
- Client secret jangan pernah di-commit ke git. `.env` sudah di-ignore.

## B. Email (SMTP)

Dipakai untuk: reset password, email **pesanan baru** (ke pembeli dan ke toko), dan notifikasi status pesanan.

**Opsi 1 - Gmail**
1. Akun Google → Security → aktifkan **2-Step Verification**.
2. Buka <https://myaccount.google.com/apppasswords> → buat App password (16 huruf).
3. `.env`:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_SCHEME=null
   MAIL_USERNAME=emailmu@gmail.com
   MAIL_PASSWORD=abcdefghijklmnop
   MAIL_FROM_ADDRESS=emailmu@gmail.com
   MAIL_FROM_NAME="Roastery Supply"
   ```

**Opsi 2 - Mailtrap (aman untuk uji coba, email tidak benar-benar terkirim)**
Daftar di mailtrap.io → Email Testing → salin kredensial SMTP ke `.env`.

**Opsi 3 - Email hosting Domainesia (cPanel), terlihat profesional: no-reply@domainmu**
1. cPanel → **Email Accounts** → Create, mis. `no-reply@koffline.my.id`.
2. Di baris akun itu klik **Connect Devices** → catat *Outgoing Server* dan port SSL.
3. `.env` di server:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=mail.koffline.my.id
   MAIL_PORT=465
   MAIL_SCHEME=smtps
   MAIL_USERNAME=no-reply@koffline.my.id
   MAIL_PASSWORD=password-akun-email-itu
   MAIL_FROM_ADDRESS=no-reply@koffline.my.id
   MAIL_FROM_NAME="KOFFLINE"
   ```
4. cPanel → **Email Deliverability**: pastikan SPF dan DKIM berstatus valid, kalau tidak email mudah masuk spam.
5. Password email hanya ditaruh di `.env` server, jangan di chat atau di git.

Tes:
```
php artisan config:clear
php artisan mail:test emailmu@gmail.com
```

### Email pesanan baru (tidak bergantung pada Midtrans)
- Saat checkout berhasil, **pembeli** menerima "Pesanan diterima" dan **toko** menerima "[Pesanan baru]", walaupun belum dibayar (jadi tetap jalan saat Midtrans masih sandbox).
- Saat pembayaran terkonfirmasi (manual maupun Midtrans), toko menerima "[Pembayaran masuk]" dan pembeli menerima "Pembayaran dikonfirmasi".
- Penerima toko diatur di **Pengaturan > Kontak > Email Notifikasi Pesanan** (pisahkan dengan koma). Kosong = toko tidak menerima email. Sengaja tidak memakai email kontak publik sebagai cadangan.
- Email tidak memakai queue, jadi tidak perlu `queue:work`. Kalau pengiriman gagal, checkout tetap berhasil dan error dicatat di `storage/logs`.
- Pembayaran Midtrans sandbox hanya terkonfirmasi kalau URL notifikasi `https://DOMAIN/webhooks/midtrans` diisi di dashboard Midtrans (Settings > Configuration), atau lewat cron `payments:reconcile`.

## C. Bukti bayar privat

Bukti transfer sekarang disimpan di `storage/app/private/payments` (tidak bisa dibuka lewat URL langsung).
Untuk memindahkan file lama dari `storage/app/public/payments`:
```
php artisan payments:privatize
```
