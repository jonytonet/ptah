FROM php:8.3-fpm-alpine

# ─── Dependências do sistema ──────────────────────────────────────────────────
RUN apk add --no-cache \
    bash \
    curl \
    git \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    nodejs \
    npm

# ─── Extensões PHP ────────────────────────────────────────────────────────────
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# ─── Redis (via PECL) ─────────────────────────────────────────────────────────
RUN pecl install redis \
    && docker-php-ext-enable redis

# ─── Composer ─────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ─── Configuração PHP customizada ─────────────────────────────────────────────
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# ─── Usuário não-root (mesmo UID/GID do host Linux para evitar permissão) ─────
RUN addgroup -g 1000 appgroup && adduser -u 1000 -G appgroup -s /bin/bash -D appuser
USER appuser

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
