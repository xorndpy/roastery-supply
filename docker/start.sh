#!/bin/sh
cd /var/www

PORT="${PORT:-8080}"
sed "s/__PORT__/${PORT}/g" docker/nginx.conf > /etc/nginx/http.d/default.conf

# artisan dijalankan sebagai www-data agar file log/cache tidak dimiliki root
run() { su -m -s /bin/sh www-data -c "$*"; }

# Gagal migrasi tidak boleh membuat situs mati: catat lalu lanjut.
run "php artisan migrate --force" || echo "[start] PERINGATAN: migrate gagal, server tetap dijalankan"
run "php artisan view:cache" || true

exec /usr/bin/supervisord -c /var/www/docker/supervisord.conf
