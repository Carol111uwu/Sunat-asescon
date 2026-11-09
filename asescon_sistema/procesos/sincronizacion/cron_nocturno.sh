#!/usr/bin/env bash
set -u
umask 077
PROYECTO="$(cd "$(dirname "$0")/../.." && pwd)"
source "$PROYECTO/procesos/entorno_comun.sh" || exit 1
RUNTIME="$ASESCON_STORAGE_ROOT/sincronizacion"
mkdir -p "$RUNTIME" || exit 1
command -v flock >/dev/null && command -v timeout >/dev/null || exit 1
                                                           
RELOJ_LIMA="$(TZ=America/Lima date +%H%M)"
if [ "$RELOJ_LIMA" -ge 0030 ] && [ "$RELOJ_LIMA" -lt 0200 ]; then exit 0; fi
[ -f "$RECURSOS_PRIVADOS/RUNTIME_OK" ] || { echo 'Falta comprobar el entorno Python/Playwright antes de iniciar la sincronización.' >&2;exit 1; }
[ ! -f "$ASESCON_STORAGE_ROOT/sincronizacion/mantenimiento.flag" ] || exit 0
                                                                          
                                                                               
(
  exec 9>"$RUNTIME/almacenamiento.lock"
  flock -s -n 9 || exit 0
  flock -n 8 || exit 0
  [ ! -f "$RUNTIME/mantenimiento.flag" ] || exit 0
  if [ -f "$RUNTIME/cron.log" ] && [ "$(wc -c < "$RUNTIME/cron.log")" -gt 1048576 ]; then
    tail -c 262144 "$RUNTIME/cron.log" > "$RUNTIME/cron.log.tmp" && cat "$RUNTIME/cron.log.tmp" > "$RUNTIME/cron.log"
    rm -f "$RUNTIME/cron.log.tmp"
  fi
                                                                        
                                                                          
                                                                         
  (while :; do
    printf '%s\n' "$(date +%s)" > "$RUNTIME/ultima_ejecucion_cron"
    sleep 60
  done) &
  MARCA_PID=$!
  trap 'kill "$MARCA_PID" 2>/dev/null || true; wait "$MARCA_PID" 2>/dev/null || true' EXIT
  if [ -f "$RUNTIME/pausa.flag" ]; then
    printf '%s Cron: sincronización pausada desde el panel, sin borrar datos.\n' "$(date '+%F %T')"
    exit 0
  fi
                                                                         
  if [ "$(TZ=America/Lima date +%H)" = "02" ]; then
    timeout -k 5s 60s "$PHP_BIN" -d session.gc_divisor=1000 "$PROYECTO/procesos/comunicaciones/procesar_avisos.php"
    timeout -k 5s 45s "$PHP_BIN" -d session.gc_divisor=1000 "$PROYECTO/procesos/sincronizacion/planificar_avisos.php"
  fi
  "$PHP_BIN" -d session.gc_divisor=1000 "$PROYECTO/procesos/sincronizacion/planificar.php"
  timeout -k 5s 900s "$PHP_BIN" -d session.gc_divisor=1000 "$PROYECTO/procesos/sincronizacion/procesar.php"
                                                                           
                                                           
  /bin/bash "$PROYECTO/procesos/buzon_sunafil/procesar_cola.sh"
) 8>"$RUNTIME/coordinador.lock" >>"$RUNTIME/cron.log" 2>&1
