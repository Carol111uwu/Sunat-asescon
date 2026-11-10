#!/bin/bash
# Contenedor "tareas": ejecuta las tareas automáticas (cron) con la misma imagen y los mismos datos que la web.
# cron no hereda las variables del contenedor: se guardan (solo ASESCON_*, TZ y Playwright) en
# /etc/asescon-entorno.sh, legible solo por root y www-data, y cada tarea lo carga antes de correr.
set -euo pipefail

export -p | grep -E '^declare -x (ASESCON_[A-Z0-9_]*|TZ|PLAYWRIGHT_BROWSERS_PATH)=' > /etc/asescon-entorno.sh || true
chown root:www-data /etc/asescon-entorno.sh
chmod 0640 /etc/asescon-entorno.sh

mkdir -p /var/www/html/asescon_datos_privados/almacenamiento/sincronizacion /var/www/html/asescon_recursos_privados
chown www-data:www-data /var/www/html/asescon_datos_privados /var/www/html/asescon_datos_privados/almacenamiento \
    /var/www/html/asescon_datos_privados/almacenamiento/sincronizacion /var/www/html/asescon_recursos_privados

if [ ! -f /var/www/html/asescon_recursos_privados/RUNTIME_OK ]; then
    echo '[tareas] Falta el entorno Python privado. Instálalo una vez con:' >&2
    echo '  docker compose exec -u www-data tareas asescon-instalar-entorno' >&2
fi

echo '[tareas] cron iniciado (3 tareas cada 5 minutos).'
exec cron -f
