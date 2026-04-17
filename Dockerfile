FROM php:8.2-fpm

# Install dependensi sistem
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Bersihkan cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install ekstensi PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer dari image resmi
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files terlebih dahulu (optimasi cache layer)
COPY composer.json composer.lock ./

# Install dependencies (update untuk sinkronisasi lock file)
RUN composer update --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy seluruh kode backend
COPY . .

# Set permission storage & cache
RUN chmod -R 777 storage bootstrap/cache

CMD ["php-fpm"]
