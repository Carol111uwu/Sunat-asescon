"""Lectura de listados oficiales: no adivina valores ni abre documentos."""
import hashlib
import re
import unicodedata


def normal(s):
    return "".join(c for c in unicodedata.normalize("NFD", " ".join(str(s or "").split()).lower()) if unicodedata.category(c) != "Mn")


MENU = {
    "FISCALIZACION": "notificaciones de fiscalizacion",
    "COBRANZA": "notificaciones de cobranza",
    "ACCIONES_PREVIAS": "acciones previas",
    "FORMALIZACION": "formalizacion",
    "SEGURIDAD_SALUD": "seguridad y salud en el trabajo",
}

AP_HEADERS = {
    normal(h): h for h in ("Tipo de Requerimiento", "Registro", "Fecha de Depósito",
                           "Fecha Acuse de Recibo", "Fecha de Notificación", "Plazo",
                           "Fecha Límite de Presentación")
}

def interpretar_tabla(headers, rows, section, parse_date):
    keys = [normal(h) for h in headers]
    # No se aceptan tablas de navegación, filtros ni tablas sin identidad.
    identity_keys = ("registro", "orden de inspeccion", "expediente", "numero de expediente",
                     "nro. expediente", "asunto", "tipo de requerimiento", "documento",
                     "numero de documento", "nro. documento", "materia", "descripcion")
    identity = [i for i, k in enumerate(keys) if k in identity_keys]
    if not identity or not any(k.startswith("fecha") or k in ("estado", "intendencia", "plazo") for k in keys):
        return None
    if section == "ACCIONES_PREVIAS" and not {"registro", "tipo de requerimiento", "fecha de deposito", "plazo"} <= set(keys):
        return None
    result = []
    for row in rows:
        cells = row["cells"]
        if len(cells) != len(headers):
            continue
        fiscal_headers = {"orden de inspeccion": "Orden de Inspección", "intendencia": "Intendencia", "estado": "Estado"}
        campos = {(AP_HEADERS.get(k, h) if section == "ACCIONES_PREVIAS" else fiscal_headers.get(k, h) if section == "FISCALIZACION" else " ".join(h.split())): v for h, k, v in zip(headers, keys, cells)
                  if h and k not in ("opcion", "ver documento", "acciones", "ver documentos", "descargar", "envio req.", "envio req")}
        bykey = {normal(h): v for h, v in campos.items()}
        def get(*names):
            return next((bykey[n] for n in names if bykey.get(n)), "")
        registro = get("registro", "numero de documento", "nro. documento", "orden de inspeccion", "expediente", "numero de expediente", "nro. expediente")
        asunto = get("asunto", "tipo de requerimiento", "documento", "materia", "descripcion") or registro
        fecha = parse_date(get("fecha de notificacion", "fecha de deposito", "fecha", "fecha de recepcion"))
        if not asunto or (not registro and not fecha) or (section == "ACCIONES_PREVIAS" and not registro):
            raise RuntimeError("Fila oficial sin identidad verificable")
        seed = "|".join((section, registro or asunto, "" if registro else fecha))
        ident = "SUNAFIL-" + hashlib.sha256(seed.encode()).hexdigest()[:40]
        result.append({
            "identificador_externo": ident, "asunto": asunto[:255],
            "tipo_documento": (get("tipo de requerimiento", "tipo de documento") or section)[:100],
            "categoria": section, "fecha_recepcion": fecha, "prioridad": "NORMAL",
            "campos_oficiales": campos, "archivo": "", "enlace_pdf": row.get("pdf", ""),
            "observaciones": " | ".join(f"{h}: {v}" for h, v in campos.items())[:2000],
        })
    return result
