FROM composer:2 AS vendor
WORKDIR /app
COPY asescon_sistema/composer.json asescon_sistema/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM php:8.4-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/asescon_sistema \
    ASESCON_PHP_CLI=/usr/local/bin/php \
    PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates cron curl git python3 python3-pip python3-venv unzip libzip-dev \
    && docker-php-ext-install -j"$(nproc)" mysqli zip \
    && a2enmod rewrite headers remoteip \
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
                /var/www/html/asescon_recursos_privados \
    && printf 'Deny from all\n' > /var/www/html/asescon_datos_privados/.htaccess \
    && chown -R www-data:www-data /var/www/html/asescon_datos_privados /var/www/html/asescon_secure \
                /var/www/html/asescon_recursos_privados /ms-playwright \
    && chmod 700 /var/www/html/asescon_recursos_privados

ENV PATH="/opt/asescon-venv/bin:${PATH}"

COPY docker/php.ini /usr/local/etc/php/conf.d/asescon.ini

# Detrás del proxy compartido: IP real del usuario y HTTPS (mod_remoteip).
COPY docker/apache-proxy.conf /etc/apache2/conf-available/asescon-proxy.conf
# Tareas automáticas (servicio "tareas" del compose): cron + instalador del entorno privado.
COPY docker/tareas.cron /etc/cron.d/asescon
COPY docker/tareas-entrypoint.sh /usr/local/bin/asescon-tareas
COPY docker/instalar-entorno.sh /usr/local/bin/asescon-instalar-entorno
RUN sed -i 's/\r$//' /etc/cron.d/asescon /usr/local/bin/asescon-tareas /usr/local/bin/asescon-instalar-entorno \
        /etc/apache2/conf-available/asescon-proxy.conf \
    && chmod 0644 /etc/cron.d/asescon \
    && chmod 0755 /usr/local/bin/asescon-tareas /usr/local/bin/asescon-instalar-entorno \
    && a2enconf asescon-proxy \
    && sed -i 's/%h /%a /' /etc/apache2/apache2.conf

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD curl -fsS http://localhost/ || exit 1

