#!/bin/bash
# Instala UNA vez el entorno privado (Python 3.13.18 + Playwright + Chromium) con el instalador original del
# sistema, dentro del volumen asescon_recursos_privados (no se pierde al reconstruir ni reiniciar).
# Uso: docker compose exec -u www-data tareas asescon-instalar-entorno
set -euo pipefail
if [ "$(id -un)" != "www-data" ]; then
    echo 'Ejecuta como www-data: docker compose exec -u www-data tareas asescon-instalar-entorno' >&2
    exit 1
fi
. /etc/asescon-entorno.sh
LOG=/var/www/html/asescon_recursos_privados/instalacion_runtime.log
if /bin/bash /var/www/html/asescon_sistema/procesos/buzon_sol/instalar_entorno.sh; then
    if [ -f /var/www/html/asescon_recursos_privados/RUNTIME_OK ]; then
        echo 'Entorno instalado y comprobado (RUNTIME_OK).'
        exit 0
    fi
fi
echo "La instalación no terminó. Revisa: $LOG" >&2
tail -n 30 "$LOG" >&2 2>/dev/null || true
exit 1
