FROM php:8.2-apache

WORKDIR /var/www/html

# Install system dependencies (termasuk yang dibutuhkan MongoDB)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libssl-dev \
    libsasl2-dev \
    pkg-config \
    libcurl4-openssl-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install MongoDB extension
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb

# Verify extensions are loaded (optional, for debugging)
RUN php -m | grep -E "(redis|mongodb)"

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files
COPY src/composer.json ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts || \
    (echo "Composer install failed, retrying..." && \
     composer install --no-dev --optimize-autoloader --no-scripts --no-plugins)

# Copy all source code
COPY src/ /var/www/html/

# Copy entrypoint script
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]