from __future__ import annotations

import os
import re
from pathlib import Path
from typing import Any

RAIZ_PROYECTO = Path(__file__).resolve().parents[2]


def _desde_entorno() -> dict[str, Any] | None:
    datos = {
        "host": os.getenv("ASESCON_DB_HOST"),
        "usuario": os.getenv("ASESCON_DB_USER"),
        "password": os.getenv("ASESCON_DB_PASSWORD"),
        "base_datos": os.getenv("ASESCON_DB_NAME"),
        "puerto": os.getenv("ASESCON_DB_PORT", "3306"),
    }
    obligatorias = ("host", "usuario", "base_datos")
    if not all(datos[clave] not in (None, "") for clave in obligatorias):
        return None
    datos["password"] = datos["password"] or ""
    datos["puerto"] = int(str(datos["puerto"]))
    return datos


def _ruta_configuracion_privada() -> Path:
    archivo = (os.getenv("ASESCON_SECRET_FILE") or "").strip()
    if archivo:
        return Path(archivo)

    directorio = (os.getenv("ASESCON_SECURE_DIR") or "").strip()
    if directorio:
        return Path(directorio) / "secretos.php"

    candidatos = [
        RAIZ_PROYECTO.parent / "asescon_secure" / "secretos.php",
    ]

    for candidato in candidatos:
        if candidato.is_file():
            return candidato

    return candidatos[0]


def _extraer_bloque_db(contenido: str) -> str:
    coincidencia = re.search(
        r"['\"]db['\"]\s*=>\s*\[(.*?)\]\s*,?\s*['\"](?:smtp|sms)['\"]\s*=>",
        contenido,
        re.DOTALL,
    )
    if coincidencia:
        return coincidencia.group(1)

    coincidencia = re.search(r"['\"]db['\"]\s*=>\s*\[(.*?)\]\s*[,\]]", contenido, re.DOTALL)
    if coincidencia:
        return coincidencia.group(1)

    raise RuntimeError("No se encontró la sección 'db' en la configuración privada de ASESCON.")


def _desde_archivo_privado() -> dict[str, Any]:
    archivo = _ruta_configuracion_privada()
    if not archivo.is_file():
        raise RuntimeError(
            "No existe la configuración privada MySQL de ASESCON. Define ASESCON_DB_* o configura asescon_secure/secretos.php."
        )

    contenido = archivo.read_text(encoding="utf-8", errors="strict")
    bloque = _extraer_bloque_db(contenido)
    resultado: dict[str, Any] = {}

    for clave in ("host", "usuario", "password", "base_datos"):
        patron = rf"['\"]{re.escape(clave)}['\"]\s*=>\s*['\"]([^'\"]*)['\"]"
        coincidencia = re.search(patron, bloque)
        if not coincidencia:
            raise RuntimeError(f"Falta '{clave}' en la configuración privada MySQL de ASESCON.")
        resultado[clave] = coincidencia.group(1)

    puerto = re.search(r"['\"]puerto['\"]\s*=>\s*(\d+)", bloque)
    resultado["puerto"] = int(puerto.group(1)) if puerto else 3306
    return resultado


def obtener_configuracion_bd() -> dict[str, Any]:
    configuracion = _desde_entorno()
    if configuracion is not None:
        return configuracion
    return _desde_archivo_privado()
