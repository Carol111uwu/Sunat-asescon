"""Fiscalización: listados de órdenes y documentos, sin activar lectura oficial."""
import hashlib
from categorias import normal

ALIASES = {
    "tipo documento": "Tipo de documento", "tipo de documento": "Tipo de documento",
    "nro. de documento": "Número de documento", "nro. documento": "Número de documento",
    "numero de documento": "Número de documento", "nro documento": "Número de documento",
    "fecha deposito en la casilla electronica": "Fecha de depósito",
    "fecha deposito": "Fecha de depósito", "fecha de deposito": "Fecha de depósito",
    "fecha de notificacion al empleador": "Fecha de notificación",
    "fecha notificacion": "Fecha de notificación", "fecha de notificacion": "Fecha de notificación",
    "fecha comparecencia": "Fecha de comparecencia", "fecha de comparecencia": "Fecha de comparecencia",
    "fecha limite de respuesta": "Fecha límite de respuesta",
    "envio de respuesta": "Envío de respuesta", "envio respuesta": "Envío de respuesta",
}

def documentos_fiscalizacion(headers, rows, orden, intendencia, estado, parse_date):
    cols = [ALIASES.get(normal(h), "") for h in headers]
    if not {"Tipo de documento", "Número de documento"} <= set(cols):
        return None
    resultado = []
    for row in rows:
        if len(row["cells"]) != len(cols):
            continue
        campos = dict(zip(cols, row["cells"]))
        campos.pop("", None)
        tipo = campos.get("Tipo de documento", "")
        numero = campos.get("Número de documento", "")
        if not tipo or not numero:
            raise RuntimeError("Documento de fiscalización sin identidad verificable")
        campos.update({"Orden de Inspección": orden, "Intendencia": intendencia,
                       "Estado de orden": estado})
        ident = hashlib.sha256("|".join(("FISCALIZACION", orden, tipo, numero)).encode()).hexdigest()[:40]
        resultado.append({
            "identificador_externo": "SUNAFIL-" + ident, "asunto": (tipo + " · " + numero)[:255],
            "tipo_documento": tipo[:100], "categoria": "FISCALIZACION",
            "fecha_recepcion": parse_date(campos.get("Fecha de notificación") or campos.get("Fecha de depósito", "")),
            "prioridad": "NORMAL", "archivo": "", "enlace_pdf": row.get("pdf", ""),
            "campos_oficiales": campos, "observaciones": " | ".join(f"{k}: {v}" for k,v in campos.items())[:2000],
        })
    return resultado
