#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
PROYECTO="$(cd "$(dirname "$0")/.." && pwd)"
source "$PROYECTO/procesos/entorno_comun.sh"
RUNTIME="$ASESCON_STORAGE_ROOT/sincronizacion"
mkdir -p "$RUNTIME"
LOG="$RUNTIME/verificacion_separacion.log"
(
  if [ -f "$(dirname "$ASESCON_STORAGE_ROOT")/.htaccess" ] && [ -w "$RUNTIME" ]; then
    echo 'DATOS_PRIVADOS_OK'
  else
    echo 'DATOS_PRIVADOS_ERROR'
    exit 2
  fi
  for archivo in \
    configuracion/rutas_privadas.php \
    configuracion/archivos_historicos.php \
    configuracion/conexion.php \
    servicios/buzones/ServicioNotasBuzon.php \
    servicios/sincronizacion/ColaNocturna.php \
    servicios/sincronizacion/EjecutorNocturno.php \
    servicios/sunafil/ServicioColaBuzonSunafil.php \
    servicios/sunafil/DiagnosticoBuzonSunafil.php \
    servicios/sunat/ServicioColaBuzonSol.php \
    servicios/sunat/DiagnosticoBuzonSol.php \
    servicios/sunat/ServicioPadronSunat.php \
    servicios/sunat/InspectorArchivosSire.php \
    servicios/sunat/ServicioEstadoAdjuntosSol.php \
    servicios/sunat/ServicioResultadoAdjuntosSol.php \
    servicios/archivos/ServicioArchivosPrivados.php \
    servicios/textos/ArchivoTextos.php \
    procesos/buzon_sol/preparar_solicitud.php \
    procesos/buzon_sol/guardar_resultado.php \
    procesos/sincronizacion/procesar.php \
    api/sincronizacion/estado_global.php \
    api/sunat/diagnostico_conectividad.php \
    controladores/buzones/ControladorSincronizacionBuzones.php \
    controladores/sincronizacion/ControladorSincronizacionGeneral.php \
    controladores/sincronizacion/ControladorPausaGeneral.php \
    controladores/panel/ControladorPanelPrincipal.php \
    vistas/utilitarios/sincronizacion.php \
    vistas/utilitarios/componentes/progreso_global.php; do
    "$PHP_BIN" -l "$PROYECTO/$archivo"
  done
  echo 'LINT_PHP_OK'
  "$PHP_BIN" -r 'require $argv[1]; $p=asesconRutaAlmacenamiento(); if(realpath($p)===false){exit(1);} echo "RUTA_PRIVADA_PHP_OK\n";' "$PROYECTO/configuracion/rutas_privadas.php"
  "$PHP_BIN" "$PROYECTO/instalacion/verificar_reparacion.php"
) > "$LOG" 2>&1
