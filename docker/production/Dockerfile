# syntax=docker/dockerfile:1@sha256:4edf897a3ffa55b89f906fc8cc78afdb3f1834cc9c7083565e611a8a7d5fe99e
FROM node:24-bookworm-slim@sha256:c86ecead9e55a5210ac1727de38a4ca840bf03dcaf34dc9749addbf383b1920c AS frontend
WORKDIR /src
RUN corepack enable && corepack prepare pnpm@11.20.0 --activate
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile
COPY . .
RUN SENTRY_AUTH_TOKEN='' pnpm build

FROM php:8.4-fpm-trixie@sha256:3a6527017daca7917709afa08a37388ec108db093857f31d642cfff2e6f0449e AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev \
    libonig-dev postgresql-client openssl unzip curl \
    tesseract-ocr tesseract-ocr-deu tesseract-ocr-fra tesseract-ocr-eng \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql mbstring pcntl bcmath gd zip intl opcache \
    && pecl install redis-6.3.0 && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2@sha256:5190d9b445add2fa26090108273cb1ef1e1af98175201c737b85b3c4f90fbf7c /usr/bin/composer /usr/local/bin/composer
COPY --from=postgres:16-bookworm@sha256:e8a07d2c7579b038938cdba6c539e580e5001caf9ea203d397b5cc83e76bd938 /usr/lib/postgresql/16/bin/pg_dump /usr/local/bin/pg_dump
COPY --from=postgres:16-bookworm@sha256:e8a07d2c7579b038938cdba6c539e580e5001caf9ea203d397b5cc83e76bd938 /usr/lib/postgresql/16/bin/pg_restore /usr/local/bin/pg_restore
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && rm -rf node_modules tests phpunit.xml .git .github .env \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/views storage/framework/sessions storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && printf 'expose_php=Off\nopcache.enable=1\nopcache.validate_timestamps=0\n' > /usr/local/etc/php/conf.d/gaeld.ini
COPY --from=frontend /src/public/build ./public/build
COPY docker/production/entrypoint.sh /usr/local/bin/gaeld-entrypoint
COPY docker/production/scheduler.sh /usr/local/bin/gaeld-scheduler
COPY docker/production/zz-gaeld.conf /usr/local/etc/php-fpm.d/zz-gaeld.conf
RUN chmod +x /usr/local/bin/gaeld-entrypoint /usr/local/bin/gaeld-scheduler
USER www-data
ENTRYPOINT ["gaeld-entrypoint"]
CMD ["php-fpm", "-F"]
STOPSIGNAL SIGQUIT

FROM nginx:1.28-alpine@sha256:a8b39bd9cf0f83869a2162827a0caf6137ddf759d50a171451b335cecc87d236 AS web
COPY docker/production/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=runtime /var/www/html/public /var/www/html/public
EXPOSE 8080
STOPSIGNAL SIGQUIT

FROM runtime AS app
