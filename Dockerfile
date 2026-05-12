ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli-bookworm

# System dependencies. exif is required by spatie/laravel-medialibrary;
# pdo_sqlite is needed by Orchestra Testbench's in-memory test database.
RUN apt-get update && apt-get install -y \
        libsqlite3-dev \
        git \
        unzip \
    && docker-php-ext-install pdo_sqlite exif \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

# Avoid git "dubious ownership" warning when /app is mounted as a volume
RUN git config --global --add safe.directory /app

WORKDIR /app
