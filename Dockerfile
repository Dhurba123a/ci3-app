FROM php:8.1-apache

WORKDIR /var/www/html

# Enable Apache rewrite module
RUN a2enmod rewrite

# Install system dependencies required by composer
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP Extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Note: Source code will be mounted as volume in docker-compose.yml for development
# Composer dependencies will be installed in a named volume to persist across mounts

