# Use PHP 8.3 with Apache untuk optimasi Ubuntu 22.04 / Debian 12
FROM php:8.3-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies untuk Ubuntu 22.04 / Debian 12
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libfreetype6-dev \
    libjpeg-dev \
    libicu-dev \
    default-mysql-client \
    nano \
    htop \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions yang dibutuhkan
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# Enable Apache modules
RUN a2enmod rewrite headers ssl expires

# Configure PHP untuk production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache \
    && chmod +x /var/www/html/artisan

# Install dependencies dengan cache optimization
RUN composer install --optimize-autoloader --no-dev --no-scripts \
    && composer dump-autoload --optimize

# Configure Apache
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Configure Apache untuk port 2020
RUN sed -i 's/Listen 80/Listen 2020/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:2020>/' /etc/apache2/sites-available/000-default.conf

# Create entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port
EXPOSE 2020

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost:2020/up || exit 1

# Use entrypoint script
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
