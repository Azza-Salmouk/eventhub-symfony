FROM php:8.4-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    icu-dev \
    icu-libs \
    oniguruma-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    linux-headers \
    su-exec \
    && docker-php-ext-install \
        pdo_mysql \
        intl \
        opcache \
        zip \
        mbstring

# Install Composer 2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies (layer-cached)
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction \
    --ignore-platform-req=ext-sodium

# Copy application source
COPY . .

# Run Symfony post-install scripts
RUN composer run-script post-install-cmd --no-interaction 2>/dev/null || true

# Wipe stale cache, set permissions
RUN rm -rf /var/www/html/var/cache/* /var/www/html/var/log/* \
    && mkdir -p /var/www/html/var/cache \
                /var/www/html/var/log \
                /var/www/html/var/sessions \
                /var/www/html/public/uploads/events \
    && chown -R www-data:www-data \
        /var/www/html/var \
        /var/www/html/public/uploads \
    && chmod -R 775 /var/www/html/var \
    && if [ -f config/jwt/private.pem ]; then \
           chown www-data:www-data config/jwt/private.pem config/jwt/public.pem; \
           chmod 640 config/jwt/private.pem; \
           chmod 644 config/jwt/public.pem; \
       fi

# Entrypoint re-applies permissions at runtime (host bind-mount can reset them)
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
