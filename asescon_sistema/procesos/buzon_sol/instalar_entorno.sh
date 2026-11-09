#!/usr/bin/env bash
                                                                                   
set -Eeuo pipefail
umask 077
PROYECTO="$(cd "$(dirname "$0")/../.." && pwd)"
source "$PROYECTO/procesos/entorno_comun.sh"
exec >>"$RECURSOS_PRIVADOS/instalacion_runtime.log" 2>&1
for herramienta in flock timeout curl tar; do command -v "$herramienta" >/dev/null || { echo "Falta $herramienta";exit 2; };done
exec 8>"$RECURSOS_PRIVADOS/instalacion_runtime.lock"
flock -n 8 || exit 0
if [ -f "$RECURSOS_PRIVADOS/RUNTIME_OK" ]; then echo 'Runtime ya comprobado; no se reinstala.';exit 0;fi
if [ -f "$RECURSOS_PRIVADOS/RUNTIME_INTENTADO" ]; then echo 'Revisa instalacion_runtime.log y retira RUNTIME_INTENTADO solo al corregir la causa.';exit 1;fi
date -u '+%FT%TZ' > "$RECURSOS_PRIVADOS/RUNTIME_INTENTADO"
trap 'estado=$?;echo "RUNTIME_FALLIDO: codigo $estado, linea $LINENO. No se creo RUNTIME_OK.";exit "$estado"' ERR
UV_VERSION='0.13.6'
PYTHON_VERSION='3.13.18'
BINARIOS="$RECURSOS_PRIVADOS/herramientas"
UV_BIN="$BINARIOS/uv"
export UV_PYTHON_INSTALL_DIR="$RECURSOS_PRIVADOS/python"
export UV_NO_CACHE=1 UV_NO_PROGRESS=1 UV_PYTHON_INSTALL_BIN=0
mkdir -p "$BINARIOS" "$PLAYWRIGHT_BROWSERS_PATH" "$TMPDIR"
if [ ! -x "$UV_BIN" ]; then
 curl --proto '=https' --tlsv1.2 -fsSL --connect-timeout 20 --max-time 180 --retry 2 \
  "https://astral.sh/uv/$UV_VERSION/install.sh" -o "$BINARIOS/instalar_uv.sh"
 timeout -k 5s 240s env UV_UNMANAGED_INSTALL="$BINARIOS" sh "$BINARIOS/instalar_uv.sh"
fi
"$UV_BIN" --version | awk -v version="$UV_VERSION" '$1=="uv" && $2==version {ok=1} END {exit !ok}'
timeout -k 10s 600s "$UV_BIN" python install "$PYTHON_VERSION" --no-bin
PYTHON_BASE="$("$UV_BIN" python find --managed-python "$PYTHON_VERSION")"
"$PYTHON_BASE" -c 'import sys,ssl;assert sys.version_info[:3]==(3,13,18);print(sys.version.split()[0],ssl.OPENSSL_VERSION)'
ENTORNO="$RECURSOS_PRIVADOS/entorno_buzon_sol"
if [ ! -d "$ENTORNO" ]; then
 "$UV_BIN" venv --no-project --no-python-downloads --python "$PYTHON_BASE" "$ENTORNO"
fi
PYTHON_BIN="$ENTORNO/bin/python"
"$PYTHON_BIN" -c 'import sys;assert sys.version_info[:3]==(3,13,18),"Retira el entorno anterior y reintenta en una carpeta nueva"'
timeout -k 10s 600s "$UV_BIN" pip sync --python "$PYTHON_BIN" --only-binary :all: "$PROYECTO/python/requirements.txt"
"$UV_BIN" pip check --python "$PYTHON_BIN"
if ! timeout -k 10s 900s "$PYTHON_BIN" -m playwright install --only-shell chromium; then
 echo 'Fallo el CDN principal. Se intenta el mismo Chromium exacto desde Google.'
 timeout -k 10s 900s "$PYTHON_BIN" "$PROYECTO/python/instalar_chromium.py"
fi
                                                                  
env -u ASESCON_CHROMIUM timeout -k 5s 60s "$PYTHON_BIN" "$PROYECTO/python/verificar_runtime.py" "$RECURSOS_PRIVADOS/diagnostico_runtime.json" --estricto
date -u '+%FT%TZ' > "$RECURSOS_PRIVADOS/RUNTIME_OK"
echo 'RUNTIME_OK: versiones exactas y navegador local comprobados. Falta comprobar bases y crear la cuenta inicial.'
