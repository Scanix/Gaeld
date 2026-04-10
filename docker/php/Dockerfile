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
    oniguruma-dev \
    tesseract-ocr \
    tesseract-ocr-data-deu \
    tesseract-ocr-data-fra \
    tesseract-ocr-data-eng

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

# Disable PHP version disclosure in HTTP responses.
RUN printf 'expose_php=Off\n' > /usr/local/etc/php/conf.d/security.ini

# Install Redis extension (best effort for restricted networks)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && (pecl install redis && docker-php-ext-enable redis || echo "Skipping redis pecl extension: unavailable") \
    && (pecl install pcov && docker-php-ext-enable pcov || echo "Skipping pcov extension: unavailable") \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js and pnpm (for frontend build)
RUN apk add --no-cache nodejs npm && npm install -g pnpm

# Create Sail-compatible user expected by vendor/bin/sail
RUN addgroup -S -g 1000 sail \
    && adduser -S -D -h /home/sail -s /bin/sh -G sail -u 1000 sail

# Set working directory
WORKDIR /var/www/html

# Copy and set entrypoint
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/php/zz-gaeld-pool.conf /usr/local/etc/php-fpm.d/zz-gaeld-pool.conf
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

EXPOSE 9000
CMD ["php-fpm"]
