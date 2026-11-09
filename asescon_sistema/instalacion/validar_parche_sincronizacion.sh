#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
PROYECTO="$(cd "$(dirname "$0")/.." && pwd)"
source "$PROYECTO/procesos/entorno_comun.sh"
RUNTIME="$ASESCON_STORAGE_ROOT/sincronizacion"
mkdir -p "$RUNTIME"
LOG="$RUNTIME/validacion_parche.log"
(
  source "$PROYECTO/procesos/entorno_comun.sh"
  for relativo in \
    servicios/sincronizacion/ColaNocturna.php \
    servicios/sincronizacion/PlanificadorSincronizacion.php \
    procesos/sincronizacion/planificar.php \
    procesos/sincronizacion/planificar_avisos.php \
    procesos/sincronizacion/procesar.php \
    controladores/sincronizacion/ControladorSincronizacionGeneral.php \
    vistas/utilitarios/sincronizacion.php; do
    "$PHP_BIN" -l "$PROYECTO/$relativo"
  done
  echo 'VALIDACION_PHP_OK: siete archivos comprobados sin ejecutarlos.'
) > "$LOG" 2>&1
