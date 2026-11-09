#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
PROYECTO="$(cd "$(dirname "$0")/.." && pwd)"
source "$PROYECTO/procesos/entorno_comun.sh"
"$PHP_BIN" "$PROYECTO/instalacion/verificar_login_bases.php"
