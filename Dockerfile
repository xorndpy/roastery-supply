FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git curl libpng-dev oniguruma-dev libxml2-dev \
    zip unzip nodejs npm nginx supervisor libzip-dev \
    libjpeg-turbo-dev libwebp-dev freetype-dev

# Configure & install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd xml zip opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

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
