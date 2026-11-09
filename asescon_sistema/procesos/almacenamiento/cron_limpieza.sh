#!/usr/bin/env bash
                                                                             
                                                                    
set -u
umask 077
PROYECTO="$(cd "$(dirname "$0")/../.." && pwd)"
source "$PROYECTO/procesos/entorno_comun.sh" || exit 1
command -v timeout >/dev/null && command -v flock >/dev/null || exit 1
RUNTIME="$ASESCON_STORAGE_ROOT/sincronizacion"
mkdir -p "$RUNTIME" || exit 1
LOG="$RUNTIME/cron_limpieza.log"
if [ -f "$LOG" ] && [ "$(wc -c < "$LOG" 2>/dev/null || echo 0)" -gt 1048576 ]; then
    tail -c 262144 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
fi
ESTADO=0
timeout -k 5s 480s "$PHP_BIN" -d session.gc_divisor=1000 \
    "$PROYECTO/procesos/almacenamiento/limpiar_dia_anterior.php" --cron >>"$LOG" 2>&1 || ESTADO=$?
if [ "$ESTADO" -ne 0 ]; then
                                                                           
                                                                        
    (
      exec 9>"$RUNTIME/limpieza.lock"
      flock -x -n 9 || exit 0
      if [ -f "$RUNTIME/mantenimiento.flag" ] && \
         [ "$(head -n 1 "$RUNTIME/mantenimiento.flag")" = "$(TZ=America/Lima date +%F)" ]; then
         rm -f -- "$RUNTIME/mantenimiento.flag"
      fi
    )
fi
exit "$ESTADO"
