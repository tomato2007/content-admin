FROM php:8.3-cli

# System deps for common PHP extensions + tooling
RUN set -eux; \
  apt-get update; \
  apt-get install -y --no-install-recommends \
    git unzip zip curl \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    libxml2-dev \
    libonig-dev; \
  rm -rf /var/lib/apt/lists/*

# PHP extensions for Laravel
RUN set -eux; \
  docker-php-ext-install -j"$(nproc)" \
    pdo_pgsql \
    intl \
    zip \
    mbstring \
    xml

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
