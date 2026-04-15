# ============================================
# WOOBLE JOB APPLICATION — PHP DOCKERFILE
# ============================================

FROM php:8.2-apache

# ── Set Working Directory ─────────────────
WORKDIR /var/www/html

# ── Install System Dependencies ───────────
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Install PHP Extensions ────────────────
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# ── Enable Apache Modules ─────────────────
RUN a2enmod rewrite headers

# ── Install Composer ──────────────────────
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ── Copy Project Files ────────────────────
COPY . /var/www/html

# ── Install PHP Dependencies ──────────────
RUN if [ -f "composer.json" ]; then \
    composer install --no-interaction --optimize-autoloader --no-dev; \
fi

# ── Create uploads directory ──────────────
RUN mkdir -p /var/www/html/uploads/resumes

# ── Set Permissions ───────────────────────
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && chmod -R 777 /var/www/html/uploads

# ── Apache Config ─────────────────────────
COPY apache.conf /etc/apache2/sites-enabled/000-default.conf

# ── Expose Port ───────────────────────────
EXPOSE 80

# ── Start Apache ──────────────────────────
CMD ["apache2-foreground"]