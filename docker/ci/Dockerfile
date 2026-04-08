# =============================================================================
#  Gäld CI Image — PHP 8.4 + Node.js 24 + Composer + pnpm
#
#  Lean CLI image for GitLab CI. No FPM, no entrypoint.
#  Extensions mirror the production Dockerfile (docker/php/Dockerfile).
#
#  Build & push:
#    docker build -t registry.nectoria.com/nectoria/products/gaeld/gaeld-ee/ci:php8.4-node24 \
#      -f docker/ci/Dockerfile .
#    docker push registry.nectoria.com/nectoria/products/gaeld/gaeld-ee/ci:php8.4-node24
# =============================================================================

FROM php:8.4-cli-alpine

# ── System dependencies ──────────────────────────────────────────────────────
RUN apk add --no-cache \
    git \
    curl \
    openssh-client \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    icu-dev \
    postgresql-dev \
    oniguruma-dev \
    libxml2-dev \
    linux-headers

# ── PHP extensions (same set as production + coverage) ───────────────────────
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
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

# ── PECL extensions: redis + pcov (coverage) ────────────────────────────────
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis && docker-php-ext-enable redis \
    && pecl install pcov  && docker-php-ext-enable pcov \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# ── Composer ─────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Node.js 24 + pnpm ────────────────────────────────────────────────────────
COPY --from=node:24-alpine /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-alpine /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && npm install -g pnpm

# ── Verify installations ────────────────────────────────────────────────────
RUN php -v && composer --version && node -v && pnpm -v

WORKDIR /app
