FROM php:8.2-fpm

# Install dependensi sistem
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install ekstensi PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer dari image resmi
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files terlebih dahulu (optimasi cache layer)
COPY composer.json composer.lock ./

# Install dependencies (deterministic build — BUKAN update)
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy seluruh kode backend
COPY . .

# Run post-install scripts setelah kode lengkap tersedia
RUN composer run-script post-autoload-dump --no-interaction 2>/dev/null || true

# Set permission storage & cache (aman, bukan 777)
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
