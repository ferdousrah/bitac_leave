# Old BITAC software (bitac.technocratsbd.com) — container for after cutover.
#
# Coolify resource with base directory /legacy/old-system; the code lives in
# app/ next to this file.
#
# The old VPS ran Ubuntu 22.04's PHP 8.1, which no longer gets fixes. 8.5.12
# carries the CVE-2026-109618 / CVE-2026-109619 fixes (unauthenticated RCE,
# 21 Oct 2026) and matches the new system's version; 8.5 is supported until
# 31 Dec 2029. Keep the tag pinned and bump the patch number on every PHP
# security release.
FROM php:8.5.12-apache

# mpdf needs gd + mbstring; zip for composer
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" mysqli gd mbstring zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite headers

# Production ini. display_errors stays off: many old pages set
# error_reporting(E_ALL), and deprecation notices (${var} strings, null
# arguments) would otherwise print into the pages.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'upload_max_filesize=10M'; \
        echo 'post_max_size=12M'; \
        echo 'max_execution_time=120'; \
        echo 'memory_limit=256M'; \
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
        echo 'error_log=/var/log/apache2/php_errors.log'; \
        echo 'expose_php=Off'; \
    } > "$PHP_INI_DIR/conf.d/app.ini"

WORKDIR /var/www/html

# Dependencies first for layer caching (mpdf and what it pulls in)
COPY app/composer.json app/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

COPY app/ /var/www/html/

# The leave-totals API the new system's sync button calls
RUN test -f legacy-api/leave-totals.php

# uploads/ and pdfs/ are volumes; they must exist and be writable
RUN mkdir -p uploads pdfs \
    && chown -R www-data:www-data uploads pdfs

EXPOSE 80
CMD ["apache2-foreground"]
