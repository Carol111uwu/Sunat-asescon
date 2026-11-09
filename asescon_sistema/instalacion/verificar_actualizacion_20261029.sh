#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
PROYECTO="$(cd "$(dirname "$0")/.." && pwd)"
source "$PROYECTO/procesos/entorno_comun.sh"
REGISTROS="$ASESCON_STORAGE_ROOT/sincronizacion"
mkdir -p "$REGISTROS"
LOG="$REGISTROS/verificacion_actualizacion_20261029.log"
(
  echo 'ASESCON: comprobación local de la actualización 2026-10-29'
  CANTIDAD=0
  while IFS= read -r -d '' archivo; do
    "$PHP_BIN" -l "$archivo" >/dev/null
    CANTIDAD=$((CANTIDAD+1))
  done < <(find "$PROYECTO" -type f -name '*.php' -print0)
  echo "LINT_PHP_OK: $CANTIDAD archivos"
  "$PHP_BIN" "$PROYECTO/instalacion/verificar_actualizacion_20261029.php"
) > "$LOG" 2>&1
