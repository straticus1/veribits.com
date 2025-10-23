FROM php:8.2-apache
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    clamav \
    clamav-daemon \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    tar \
    bzip2 \
    xz-utils \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

# Copy application files
COPY app/public/ /var/www/html/
COPY app/src/ /var/www/src/
COPY app/config/.env.example /var/www/.env

# Configure Apache
RUN a2enmod rewrite

# Create directories for file uploads
RUN mkdir -p /tmp/veribits-scans /tmp/veribits-archives \
    && chown -R www-data:www-data /tmp/veribits-scans /tmp/veribits-archives

# Configure ClamAV
RUN mkdir -p /var/run/clamav && chown -R clamav:clamav /var/run/clamav

EXPOSE 80
