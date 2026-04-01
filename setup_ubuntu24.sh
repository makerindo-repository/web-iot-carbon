#!/bin/bash

# ==============================================================================
# Setup Script for web-pertanian-upi (Ubuntu 24.04 - Noble Numbat)
# ==============================================================================

set -e

echo "🚀 Starting setup for Ubuntu 24.04..."

# 1. Update System & Install PHP 8.3
echo "📦 Installing PHP 8.3 and extensions..."
sudo apt update
sudo apt install -y php8.3-cli php8.3-common php8.3-curl php8.3-mbstring \
    php8.3-mysql php8.3-sqlite3 php8.3-xml php8.3-zip php8.3-bcmath \
    php8.3-intl php8.3-gd curl git unzip

# 2. Install Composer (if not exists)
if ! command -v composer &> /dev/null; then
    echo "📥 Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

# 3. Install Node.js (V3 via NodeSource)
if ! command -v node &> /dev/null; then
    echo "📥 Installing Node.js 22.x (LTS)..."
    curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
    sudo apt install -y nodejs
fi

# 4. PHP Dependencies
echo "🐘 Installing PHP dependencies..."
composer install --no-interaction

# 5. Environment Setup
if [ ! -f .env ]; then
    echo "⚙️ Creating .env file..."
    cp .env.example .env
    php artisan key:generate
fi

# 6. Database Setup (SQLite)
echo "📂 Setting up SQLite database..."
touch database/database.sqlite
php artisan migrate --seed --no-interaction

# 7. Frontend Setup
echo "🌐 Installing Node dependencies..."
npm install
echo "🏗️ Building assets..."
npm run build

echo "✅ Setup complete!"
echo "👉 Run 'php artisan serve' to start the application."
