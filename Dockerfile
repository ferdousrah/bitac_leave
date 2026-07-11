# BITAC Leave System — PHP 8.2 + Apache image
# Designed for Coolify / Docker Compose deployment
FROM php:8.2-apache

# Install system dependencies + PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        zip \
        unzip \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        libicu-dev \
        libxml2-dev \
        libgmp-dev \
        git \
        curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        mysqli \
        pdo \
        pdo_mysql \
        gd \
        zip \
        mbstring \
        intl \
        gmp \
        bcmath \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite headers

# PHP config — production-friendly, large file uploads, sane error handling.
# display_errors=Off is critical: DataTables/JSON APIs would otherwise get
# HTML warnings prepended to their JSON responses (breaking the parser with
# "Unexpected token '<'"). Errors still get logged.
RUN { \
        echo 'upload_max_filesize=10M'; \
        echo 'post_max_size=12M'; \
        echo 'max_execution_time=120'; \
        echo 'memory_limit=256M'; \
        echo 'date.timezone=Asia/Dhaka'; \
        echo 'session.gc_maxlifetime=7200'; \
        echo 'display_errors=Off'; \
        echo 'display_startup_errors=Off'; \
        echo 'log_errors=On'; \
        echo 'error_log=/var/log/apache2/php_errors.log'; \
    } > /usr/local/etc/php/conf.d/app.ini

# Apache: set DocumentRoot to project root, allow .htaccess
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Install Composer deps first (better layer caching)
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader || true

# Copy app source
COPY . /var/www/html/

# Re-run composer install in case lock file came with copy
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader || true

# Permissions: writable folders for uploads, sessions, logs
RUN mkdir -p /var/www/html/uploads \
             /var/www/html/sessions \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads /var/www/html/sessions

# Session storage outside the web root
RUN sed -ri -e 's!^session.save_path = .*!session.save_path = "/var/www/html/sessions"!' /usr/local/etc/php/php.ini-production || true

EXPOSE 80

# Default command: Apache foreground
CMD ["apache2-foreground"]
