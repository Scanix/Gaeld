# syntax=docker/dockerfile:1@sha256:572cebbc2b15e6f84c077742952674889cb63cd5e9b80abdffcbe587b6f19183
FROM node:24.22.3-bookworm-slim@sha256:dc26ae92c9fb15cf6b1d63e20f0944b2efe0f115b20e00be48fb23fceb061c54 AS frontend
WORKDIR /src
RUN corepack enable && corepack prepare pnpm@11.20.0 --activate
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile
COPY . .
RUN SENTRY_AUTH_TOKEN='' pnpm build

FROM php:8.4.28-fpm-trixie@sha256:495ab94788034640ddd6fb313476e75570261622c42704e5ae02047b8d934ea0 AS runtime
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get upgrade -y --no-install-recommends \
    && apt-get install -y --no-install-recommends \
    libpq-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev \
    libonig-dev postgresql-client openssl unzip curl \
    tesseract-ocr tesseract-ocr-deu tesseract-ocr-fra tesseract-ocr-eng \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql mbstring pcntl bcmath gd zip intl opcache \
    && pecl install redis-6.3.0 && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.11.2@sha256:50ea4d33fdcde1adb0f045547295faa1548b33872e5f789dae5b02ce2fb5de93 /usr/bin/composer /usr/local/bin/composer
COPY --from=postgres:16.16-bookworm@sha256:ce3efec6bc404f6b15ebf5d9bed6b45a9d4655aea238d7dacc3467ccb05a1175 /usr/lib/postgresql/16/bin/pg_dump /usr/local/bin/pg_dump
COPY --from=postgres:16.16-bookworm@sha256:ce3efec6bc404f6b15ebf5d9bed6b45a9d4655aea238d7dacc3467ccb05a1175 /usr/lib/postgresql/16/bin/pg_restore /usr/local/bin/pg_restore
WORKDIR /var/www/html
COPY . .
RUN --mount=type=cache,target=/tmp/gaeld-composer-cache,sharing=locked \
    sh docker/production/install-composer.sh \
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

FROM nginx:1.30.7-alpine@sha256:caf4e1e032a20f9c3c5e28fc7f25ffd768ad8677b4975c54193075997b3a97e3 AS web
RUN apk upgrade --no-cache
COPY docker/production/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=runtime /var/www/html/public /var/www/html/public
EXPOSE 8080
STOPSIGNAL SIGQUIT

FROM runtime AS app
