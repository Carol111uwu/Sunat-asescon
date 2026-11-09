FROM composer:2 AS vendor
WORKDIR /app
COPY asescon_sistema/composer.json asescon_sistema/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/asescon_sistema \
    ASESCON_PHP_CLI=/usr/local/bin/php \
    PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl git python3 python3-pip python3-venv unzip libzip-dev \
    && docker-php-ext-install -j"$(nproc)" mysqli zip \
    && a2enmod rewrite headers \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY asescon_sistema ./asescon_sistema
COPY --from=vendor /app/vendor ./asescon_sistema/vendor

RUN python3 -m venv /opt/asescon-venv \
    && /opt/asescon-venv/bin/pip install --no-cache-dir -r asescon_sistema/python/requirements.txt \
    && /opt/asescon-venv/bin/playwright install --with-deps chromium \
    && mkdir -p /var/www/html/asescon_datos_privados/almacenamiento /var/www/html/asescon_secure \
    && printf 'Deny from all\n' > /var/www/html/asescon_datos_privados/.htaccess \
    && chown -R www-data:www-data /var/www/html/asescon_datos_privados /var/www/html/asescon_secure /ms-playwright

ENV PATH="/opt/asescon-venv/bin:${PATH}"

COPY docker/php.ini /usr/local/etc/php/conf.d/asescon.ini

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD curl -fsS http://localhost/ || exit 1

