FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git curl libpng-dev oniguruma-dev libxml2-dev \
    zip unzip nodejs npm nginx supervisor libzip-dev \
    libjpeg-turbo-dev libwebp-dev freetype-dev

# Configure & install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd xml zip opcache

# Install Composer (pin ke v2)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Kurangi download paralel + naikkan timeout biar tidak gampang putus (curl error 56)
ENV COMPOSER_MAX_PARALLEL_HTTP=4 \
    COMPOSER_PROCESS_TIMEOUT=900 \
    COMPOSER_ALLOW_SUPERUSER=1

# (Opsional) GitHub token supaya tidak kena rate limit. Isi via Railway Variables: GITHUB_TOKEN
ARG GITHUB_TOKEN
RUN if [ -n "$GITHUB_TOKEN" ]; then composer config -g github-oauth.github.com "$GITHUB_TOKEN"; fi

# Copy composer file dulu (layer cache)
COPY composer.json composer.lock ./

# Install PHP dependencies, retry sampai 5x
RUN for i in 1 2 3 4 5; do \
      composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist && break; \
      echo "composer gagal, retry $i..."; sleep 5; \
    done && test -d vendor

# Baru copy sisa project
COPY . .

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && (php artisan package:discover --ansi || true)

# INSTALL NODE DEPS & BUILD ASSETS
RUN npm install && npm run build

# Konfigurasi runtime (nginx + php-fpm + scheduler) dan permission
RUN sed -i 's/\r$//' docker/start.sh \
    && chmod +x docker/start.sh \
    && cp docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini \
    && cp docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 8080

CMD ["sh", "docker/start.sh"]
