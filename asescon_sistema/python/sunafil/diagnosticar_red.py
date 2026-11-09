                                                                             
from __future__ import annotations

import errno
import re
import socket
import ssl
import subprocess
import sys
import time
from pathlib import Path

DESTINOS = {
    "SUNAFIL": ("casillaelectronica.sunafil.gob.pe", "/si.inbox/"),
    "SUNAT_CONTROL": ("api-seguridad.sunat.gob.pe", "/"),
}


def registrar(texto: str) -> None:
    print("RED_SUNAFIL: " + texto, flush=True)


def codigo_error(exc: Exception) -> str:
    if isinstance(exc, ssl.SSLCertVerificationError):
        return "CERTIFICADO_NO_VALIDO"
    if isinstance(exc, ssl.SSLError):
        return "ERROR_TLS"
    if isinstance(exc, (TimeoutError, socket.timeout)):
        return "TIEMPO_AGOTADO"
    if isinstance(exc, socket.gaierror):
        return "ERROR_DNS"
    if isinstance(exc, OSError):
        return errno.errorcode.get(exc.errno, "ERROR_RED")
    return "ERROR_DIAGNOSTICO"


def sondear(nombre: str) -> None:
    host, ruta = DESTINOS[nombre]
    registrar(nombre + " DNS: inicio")
    try:
        direcciones = socket.getaddrinfo(host, 443, type=socket.SOCK_STREAM)
    except Exception as exc:
        registrar(nombre + " DNS: " + codigo_error(exc))
        return
    seleccionadas = []
    for familia in (socket.AF_INET, socket.AF_INET6):
        candidatas = [d for d in direcciones if d[0] == familia]
        if candidatas:
            seleccionadas.append(candidatas[0])
    registrar(nombre + " DNS: OK; IPv4=%s IPv6=%s" % (
        any(d[0] == socket.AF_INET for d in direcciones),
        any(d[0] == socket.AF_INET6 for d in direcciones)))
    for familia, tipo, protocolo, _, direccion in seleccionadas:
        version = "IPv4" if familia == socket.AF_INET else "IPv6"
        prefijo = nombre + " " + version
        etapa = "TCP_443"
        sock = None
        inicio = time.monotonic()
        try:
            registrar(prefijo + " TCP_443: inicio")
            sock = socket.socket(familia, tipo, protocolo)
            sock.settimeout(4)
            sock.connect(direccion)
            registrar(prefijo + " TCP_443: OK")
            etapa = "TLS"
            sock.settimeout(5)
            sock = ssl.create_default_context().wrap_socket(sock, server_hostname=host)
            registrar(prefijo + " TLS: OK; certificado verificado")
            etapa = "HTTP"
            sock.settimeout(4)
            solicitud = ("GET %s HTTP/1.1\r\nHost: %s\r\n"
                         "User-Agent: ASESCON-Diagnostico/1.0\r\n"
                         "Connection: close\r\n\r\n") % (ruta, host)
            sock.sendall(solicitud.encode("ascii"))
            respuesta = b""
            while b"\r\n" not in respuesta and len(respuesta) < 4096:
                bloque = sock.recv(512)
                if not bloque:
                    break
                respuesta += bloque
            estado = re.match(rb"HTTP/\d(?:\.\d)? (\d{3})\b", respuesta)
            registrar(prefijo + " HTTP: " + (estado.group(1).decode() if estado else "RESPUESTA_NO_RECONOCIDA"))
        except Exception as exc:
            registrar(prefijo + " " + etapa + ": " + codigo_error(exc))
        finally:
            if sock is not None:
                sock.close()
            registrar(prefijo + " duración=%.1fs" % (time.monotonic() - inicio))


def main() -> None:
    if len(sys.argv) == 3 and sys.argv[1] == "--sondear" and sys.argv[2] in DESTINOS:
        sondear(sys.argv[2])
        return
    registrar("INICIO; prueba sin Chromium ni datos SOL")
    for nombre in DESTINOS:
                                                                         
        try:
            resultado = subprocess.run(
                [sys.executable, str(Path(__file__).resolve()), "--sondear", nombre],
                capture_output=True, timeout=30,
            )
            sys.stdout.write(resultado.stdout.decode("utf-8", errors="replace"))
            sys.stdout.flush()
            if resultado.returncode:
                registrar(nombre + " proceso interrumpido; código=%s" % resultado.returncode)
        except subprocess.TimeoutExpired as exc:
            sys.stdout.write((exc.stdout or b"").decode("utf-8", errors="replace"))
            registrar(nombre + " LIMITE_GLOBAL_30S")
    registrar("FIN; un HTTP 3xx/4xx también demuestra respuesta, no acceso autenticado")


if __name__ == "__main__":
    main()
