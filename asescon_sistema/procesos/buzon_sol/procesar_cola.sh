#!/usr/bin/env bash
set -u
umask 077

PROYECTO="$(cd "$(dirname "$0")/../.." && pwd)"
PUBLIC_HTML="$(cd "$PROYECTO/.." && pwd)"
RUNTIME="$PUBLIC_HTML/asescon_datos_privados/almacenamiento/sunat/buzon_sol"
LOG="$RUNTIME/cron.log"
SLOT="${1:-0}"
case "$SLOT" in 0|1|2|3) ;; *) exit 2 ;; esac
LOG="$RUNTIME/cron_${SLOT}.log"
ENTRADA=""
RESULTADO=""
mkdir -p "$RUNTIME" || exit 1
command -v flock >/dev/null && command -v timeout >/dev/null || exit 1
exec 9>"$RUNTIME/cron_${SLOT}.lock"
flock -n 9 || exit 0
limpiar_salida() {
    [ -z "$ENTRADA" ] || rm -f -- "$ENTRADA"
    [ -z "$RESULTADO" ] || rm -f -- "$RESULTADO"
}
trap limpiar_salida EXIT

if [ -f "$LOG" ] && [ "$(wc -c < "$LOG" 2>/dev/null || echo 0)" -gt 1048576 ]; then
    tail -c 262144 "$LOG" > "$LOG.tmp" 2>/dev/null || true
    mv "$LOG.tmp" "$LOG" 2>/dev/null || true
fi

source "$PROYECTO/procesos/entorno_comun.sh" >>"$LOG" 2>&1 || exit 1
                                                               
RELOJ_LIMA="$(TZ=America/Lima date +%H%M)"
if [ "$RELOJ_LIMA" -ge 0030 ] && [ "$RELOJ_LIMA" -lt 0200 ]; then exit 0; fi
mkdir -p "$ASESCON_STORAGE_ROOT/sincronizacion" || exit 1
exec 8>"$ASESCON_STORAGE_ROOT/sincronizacion/almacenamiento.lock"
flock -s -n 8 || exit 0
if [ -f "$ASESCON_STORAGE_ROOT/sincronizacion/mantenimiento.flag" ]; then exit 0; fi
if [ -f "$ASESCON_STORAGE_ROOT/sincronizacion/pausa.flag" ]; then exit 0; fi
if [ ! -x "$PYTHON_BIN" ]; then echo 'Falta instalar el entorno Python privado.' >>"$LOG";exit 1;fi
printf '%s\n' "$(date +%s)" > "$RUNTIME/ultima_ejecucion_cron"
if [ "$(TZ=America/Lima date +%H)" = "02" ]; then
    "$PHP_BIN" -d session.gc_divisor=1000 "$PROYECTO/procesos/buzon_sol/programar_adjuntos.php" >>"$LOG" 2>&1 || true
fi
LIMITE_LOTE=$(( $(date +%s) + 50 ))
while [ "$(date +%s)" -lt "$LIMITE_LOTE" ]; do
if [ -f "$ASESCON_STORAGE_ROOT/sincronizacion/pausa.flag" ]; then exit 0; fi
PREPARADO="$("$PHP_BIN" -d session.gc_divisor=1000 "$PROYECTO/procesos/buzon_sol/preparar_solicitud.php" 2>>"$LOG")"
if [ -z "$PREPARADO" ]; then
    exit 0
fi

IFS=$'\t' read -r ID_TRABAJO ENTRADA RESULTADO <<< "$PREPARADO"
if [ -z "${ID_TRABAJO:-}" ] || [ -z "${ENTRADA:-}" ] || [ -z "${RESULTADO:-}" ]; then
    printf '%s No se pudo interpretar la solicitud preparada.\n' "$(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG"
    exit 2
fi

CODIGO=0
timeout -k 5s 900s "$PYTHON_BIN" "$PROYECTO/python/sunat/consultar_buzon_sol.py" < "$ENTRADA" > "$RESULTADO" 2>>"$LOG" || CODIGO=$?
"$PHP_BIN" -d session.gc_divisor=1000 "$PROYECTO/procesos/buzon_sol/guardar_resultado.php" "$ID_TRABAJO" "$CODIGO" >>"$LOG" 2>&1 || true

ENTRADA=""
RESULTADO=""
done
