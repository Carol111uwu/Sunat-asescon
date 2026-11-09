#!/usr/bin/env bash
                                                         
PUBLIC_HTML="$(cd "$PROYECTO/.." && pwd)"
RECURSOS_PRIVADOS="${ASESCON_RECURSOS_PRIVADOS:-$PUBLIC_HTML/asescon_recursos_privados}"
ASESCON_STORAGE_ROOT="$PUBLIC_HTML/asescon_datos_privados/almacenamiento"
if [ ! -f "$PUBLIC_HTML/asescon_datos_privados/.htaccess" ]; then
    echo 'Falta la protección del almacenamiento privado ASESCON.' >&2
    return 1
fi
export ASESCON_STORAGE_ROOT
resolver_php_asescon() {
    local candidato binario
    for candidato in "${ASESCON_PHP_CLI:-}" /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php /opt/cpanel/ea-php82/root/usr/bin/php /usr/local/bin/ea-php84 /usr/local/bin/ea-php83 /usr/local/bin/ea-php82 /usr/local/bin/php /usr/bin/php php; do
        [ -n "$candidato" ] || continue
        binario="$(command -v "$candidato" 2>/dev/null || true)"
        [ -n "$binario" ] && [ -x "$binario" ] || continue
        if "$binario" -r 'exit(PHP_SAPI === "cli" && PHP_VERSION_ID >= 80200 && extension_loaded("mysqli") ? 0 : 1);' >/dev/null 2>&1; then printf '%s' "$binario"; return 0; fi
    done
    return 1
}
PHP_BIN="$(resolver_php_asescon)" || { echo 'Falta PHP CLI 8.2 o superior con mysqli; revisa PHP de cPanel.' >&2; return 1; }
export ASESCON_PHP_CLI="$PHP_BIN"
PYTHON_BIN="${ASESCON_PYTHON_CLI:-${ASESCON_PYTHON_BIN:-$RECURSOS_PRIVADOS/entorno_buzon_sol/bin/python}}"
export ASESCON_RECURSOS_PRIVADOS="$RECURSOS_PRIVADOS" ASESCON_PYTHON_CLI="$PYTHON_BIN"
export PLAYWRIGHT_BROWSERS_PATH="$RECURSOS_PRIVADOS/navegadores_playwright"
export PIP_CACHE_DIR="$RECURSOS_PRIVADOS/cache_pip" XDG_CACHE_HOME="$RECURSOS_PRIVADOS/cache_linux" TMPDIR="$RECURSOS_PRIVADOS/temporales"
                                                                                
unset ASESCON_CHROMIUM
mkdir -p "$RECURSOS_PRIVADOS" "$TMPDIR" "$XDG_CACHE_HOME" || return 1
chmod 700 "$RECURSOS_PRIVADOS" "$TMPDIR" "$XDG_CACHE_HOME" 2>/dev/null || true

if [ -d "$PUBLIC_HTML/asescon_secure" ]; then
    chmod 700 "$PUBLIC_HTML/asescon_secure" 2>/dev/null || true
    for archivo in secretos.php credenciales.servidor.php; do
        [ ! -f "$PUBLIC_HTML/asescon_secure/$archivo" ] || chmod 600 "$PUBLIC_HTML/asescon_secure/$archivo" 2>/dev/null || true
    done
fi
