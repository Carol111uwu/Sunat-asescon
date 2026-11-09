#!/usr/bin/env bash
set -u
umask 077
PROYECTO="$(cd "$(dirname "$0")/../.." && pwd)"
PUBLIC_HTML="$(cd "$PROYECTO/.." && pwd)"
RUNTIME="$PUBLIC_HTML/asescon_datos_privados/almacenamiento/sunafil/buzon_sunafil"
LOG="$RUNTIME/cron.log"
ENTRADA=""
RESULTADO=""
mkdir -p "$RUNTIME" || exit 1
chmod 700 "$RUNTIME" 2>/dev/null || true
                                                                       
exec 9>"$RUNTIME/cron.lock"
if ! command -v flock >/dev/null || ! command -v timeout >/dev/null; then
    printf '%s El servidor necesita flock y timeout para procesar SUNAFIL.\n' "$(date '+%F %T')" >> "$LOG"
    exit 1
fi
flock -n 9 || exit 0
limpiar_salida() {
    [ -z "$ENTRADA" ] || rm -f -- "$ENTRADA"
    [ -z "$RESULTADO" ] || rm -f -- "$RESULTADO"
}
trap limpiar_salida EXIT
if [ -f "$LOG" ] && [ "$(wc -c < "$LOG")" -gt 1048576 ]; then
    tail -c 262144 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
fi
source "$PROYECTO/procesos/entorno_comun.sh" >>"$LOG" 2>&1 || exit 1
if [ -f "$ASESCON_STORAGE_ROOT/sincronizacion/mantenimiento.flag" ]; then exit 0; fi
if [ -f "$ASESCON_STORAGE_ROOT/sincronizacion/pausa.flag" ]; then exit 0; fi
if [ ! -x "$PYTHON_BIN" ]; then echo 'Falta instalar el entorno Python privado.' >>"$LOG";exit 1;fi
printf '%s\n' "$(date +%s)" > "$RUNTIME/ultima_ejecucion_cron"
PREPARADO="$("$PHP_BIN" "$PROYECTO/procesos/buzon_sunafil/preparar_solicitud.php" 2>>"$LOG")" || exit 1
[ -n "$PREPARADO" ] || exit 0
IFS=$'\t' read -r ID_TRABAJO ENTRADA RESULTADO <<< "$PREPARADO"
if [ -z "${ID_TRABAJO:-}" ] || [ -z "$ENTRADA" ] || [ -z "$RESULTADO" ]; then
    printf '%s Solicitud SUNAFIL no válida.\n' "$(date '+%F %T')" >> "$LOG"
    exit 2
fi
CODIGO=0
printf '%s SUNAFIL: inicio del trabajo %s.\n' "$(date '+%F %T')" "$ID_TRABAJO" >> "$LOG"
timeout -k 5s 240s "$PYTHON_BIN" "$PROYECTO/python/sunafil/consultar_casilla_sunafil.py" < "$ENTRADA" > "$RESULTADO" 2>>"$LOG" || CODIGO=$?
printf '%s SUNAFIL: salida del conector %s.\n' "$(date '+%F %T')" "$CODIGO" >> "$LOG"
CODIGO_GUARDADO=0
"$PHP_BIN" "$PROYECTO/procesos/buzon_sunafil/guardar_resultado.php" "$ID_TRABAJO" "$CODIGO" >>"$LOG" 2>&1 || CODIGO_GUARDADO=$?
printf '%s SUNAFIL: salida del guardado %s.\n' "$(date '+%F %T')" "$CODIGO_GUARDADO" >> "$LOG"
exit "$CODIGO_GUARDADO"
