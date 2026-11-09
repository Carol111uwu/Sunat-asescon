#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
PROYECTO="$(cd "$(dirname "$0")/.." && pwd)"
source "$PROYECTO/procesos/entorno_comun.sh"
RUNTIME="$ASESCON_STORAGE_ROOT/sincronizacion"
mkdir -p "$RUNTIME"
LOG="$RUNTIME/verificacion_reparacion.log"
(
  for codigo in \
    modelos/sunat/RepositorioLecturaSire.php \
    controladores/sunat/ControladorDashboardSire.php \
    controladores/sunat/ControladorVistaLibroSire.php \
    controladores/clientes/ControladorCliente.php \
    controladores/buzones/ControladorSincronizacionBuzones.php \
    servicios/sunat/InspectorArchivosSire.php \
    vistas/sunat/panel_sire.php \
    vistas/sunat/rce.php \
    vistas/sunat/rvie.php \
    vistas/sunat/componentes/resumen_global_sire.php \
    vistas/buzones/buzon_sol_sunat.php \
    vistas/buzones/componentes/resumen_empresas_buzon.php \
    vistas/clientes/ficha_cliente.php \
    vistas/utilitarios/sincronizacion.php \
    vistas/utilitarios/componentes/progreso_global.php \
    api/sincronizacion/estado_global.php \
    instalacion/verificar_reparacion.php; do
      "$PHP_BIN" -l "$PROYECTO/$codigo"
  done
  echo 'LINT_PHP_OK'
  "$PHP_BIN" "$PROYECTO/instalacion/verificar_reparacion.php"
) > "$LOG" 2>&1
