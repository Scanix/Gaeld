FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    icu-dev \
    postgresql-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Install Redis extension (best effort for restricted networks)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && (pecl install redis && docker-php-ext-enable redis || echo "Skipping redis pecl extension: unavailable") \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create Sail-compatible user expected by vendor/bin/sail
RUN addgroup -S -g 1000 sail \
    && adduser -S -D -h /home/sail -s /bin/sh -G sail -u 1000 sail

# Set working directory
WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
