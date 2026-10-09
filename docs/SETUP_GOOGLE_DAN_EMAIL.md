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

Dipakai untuk: reset password dan notifikasi status pesanan.

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

Tes:
```
php artisan config:clear
php artisan mail:test emailmu@gmail.com
```

## C. Bukti bayar privat

Bukti transfer sekarang disimpan di `storage/app/private/payments` (tidak bisa dibuka lewat URL langsung).
Untuk memindahkan file lama dari `storage/app/public/payments`:
```
php artisan payments:privatize
```
