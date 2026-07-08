FROM php:8.2-fpm

# Instala dependências do sistema
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    git

# Instala extensões do PHP para MySQL e Strings
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Instala o Composer v2
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www