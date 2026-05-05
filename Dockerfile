FROM php:8.2-apache

LABEL maintainer="ContaVision ERP"

# Instalar dependencias del sistema y Tesseract OCR
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype-dev \
    tesseract-ocr \
    tesseract-ocr-spa \
    imagemagick \
    ghostscript \
    && docker-php-ext-install pdo pdo_mysql zip gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar configuración de Apache para Slim
COPY docker/php/apache.conf /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite

# Directorio de trabajo
WORKDIR /var/www/html

# Copiar composer.json primero para aprovechar cache de capas
COPY backend/composer.json backend/composer.lock ./

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copiar código fuente
COPY backend/ .

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
