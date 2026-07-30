FROM php:8.2-fpm

# Install dependensi sistem & Python 3
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    ca-certificates \
    python3 \
    python3-venv \
    python3-pip \
    && update-ca-certificates \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Setup Python Virtual Environment & Install ML Libraries
RUN python3 -m venv /opt/ai_env \
    && /opt/ai_env/bin/pip install --no-cache-dir numpy pandas joblib scikit-learn xgboost torch

# Install ekstensi PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer dari image resmi
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files terlebih dahulu (optimasi cache layer)
COPY composer.json composer.lock ./

# Install dependencies (update untuk sinkronisasi lock file dengan composer.json)
RUN composer update --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy seluruh kode backend
COPY . .

# Run post-install scripts setelah kode lengkap tersedia
RUN composer run-script post-autoload-dump --no-interaction 2>/dev/null || true

# Set permission storage, cache, & public uploads
RUN chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R 775 storage bootstrap/cache public

EXPOSE 9000

CMD ["php-fpm"]
