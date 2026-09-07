# Stage 1: Builder
# Debian (glibc) is required for Neon DNS: Alpine/musl often fails with
# "could not translate host name ... Name or service not known".
FROM php:8.3-fpm-bookworm AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        git \
        unzip \
        libpq-dev \
        libonig-dev \
        libzip-dev \
        zlib1g-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_pgsql \
        mbstring \
        zip \
        bcmath \
        gd \
        opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

COPY . .

RUN composer dump-autoload --optimize

# Stage 2: Runtime
FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        libpq-dev \
        libonig-dev \
        libzip-dev \
        zlib1g-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libpq5 \
        nginx \
        supervisor \
        postgresql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_pgsql \
        mbstring \
        zip \
        bcmath \
        gd \
        opcache \
    && apt-get purge -y --auto-remove \
        libpq-dev \
        libonig-dev \
        libzip-dev \
        zlib1g-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /usr/local/etc/php-fpm.d/zz-docker.conf \
    && rm -f /etc/nginx/sites-enabled/default \
    && mkdir -p /var/log/nginx /run \
    && printf '%s\n' 'precedence ::ffff:0:0/96  100' >> /etc/gai.conf

COPY docker/php.ini /usr/local/etc/php/php.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /app

ENV FILESYSTEM_DISK=local
ENV FILESYSTEM_LOCAL_ROOT=/app/storage/app/private
ENV LOG_CHANNEL=stderr
ENV LOG_STACK=stderr
ENV CORS_ALLOWED_ORIGINS=https://bserp.vercel.app,http://localhost:8080,http://127.0.0.1:8080

COPY --from=builder /app .

RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views /var/log/supervisor /var/run \
    && chmod -R 755 storage bootstrap/cache /var/log/supervisor \
    && chown -R www-data:www-data /app /var/log/supervisor

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
