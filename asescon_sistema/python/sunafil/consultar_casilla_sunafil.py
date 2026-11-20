                                                                              

                                                                                            
                                  
   
from __future__ import annotations

import hashlib
import os
from pathlib import Path
from tempfile import NamedTemporaryFile
import time
import unicodedata
import json
import re
import sys
from datetime import datetime
from typing import Any
from urllib.parse import urljoin, urlparse
from categorias import MENU, interpretar_tabla
from fiscalizacion import documentos_fiscalizacion

try:
    sys.stdin.reconfigure(encoding="utf-8")
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")
except Exception:
    pass

SUNAFIL_PORTADA = "https://casillaelectronica.sunafil.gob.pe/si.inbox/"
HOST = "casillaelectronica.sunafil.gob.pe"
LOGIN_HOST = "api-seguridad.sunat.gob.pe"


def out(payload: dict[str, Any]) -> None:
    sys.stdout.write(json.dumps(payload, ensure_ascii=False, separators=(",", ":")))
    sys.stdout.flush()

def clean(v: Any) -> str:
    return re.sub(r"\s+", " ", str(v or "")).strip()

def frames(page):
    yield page
    for f in page.frames:
        if f != page.main_frame:
            yield f

def body_text(page) -> str:
    parts: list[str] = []
    for f in frames(page):
        try:
            t = f.locator("body").inner_text(timeout=5000)
            if t:
                parts.append(t)
        except Exception:
            pass
    return "\n".join(parts)

def find_input(page, selectors: list[str]):
    for f in frames(page):
        for s in selectors:
            try:
                loc = f.locator(s)
                if loc.count() and loc.first.is_visible():
                    return loc.first
            except Exception:
                pass
    return None

def click_login(page) -> bool:
    for f in frames(page):
        for role in ("button", "link"):
            for pat in (r"entrar", r"ingresar", r"iniciar\s*sesión", r"iniciar\s*sesion"):
                try:
                    loc = f.get_by_role(role, name=re.compile(pat, re.I))
                    if loc.count() and loc.first.is_visible():
                        loc.first.click()
                        return True
                except Exception:
                    pass
    return False

def folded(value: str) -> str:
    return "".join(c for c in unicodedata.normalize("NFD", clean(value).lower()) if unicodedata.category(c) != "Mn")


def is_sunafil_session(page) -> bool:
    url = urlparse(page.url)
    text = folded(body_text(page))

    return (
        url.scheme == "https"
        and url.hostname == HOST
        and url.path.lower().startswith("/si.inbox/")
        and find_input(page, ["input[type=password]"]) is None
        and (("bienvenido" in text and "notificaciones de fiscalizacion" in text)
             or (re.search(r"cerrar sesion|salir|logout", text)
                 and re.search(r"bandeja|notificaciones|comunicaciones|orientacion", text)))
    )

def navigation_failure(exc: Exception) -> str:
                                                                            
    code = re.search(r"\bnet::(ERR_[A-Z0-9_]+)\b", str(exc))
    if code:
        return code.group(1)
    if isinstance(exc, TimeoutError) or type(exc).__name__ == "TimeoutError":
        return "TIEMPO_AGOTADO"
    return "ERROR_NAVEGACION"


def goto_retry(page, url: str, attempts: int = 2, timeout: int = 30000) -> None:
    for attempt in range(attempts):
        try:
                                                                               
                                                                            
            response = page.goto(url, wait_until="commit", timeout=timeout)
            if response and response.status >= 400:
                raise RuntimeError("SUNAFIL rechazó el acceso (HTTP %s)." % response.status)
            if response:
                sys.stderr.write("SUNAFIL: documento recibido, HTTP %s.\n" % response.status)
            return
        except RuntimeError:
            raise
        except Exception as exc:
            sys.stderr.write("SUNAFIL: navegación, intento %s/%s, %s.\n" % (attempt + 1, attempts, navigation_failure(exc)))
            if attempt + 1 == attempts:
                raise RuntimeError("No se pudo conectar con la entrada oficial de SUNAFIL (%s)." % navigation_failure(exc)) from None
            page.wait_for_timeout(800)


def interactive_check(page) -> None:
                                                                                     
    if find_input(page, ['iframe[src*="recaptcha"][title*="challenge"]', 'input[name*="captcha" i]']):
        raise RuntimeError("El acceso requiere una verificación interactiva. Ingresa manualmente a SUNAFIL.")
    if re.search(r"no soy un robot|complete.{0,30}captcha|verificacion interactiva", folded(body_text(page))):
        raise RuntimeError("SUNAT requiere una verificación interactiva para este acceso.")


def login(page, ruc: str, usuario: str, clave: str):
    goto_retry(page, SUNAFIL_PORTADA)
    deadline = time.monotonic() + 20
    while time.monotonic() < deadline:
        employer = page.get_by_role("button", name=re.compile(r"empleador", re.I))
        if employer.count() and employer.first.is_visible():
            break
        employer = page.get_by_role("link", name=re.compile(r"empleador", re.I))
        if employer.count() and employer.first.is_visible():
            break
        page.wait_for_timeout(500)
    else:
        raise RuntimeError("SUNAFIL respondió, pero la entrada de empleadores no apareció dentro de 20 segundos.")
    sys.stderr.write("SUNAFIL: entrada de empleadores visible.\n")
    previous_pages = list(page.context.pages)
    employer.first.click()
    deadline = time.monotonic() + 25
    while time.monotonic() < deadline:
        opened = [p for p in page.context.pages if p not in previous_pages]
        if opened:
            page = opened[-1]
        password = find_input(page, ["input[type=password]"])
        if password is not None:
            break
        page.wait_for_timeout(500)
    else:
        raise RuntimeError("SUNAFIL no abrió el formulario oficial de Clave SOL.")

    interactive_check(page)
                                                                               
    auth = urlparse(password.evaluate("el => window.location.href"))
    if auth.scheme != "https" or auth.hostname != LOGIN_HOST:
        raise RuntimeError("El formulario no pertenece al acceso oficial de SUNAT.")
    ruc_input = find_input(page, ['#txtRuc', 'input[name*=ruc i]', 'input[id*=ruc i]'])
    user_input = find_input(page, ['#txtUsuario', 'input[name*=usuario i]', 'input[id*=usuario i]'])
    if ruc_input is None or user_input is None:
        raise RuntimeError("El formulario SOL cambió: no se reconocieron RUC y usuario.")
    for control in (ruc_input, user_input):
        origin = urlparse(control.evaluate("el => window.location.href"))
        if origin.scheme != "https" or origin.hostname != LOGIN_HOST:
            raise RuntimeError("El formulario no pertenece al acceso oficial de SUNAT.")
    ruc_input.fill(ruc)
    user_input.fill(usuario)
    password.fill(clave)
    if not click_login(page):
        password.press("Enter")

    deadline = time.monotonic() + 35
    while time.monotonic() < deadline:
        candidates = [p for p in page.context.pages if not p.is_closed()]
        for candidate in candidates:
            if is_sunafil_session(candidate):
                                                                                 
                text = body_text(candidate)
                shown = re.search(r"RUC\s*[:.-]?\s*(\d{11})", text, re.I)
                if shown and shown.group(1) != ruc:
                    raise RuntimeError("SUNAFIL mostró la casilla de otro RUC.")
                return candidate
        text = folded(body_text(page))
        if re.search(r"(usuario|clave|credencial).{0,80}(incorrect|invalid|no existe)", text):
            raise RuntimeError("SUNAT rechazó el usuario o la Clave SOL.")
        if "forbidden" in text:
            raise RuntimeError("El servicio rechazó el acceso (Forbidden). Revisa cron.log.")
        interactive_check(page)
        page.wait_for_timeout(700)
    raise RuntimeError("SUNAFIL no confirmó una sesión y bandeja autenticadas. No se guardaron resultados.")

def parse_date(value: str) -> str:
    value = clean(value)
    for pattern in (r"(\d{2}/\d{2}/\d{4})(?:\s+(\d{1,2}:\d{2})(?::\d{2})?)?", r"(\d{4}-\d{2}-\d{2})(?:\s+(\d{1,2}:\d{2})(?::\d{2})?)?"):
        m = re.search(pattern, value)
        if not m:
            continue
        d, tm = m.group(1), m.group(2) or "00:00"
        for fmt in ("%d/%m/%Y %H:%M", "%Y-%m-%d %H:%M"):
            try:
                return datetime.strptime(f"{d} {tm}", fmt).strftime("%Y-%m-%d %H:%M:%S")
            except ValueError:
                pass
    return ""

def safe_internal_links(page) -> list[tuple[str, str]]:
    links: list[tuple[str, str]] = []
    seen: set[str] = set()
    for f in frames(page):
        try:
            items = f.locator("a[href]").evaluate_all("els => els.map(a => ({text:(a.innerText||'').trim(),href:a.href||''}))")
        except Exception:
            continue
        for item in items:
            text = clean(item.get("text", ""))
            href = clean(item.get("href", ""))
            if not href:
                continue
            p = urlparse(href)
            if p.scheme != "https" or p.hostname != HOST:
                continue
            if p.netloc != HOST or not p.path.lower().startswith("/si.inbox/"):
                continue
            if not p.path.rstrip("/").split("/")[-1] or href.split("#")[0] == page.url.split("#")[0]:
                continue
            if re.search(r"detalle|acuse|confirm|aceptar|marcar|descarga|verdocumento", folded(href)):
                continue
            label = re.sub(r"\s*\(?\d+\)?$", "", folded(text)).strip()
            categories = {
                "notificaciones de fiscalizacion": "FISCALIZACION",
                "notificaciones de cobranza": "COBRANZA",
                "acciones previas": "ACCIONES PREVIAS",
                "formalizacion": "FORMALIZACION",
                "seguridad y salud en el trabajo": "SEGURIDAD Y SALUD EN EL TRABAJO",
                "sunafil te orienta": "ORIENTACION",
                "orientacion": "ORIENTACION",
            }
            section = categories.get(label)
            if not section and label in ("notificaciones", "bandeja de entrada", "mensajes", "comunicaciones", "alertas"):
                section = label.upper()
            if not section or href in seen or href.endswith("#"):
                continue
            seen.add(href)
            links.append((section, href))
    return links

def table_records(page, section: str, requested: str = "", download=None) -> list[dict[str, Any]]:
    records: list[dict[str, Any]] = []
    for f in frames(page):
        try:
            tables = f.locator("table").evaluate_all(
                """tables => tables.map(t => ({
                    headers:Array.from(t.querySelectorAll('thead th, thead td').length ? t.querySelectorAll('thead th, thead td') : t.querySelectorAll('tr:first-child th')).map(x=>(x.innerText||'').trim()),
                    rows:Array.from(t.querySelectorAll('tbody tr')).map(tr => ({
                        cells:Array.from(tr.querySelectorAll('td')).map(td=>(td.innerText||'').trim()),
                        links:Array.from(tr.querySelectorAll('a[href]')).map(a=>({text:(a.innerText||'').trim(),href:a.href||''}))
                    }))
                }))"""
            )
        except Exception:
            continue

        for table_index, table in enumerate(tables):
            headers = [folded(x) for x in table.get("headers", [])]
            if not any(any(k in h for k in ("asunto", "documento", "descripcion", "materia", "expediente", "mensaje", "orden de inspeccion")) for h in headers):
                continue
            for row_index, row in enumerate(table.get("rows", [])):
                cells = [clean(x) for x in row.get("cells", [])]
                if not cells or all(not x for x in cells):
                    continue
                joined = " | ".join(cells)
                if re.search(r"no\s+hay|sin\s+registros|ning[uú]n\s+registro", joined, re.I):
                    continue

                def cell_for(*keys: str) -> str:
                    for i, h in enumerate(headers):
                        if any(k in h for k in keys) and i < len(cells):
                            return cells[i]
                    return ""

                asunto = cell_for("asunto", "mensaje", "descrip", "documento", "materia")
                tipo = cell_for("tipo", "origen", "documento")
                fecha = cell_for("fecha", "notific", "recepci")
                if not asunto:
                    candidates = [c for c in cells if c and not re.fullmatch(r"\d+", c)]
                    asunto = max(candidates, key=len) if candidates else joined[:255]
                fecha_iso = parse_date(fecha or joined)

                links = row.get("links", []) or []
                pdf = ""
                identifier_seed = cell_for("expediente", "numero", "registro", "codigo") or "|".join((asunto, tipo, fecha_iso))
                for link in links:
                    href = clean(link.get("href", ""))
                    txt = clean(link.get("text", ""))
                    if not pdf and (".pdf" in href.lower() or "pdf" in txt.lower()):
                                                                              
                        if is_official_pdf_link(href):
                            pdf = href

                if section in {"FISCALIZACION", "COBRANZA", "ACCIONES PREVIAS", "FORMALIZACION", "SEGURIDAD Y SALUD EN EL TRABAJO"}:
                    identifier_seed = "|".join((section, identifier_seed, asunto, fecha_iso))
                ident = hashlib.sha256(identifier_seed.encode("utf-8", "ignore")).hexdigest()[:40]
                if requested == "SUNAFIL-" + ident and download is not None and not pdf:
                    tr = f.locator("table").nth(table_index).locator("tbody tr").nth(row_index)
                    current = [clean(cell) for cell in tr.locator("td").all_inner_texts()]
                    if current == cells:
                        download(tr)
                records.append({
                    "identificador_externo": "SUNAFIL-" + ident,
                    "asunto": asunto[:255],
                    "tipo_documento": (tipo or section)[:100],
                    "categoria": (section or "SUNAFIL")[:80],
                    "fecha_recepcion": fecha_iso,
                    "prioridad": "NORMAL",
                    "enlace_pdf": pdf,
                    "archivo": "",
                    "observaciones": joined[:2000],
                })
    return records

def generic_records(page, section: str) -> list[dict[str, Any]]:
                                                                                  
    records: list[dict[str, Any]] = []
    selectors = "[role=row], .mat-row, .p-datatable-tbody > tr, .list-group-item"
    for f in frames(page):
        try:
            texts = f.locator(selectors).evaluate_all("els => els.filter(e => !e.querySelector('[role=columnheader], th') && e.querySelector('[role=cell], [role=gridcell], td')).map(e => e.innerText || '')")
        except Exception:
            continue
        for raw in texts:
            txt = clean(raw)
            if len(txt) < 20:
                continue
            if not re.search(r"requerimiento|notific|resoluci[oó]n|inspecci[oó]n|orden|acta|expediente|sunafil", txt, re.I):
                continue
            stable_text = re.sub(r"\b(?:sin leer|no leido|leido|revisado|no revisado|descargar|ver documento)\b", "", folded(txt))
            ident = hashlib.sha256(clean(stable_text).encode("utf-8", "ignore")).hexdigest()[:40]
            records.append({
                "identificador_externo": "SUNAFIL-" + ident,
                "asunto": txt[:255],
                "tipo_documento": (section or "SUNAFIL")[:100],
                "categoria": (section or "SUNAFIL")[:80],
                "fecha_recepcion": parse_date(txt),
                "prioridad": "NORMAL",
                "archivo": "",
                "observaciones": txt[:2000],
            })
    return records

def read_orientation(page, requested='', download=None) -> list[dict[str, Any]]:
    pages = [
    (
        "ORIENTACION",
        "https://casillaelectronica.sunafil.gob.pe/si.inbox/Orientacion/ListadoOrientacionEmpleador"
    )
]
    records = []
    recognized = False
    for section, url in pages:
        if page.url != url:
            goto_retry(page, url, attempts=1)
            page.wait_for_timeout(700)
        if not is_sunafil_session(page):
            raise RuntimeError("La sesión SUNAFIL se perdió al consultar la bandeja.")
        signatures = set()
        for _ in range(120):
            deadline = time.monotonic() + 10
            while True:
                text = folded(body_text(page))
                batch = table_records(page, section, requested, download)
                if not batch:
                    batch = generic_records(page, section)
                empty = bool(re.search(r"sin (?:notificaciones|registros|comunicaciones)|no (?:hay|tiene|existen) (?:notificaciones|registros|comunicaciones)|ningun registro|no data available|no se encontraron registros", text))
                if batch or empty or time.monotonic() >= deadline:
                    break
                page.wait_for_timeout(500)
            if not batch and not empty:
                raise RuntimeError("No se reconoció una página de la bandeja SUNAFIL. No se guardaron resultados parciales.")
            if batch or empty:
                recognized = True
            signature = tuple(r["identificador_externo"] for r in batch)
            if signature in signatures and batch:
                raise RuntimeError("La paginación SUNAFIL no avanzó. No se guardaron resultados parciales.")
            signatures.add(signature)
            records.extend(batch)
                                                                                  
            next_button = None
            for f in frames(page):
                for selector in ('li.paginate_button.next:not(.disabled) a', '.dataTables_paginate .next:not(.disabled)', 'button[aria-label="Siguiente página"]', 'button[aria-label="Next page"]'):
                    loc = f.locator(selector)
                    if loc.count() and loc.first.is_visible() and loc.first.is_enabled() and loc.first.get_attribute("aria-disabled") != "true":
                        next_button = loc.first
                        break
                if next_button is not None:
                    break
            if next_button is None:
                break
            next_button.click()
            page.wait_for_timeout(900)
        else:
            raise RuntimeError("La bandeja supera el límite de 120 páginas. No se guardaron resultados parciales.")
    if not recognized:
        raise RuntimeError("La sesión abrió, pero no se reconoció la bandeja ni un aviso de bandeja vacía. No se guardaron resultados.")
    unicas = {}
    for registro in records:
        identificador = registro["identificador_externo"]
        if identificador not in unicas or registro.get("enlace_pdf"):
            unicas[identificador] = registro
    return list(unicas.values())

def read_other_sections(page, avisos):
    """Solo navega a enlaces de listado identificados por el menú oficial."""
    links = {}
    for frame in frames(page):
        for item in frame.locator("a[href]").evaluate_all(
            "els => els.map(a => ({text: a.textContent, href: a.href}))"
        ):
            label = re.sub(r"\s*\d+$", "", folded(item["text"]))
            u = urlparse(item["href"])
            if u.scheme != "https" or u.netloc != HOST or not u.path.lower().startswith("/si.inbox/"):
                continue
            if re.search(r"detalle|acuse|confirm|archivo|descarga|\.pdf", folded(u.path)):
                continue
            for section, title in MENU.items():
                if label == title:
                    links[section] = item["href"]
    links["ACCIONES_PREVIAS"] = SUNAFIL_PORTADA + "ActosAdministrativos/ListadoRequerimientos"
    links["FISCALIZACION"] = SUNAFIL_PORTADA + "Notificacion/Empleador"
    result = []
    for section in MENU:
        try:
            if section not in links:
                raise RuntimeError("Menú no reconocido")
            goto_retry(page, links[section], attempts=1)
            if not is_sunafil_session(page):
                raise RuntimeError("Sesión no reconocida")
            seen = set()
            section_rows = []
            for _ in range(120):
                page.wait_for_timeout(700)
                tables = page.locator("table:visible").evaluate_all(r"""ts => ts.map(t => ({
                    headers: Array.from(t.querySelectorAll(':scope > thead > tr:last-child > th')).map(x => x.innerText.trim()),
                    rows: Array.from(t.querySelectorAll(':scope > tbody > tr')).map(tr => ({
                        cells: Array.from(tr.querySelectorAll(':scope > td')).map(td => { const copy=td.cloneNode(true); copy.querySelectorAll('script,style,button,input').forEach(n=>n.remove()); return copy.textContent.trim(); }),
                        pdf: (() => { const a = Array.from(tr.querySelectorAll('a[href]')).filter(a => /\.pdf$/i.test(new URL(a.href).pathname)); return a.length === 1 ? a[0].href : ''; })()
                    }))
                }))""")
                batches = [interpretar_tabla(t["headers"], t["rows"], section, parse_date) for t in tables]
                valid = [b for b in batches if b is not None]
                if not valid:
                    raise RuntimeError("Encabezados no reconocidos")
                batch = [r for b in valid for r in b]
                if not batch and not re.search(r"sin registros|no hay|ningun registro|no data available|no se encontraron|ningun dato", folded(body_text(page))):
                    raise RuntimeError("Listado sin filas verificables")
                sig = tuple(r["identificador_externo"] for r in batch)
                if sig in seen or len(set(sig)) != len(sig):
                    raise RuntimeError("Identidad o paginación ambigua")
                seen.add(sig)
                section_rows.extend(batch)
                nxt = page.locator("li.paginate_button.next:not(.disabled) a, .dataTables_paginate .next:not(.disabled), .ui-paginator-next:not(.ui-state-disabled)")
                if not nxt.count() or not nxt.first.is_visible():
                    break
                nxt.first.click()
            else:
                raise RuntimeError("Límite de páginas")
            if len({r["identificador_externo"] for r in section_rows}) != len(section_rows):
                raise RuntimeError("Identidad repetida")
            result.extend(section_rows)
            if section == "FISCALIZACION":
                for orden in section_rows:
                    try:
                        result.extend(read_fiscal_documents(page, links[section], orden))
                    except Exception:
                        if section not in avisos:
                            avisos.append(section)
                        sys.stderr.write("SUNAFIL: detalle de fiscalización no verificado.\n")
        except Exception:
            avisos.append(section)
            sys.stderr.write("SUNAFIL: listado no verificado: " + section + "\n")
    return result

def read_fiscal_documents(page, url, orden):
    """Abre solo el listado de documentos de una orden, nunca el botón de lectura."""
    campos = orden["campos_oficiales"]
    numero = campos.get("Orden de Inspección") or next(
        (v for k,v in campos.items() if folded(k) == "orden de inspeccion"), "")
    if not numero:
        raise RuntimeError("Orden no identificada")
    goto_retry(page, url, attempts=1)
    for _ in range(120):
        page.wait_for_timeout(700)
        candidatos = []
        for fila in page.locator("table:visible tbody tr").all():
            if numero not in [clean(v) for v in fila.locator(":scope > td").all_inner_texts()]:
                continue
            for boton in fila.locator("a,button").all():
                etiquetas = [folded(v) for v in (boton.inner_text(), boton.get_attribute("title") or "",
                                             boton.get_attribute("data-original-title") or "",
                                             boton.get_attribute("aria-label") or "")]
                if any(e in ("ver", "ver documentos", "ver documento") for e in etiquetas):
                    candidatos.append(boton)
        if len(candidatos) == 1:
            candidatos[0].click()
            break
        if candidatos:
            raise RuntimeError("Acción de orden ambigua")
        nxt = page.locator("li.paginate_button.next:not(.disabled) a, .dataTables_paginate .next:not(.disabled), .ui-paginator-next:not(.ui-state-disabled)")
        if not nxt.count() or not nxt.first.is_visible():
            raise RuntimeError("No se encontró la orden")
        nxt.first.click()
    else:
        raise RuntimeError("Límite de páginas")
    result = []
    seen = set()
    for _ in range(120):
        page.wait_for_timeout(900)
        if not is_sunafil_session(page):
            raise RuntimeError("Sesión no reconocida")
        tables = page.locator("table:visible").evaluate_all(r"""ts => ts.map(t => ({
            headers: Array.from(t.querySelectorAll(':scope > thead > tr:last-child > th')).map(x => x.innerText.trim()),
            rows: Array.from(t.querySelectorAll(':scope > tbody > tr')).map(tr => ({
                cells: Array.from(tr.querySelectorAll(':scope > td')).map(td => { const c=td.cloneNode(true); c.querySelectorAll('script,style,button,input').forEach(n=>n.remove()); return c.textContent.trim(); }),
                pdf: (() => { const a=Array.from(tr.querySelectorAll('a[href]')).filter(a=>/\.pdf$/i.test(new URL(a.href).pathname)); return a.length===1?a[0].href:''; })()
            }))
        }))""")
        valid = [b for t in tables if (b := documentos_fiscalizacion(t["headers"], t["rows"], numero,
                    campos.get("Intendencia", ""), campos.get("Estado", ""), parse_date)) is not None]
        if len(valid) != 1:
            raise RuntimeError("Tabla de documentos no reconocida")
        batch = valid[0]
        if not batch and not re.search(r"sin registros|ningun dato|ningun registro|no data available|no se encontraron", folded(body_text(page))):
            raise RuntimeError("Listado no verificado")
        for doc in batch:
            if doc["identificador_externo"] in seen:
                raise RuntimeError("Documento duplicado")
            seen.add(doc["identificador_externo"])
            result.append(doc)
        nxt = page.locator("li.paginate_button.next:not(.disabled) a, .dataTables_paginate .next:not(.disabled), .ui-paginator-next:not(.ui-state-disabled)")
        if not nxt.count() or not nxt.first.is_visible():
            return result
        nxt.first.click()
    raise RuntimeError("Límite de documentos")

def read_inbox(page, avisos=None):
    records = read_orientation(page)
    if avisos is not None:
        records.extend(read_other_sections(page, avisos))
    return records

def is_official_pdf_link(url: str) -> bool:
    parts = urlparse(url)
    return (parts.scheme == "https" and parts.netloc == HOST
            and not re.search(r"acuse|confirm|aceptar|marcar|constancia.?lectura", folded(url)))


def guardar_adjuntos(page, records: list[dict[str, Any]], carpeta: str, cliente: int) -> int:
    if not carpeta or cliente < 1:
        return 0
    relativo = Path("almacenamiento/sunafil/buzon_sunafil/adjuntos") / str(cliente) / "oficial"
    directorio = Path(carpeta) / relativo
    limite = 16 * 1024 * 1024
    fecha_limite = time.monotonic() + 27
    descargados = 0
    intentos = 0
    directorio.mkdir(mode=0o700, parents=True, exist_ok=True)
    for registro in records:
        enlace = registro.pop("enlace_pdf", "")
        identificador = registro["identificador_externo"]
        if not re.fullmatch(r"SUNAFIL-[a-f0-9]{40}", identificador):
            continue
        nombre = identificador + ".pdf"
        destino = directorio / nombre
        if destino.is_file():
            with destino.open("rb") as prueba:
                if prueba.read(5) == b"%PDF-":
                    registro["archivo"] = (relativo / nombre).as_posix()
                    continue
        if not enlace or not is_official_pdf_link(enlace) or intentos >= 12 or time.monotonic() > fecha_limite:
            continue
        intentos += 1
        respuesta = None
        temporal = None
        try:
            respuesta = page.context.request.get(enlace, timeout=4000, max_redirects=0)
            tipo = respuesta.headers.get("content-type", "").split(";", 1)[0].strip().lower()
            longitud = respuesta.headers.get("content-length", "")
            if (not respuesta.ok or tipo not in {"application/pdf", "application/octet-stream"}
                    or (longitud.isdigit() and int(longitud) > limite)):
                continue
            pdf = respuesta.body()
            if len(pdf) > limite or not pdf.startswith(b"%PDF-"):
                continue
            with NamedTemporaryFile(dir=directorio, prefix=".sunafil-", delete=False) as archivo:
                temporal = Path(archivo.name)
                archivo.write(pdf)
            temporal.replace(destino)
            registro["archivo"] = (relativo / nombre).as_posix()
            descargados += 1
        except Exception:
            sys.stderr.write("SUNAFIL: un PDF no estuvo disponible en esta consulta.\n")
        finally:
            if respuesta is not None:
                respuesta.dispose()
            if temporal is not None:
                temporal.unlink(missing_ok=True)
    return descargados

def descargar_accion_previa(page, registro, raiz, cliente, identificador):
    goto_retry(page, SUNAFIL_PORTADA + "ActosAdministrativos/ListadoRequerimientos")
    if not is_sunafil_session(page):
        raise RuntimeError("Sesión SUNAFIL no reconocida.")
    selector = 'button.btn-danger[type="submit"][id*=":dtAlertas:"]:has(i.fa-file-pdf-o)'
    buscado = clean(registro)
    for _ in range(120):
        page.wait_for_timeout(700)
        filas = page.locator('tr').filter(has=page.locator(selector))
        coincidencias = []
        for fila in filas.all():
            celdas = fila.locator(':scope > td').all_inner_texts()
            if any(clean(c) == buscado for c in celdas):
                coincidencias.append(fila)
        if len(coincidencias) > 1:
            raise RuntimeError("Registro SUNAFIL duplicado.")
        if coincidencias:
            boton = coincidencias[0].locator(selector)
            if boton.count() != 1 or not boton.is_enabled():
                raise RuntimeError("Botón PDF original no identificado.")
            boton.click()
            confirmar = page.locator('form[id="formDescargaDoc"] button[name="formDescargaDoc:btnverDocumento"]')
            try:
                confirmar.wait_for(state="visible", timeout=20000)
            except Exception:
                raise RuntimeError("No apareció la ventana Descargar Documento de SUNAFIL.") from None
            if confirmar.count() != 1 or not confirmar.is_enabled():
                raise RuntimeError("No se identificó el botón Descargar de SUNAFIL.")
            try:
                with page.expect_download(timeout=40000) as descarga:
                    confirmar.click()
            except Exception:
                raise RuntimeError("SUNAFIL no inició la descarga tras pulsar Descargar.") from None
            archivo = descarga.value
            origen = archivo.path()
            if archivo.failure() or not origen or origen.stat().st_size > 16 * 1024 * 1024:
                raise RuntimeError("Descarga SUNAFIL inválida.")
            contenido = origen.read_bytes()
            if not contenido.startswith(b"%PDF-"):
                raise RuntimeError("SUNAFIL no devolvió un PDF válido.")
            relativo = Path("almacenamiento/sunafil/buzon_sunafil/adjuntos") / str(cliente) / "oficial" / (identificador + ".pdf")
            destino = Path(raiz) / relativo
            destino.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
            with NamedTemporaryFile(dir=destino.parent, prefix=".sunafil-", delete=False) as f:
                temporal = Path(f.name)
                f.write(contenido)
            try:
                temporal.replace(destino)
            finally:
                temporal.unlink(missing_ok=True)
            return relativo.as_posix()
        nxt = page.locator("li.paginate_button.next:not(.disabled) a, .dataTables_paginate .next:not(.disabled), .ui-paginator-next:not(.ui-state-disabled)")
        if not nxt.count() or not nxt.first.is_visible():
            break
        nxt.first.click()
    raise RuntimeError("No se encontró el registro en Acciones previas.")

def descargar_pdf_solicitado(page, req: dict[str, Any]) -> dict[str, Any]:
    identificador = clean(req.get("identificador_externo", ""))
    id_documento = int(req.get("id_documento", 0))
    cliente = int(req.get("id_cliente", 0))
    raiz = str(req.get("raiz_archivos", ""))

    if (
        id_documento < 1
        or cliente < 1
        or not raiz
        or not re.fullmatch(r"SUNAFIL-[a-f0-9]{40}", identificador)
    ):
        raise RuntimeError("Documento SUNAFIL no válido.")

    coincidencias = [
        r for r in read_inbox(page)
        if r["identificador_externo"] == identificador
    ]
    if not coincidencias:
        coincidencias = [r for r in read_other_sections(page, [])
                         if r["identificador_externo"] == identificador]

    if not coincidencias:
        return descargar_pdf_legacy(page, req)
    if len(coincidencias) != 1:
        raise RuntimeError("No se identificó la notificación.")

    guardar_adjuntos(page, coincidencias, raiz, cliente)

    archivo = coincidencias[0].get("archivo", "")

    if archivo:
        return {
            "id_documento": id_documento,
            "identificador_externo": identificador,
            "archivo": archivo
        }

    if coincidencias[0].get("categoria") == "ACCIONES_PREVIAS":
        registro = coincidencias[0]["campos_oficiales"].get("Registro", "")
        if not registro:
            raise RuntimeError("Registro oficial no identificado.")
        archivo = descargar_accion_previa(page, registro, raiz, cliente, identificador)
        return {"id_documento": id_documento, "identificador_externo": identificador, "archivo": archivo}
    if coincidencias[0].get("categoria") != "ORIENTACION":
        raise RuntimeError("No se encontró un PDF oficial.")

    url = (
        "https://casillaelectronica.sunafil.gob.pe"
        "/si.inbox/Orientacion/ListadoOrientacionEmpleador"
    )

    goto_retry(page, url)
    page.locator(
    'button[type="submit"][id*=":dtAlertas:"]'
).first.wait_for(state="visible", timeout=20000)
    primera = page.locator(
        '.dataTables_paginate a'
    ).filter(has_text=re.compile(r'^1$'))

    if primera.count() == 1:
        primera.click()
        page.wait_for_timeout(1500)

    encontrado = False

    for _ in range(120):
        filas = page.locator(
    'tr:has(button[type="submit"][id*=":dtAlertas:"])'
).all()
        candidatas = []

        for fila in filas:
            celdas = fila.locator(":scope > td").all_inner_texts()

            if len(celdas) < 5:
                continue

            asunto = clean(celdas[2])
            fecha = parse_date(celdas[1])

            if not fecha:
                continue

            semilla = "|".join((asunto, "", fecha))
            clave = "SUNAFIL-" + hashlib.sha256(
                semilla.encode("utf-8", "ignore")
            ).hexdigest()[:40]

            if (
                clave == identificador
                or (
                    asunto == coincidencias[0]["asunto"]
                    and fecha == coincidencias[0]["fecha_recepcion"]
                )
            ):
                candidatas.append(fila)

        if len(candidatas) > 1:
            raise RuntimeError("Documento duplicado en la bandeja.")

        if len(candidatas) == 1:
            boton = candidatas[0].locator(
                'button[type="submit"][id*=":dtAlertas:"]'
            )

            if boton.count() != 1:
                raise RuntimeError("No se reconoció el botón del documento.")

            boton.click()

            page.wait_for_url(
                "**/Orientacion/DetallePlantillaOrientacionEmpleador",
                timeout=20000
            )

            encontrado = True
            break

        siguiente = page.locator(
            "li.paginate_button.next:not(.disabled) a"
        )

        if siguiente.count() != 1:
            break

        siguiente.click()
        page.wait_for_timeout(1000)

    if not encontrado:
        raise RuntimeError("No se encontró el documento solicitado.")

    if not is_sunafil_session(page):
        raise RuntimeError("La sesión SUNAFIL se perdió.")

    ruc = re.sub(r"\D", "", str(req.get("ruc", "")))

    if not ruc or ruc not in body_text(page):
        raise RuntimeError("No se pudo verificar el RUC del documento.")

    page.wait_for_load_state("domcontentloaded")

    page.wait_for_function(
        """() => Array.from(document.images).every(
            img => img.complete
        )""",
        timeout=15000
    )

    page.evaluate("""
        () => {
            const contenedor = Array.from(
                document.querySelectorAll('.table-responsive')
            ).find(el =>
                el.innerText.includes('Estimado empleador')
            );

            if (!contenedor) {
                throw new Error('No se encontró el contenido SUNAFIL');
            }

            const contenido = contenedor.cloneNode(true);

            document.body.replaceChildren(contenido);
            document.body.className = '';

            contenido.id = 'documento-sunafil';
        }
    """)

    page.add_style_tag(content="""
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 760px !important;
            height: auto !important;
            background: white !important;
            overflow: visible !important;
        }

        #documento-sunafil {
            width: 760px !important;
            height: auto !important;
            margin: 0 auto !important;
            padding: 0 !important;
            overflow: visible !important;
        }

        #documento-sunafil > table {
            width: 760px !important;
            max-width: 100% !important;
            margin: 0 auto !important;
        }

        table {
            max-width: 100% !important;
        }

        td, tr {
            height: auto !important;
            max-width: 100% !important;
        }

        img {
            max-width: 100% !important;
            height: auto !important;
        }

        button, input, .btn {
            display: none !important;
        }
    """)

    relativo = (
        Path("almacenamiento/sunafil/buzon_sunafil/adjuntos")
        / str(cliente)
        / "oficial"
        / (identificador + ".pdf")
    )

    destino = Path(raiz) / relativo
    destino.parent.mkdir(parents=True, exist_ok=True)


    page.emulate_media(media="screen")

    page.wait_for_function("""
        () => Array.from(document.images).every(
            img => img.complete && img.naturalWidth > 0
        )
    """, timeout=15000)

    medidas = page.locator("#documento-sunafil").evaluate("""
        el => ({
            ancho: Math.ceil(Math.max(
                el.scrollWidth, el.offsetWidth
            )),
            alto: Math.ceil(Math.max(
                el.scrollHeight, el.offsetHeight
            ))
        })
    """)

    pdf = page.pdf(
        width=f"{medidas['ancho'] + 40}px",
        height=f"{medidas['alto'] + 100}px",
        scale=1,
        print_background=True,
        prefer_css_page_size=False,
        display_header_footer=True,
        header_template="<span></span>",
        footer_template="""
            <div style="font-size:9px;width:100%;text-align:center;">
                Copia del contenido SUNAFIL generada por ASESCON
            </div>
        """,
        margin={
            "top": "5mm",
            "bottom": "10mm",
            "left": "5mm",
            "right": "5mm"
        }
    )





    if not pdf.startswith(b"%PDF-") or len(pdf) > 15 * 1024 * 1024:
        raise RuntimeError("No se generó un PDF válido.")

    with NamedTemporaryFile(
        dir=destino.parent,
        delete=False
    ) as temporal:
        temporal.write(pdf)
        ruta_temporal = Path(temporal.name)

    ruta_temporal.replace(destino)

    return {
        "id_documento": id_documento,
        "identificador_externo": identificador,
        "archivo": relativo.as_posix()
    }

def read_legacy_inbox(page, requested: str = "", download=None) -> list[dict[str, Any]]:
    pages = safe_internal_links(page)
    if not any(section == "ORIENTACION" for section, _ in pages):
        pages.append(("ORIENTACION", urljoin(SUNAFIL_PORTADA, "Orientacion/ListadoOrientacionEmpleador")))
    records = []
    recognized = False
    for section, url in pages:
        if page.url != url:
            goto_retry(page, url, attempts=1)
            page.wait_for_timeout(700)
        if not is_sunafil_session(page):
            raise RuntimeError("La sesión SUNAFIL se perdió al consultar la bandeja.")
        signatures = set()
        for _ in range(120):
            deadline = time.monotonic() + 10
            while True:
                text = folded(body_text(page))
                batch = table_records(page, section, requested, download)
                if not batch:
                    batch = generic_records(page, section)
                empty = bool(re.search(r"sin (?:notificaciones|registros|comunicaciones)|no (?:hay|tiene|existen) (?:notificaciones|registros|comunicaciones)|ningun registro|no data available|no se encontraron registros", text))
                if batch or empty or time.monotonic() >= deadline:
                    break
                page.wait_for_timeout(500)
            if not batch and not empty:
                raise RuntimeError("No se reconoció una página de la bandeja SUNAFIL. No se guardaron resultados parciales.")
            if batch or empty:
                recognized = True
            signature = tuple(r["identificador_externo"] for r in batch)
            if signature in signatures and batch:
                raise RuntimeError("La paginación SUNAFIL no avanzó. No se guardaron resultados parciales.")
            signatures.add(signature)
            records.extend(batch)

            next_button = None
            for f in frames(page):
                for selector in ('li.paginate_button.next:not(.disabled) a', '.dataTables_paginate .next:not(.disabled)', 'button[aria-label="Siguiente página"]', 'button[aria-label="Next page"]'):
                    loc = f.locator(selector)
                    if loc.count() and loc.first.is_visible() and loc.first.is_enabled() and loc.first.get_attribute("aria-disabled") != "true":
                        next_button = loc.first
                        break
                if next_button is not None:
                    break
            if next_button is None:
                break
            next_button.click()
            page.wait_for_timeout(900)
        else:
            raise RuntimeError("La bandeja supera el límite de 120 páginas. No se guardaron resultados parciales.")
    if not recognized:
        raise RuntimeError("La sesión abrió, pero no se reconoció la bandeja ni un aviso de bandeja vacía. No se guardaron resultados.")
    unicas = {}
    for registro in records:
        identificador = registro["identificador_externo"]
        if identificador not in unicas or registro.get("enlace_pdf"):
            unicas[identificador] = registro
    return list(unicas.values())

def descargar_pdf_legacy(page, req: dict[str, Any]) -> dict[str, Any]:
    identificador = clean(req.get("identificador_externo", ""))
    id_documento = int(req.get("id_documento", 0))
    cliente = int(req.get("id_cliente", 0))
    raiz = str(req.get("raiz_archivos", ""))

    if (
        id_documento < 1
        or cliente < 1
        or not raiz
        or not re.fullmatch(r"SUNAFIL-[a-f0-9]{40}", identificador)
    ):
        raise RuntimeError("Documento SUNAFIL no válido.")

    # Keep the downloaded file private until the matching document is unique.
    from tempfile import TemporaryDirectory
    with TemporaryDirectory() as temp:
        pending = Path(temp) / "adjunto.pdf"

        def download_row(row):
            controls = row.locator("a,button,input[type=button],input[type=submit]").all()
            matches = []
            for control in controls:
                label = folded(control.evaluate("""el => [el.innerText, el.title,
                    el.getAttribute('aria-label'), el.value,
                    el.querySelector('img')?.alt].filter(Boolean).join(' ')"""))
                if re.search(r"descargar|download", label) and not re.search(r"acuse|constancia|confirm|excel|zip", label):
                    matches.append(control)
            if len(matches) != 1 or not matches[0].is_visible():
                return
            try:
                with page.expect_download(timeout=7000) as event:
                    matches[0].click(timeout=5000)
                item = event.value
                source = item.url.removeprefix("blob:")
                if urlparse(source).netloc == HOST and urlparse(source).scheme == "https":
                    item.save_as(pending)
                item.delete()
            except Exception:
                pending.unlink(missing_ok=True)

        coincidencias = [r for r in read_legacy_inbox(page, identificador, download_row)
                         if r["identificador_externo"] == identificador]
        if len(coincidencias) == 1 and pending.is_file() and pending.stat().st_size <= 16 * 1024 * 1024:
            pdf = pending.read_bytes()
            if pdf.startswith(b"%PDF-"):
                relative = Path("almacenamiento/sunafil/buzon_sunafil/adjuntos") / str(cliente) / "oficial" / (identificador + ".pdf")
                target = Path(raiz) / relative
                target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
                with NamedTemporaryFile(dir=target.parent, delete=False) as handle:
                    handle.write(pdf)
                Path(handle.name).replace(target)
                return {"id_documento": id_documento, "identificador_externo": identificador, "archivo": relative.as_posix()}

    if len(coincidencias) != 1:
        raise RuntimeError("No se identificó la notificación.")

    guardar_adjuntos(page, coincidencias, raiz, cliente)

    archivo = coincidencias[0].get("archivo", "")

    if archivo:
        return {
            "id_documento": id_documento,
            "identificador_externo": identificador,
            "archivo": archivo
        }

    if coincidencias[0].get("categoria") != "ORIENTACION":
        raise RuntimeError("No se encontró un PDF oficial.")

    url = (
        "https://casillaelectronica.sunafil.gob.pe"
        "/si.inbox/Orientacion/ListadoOrientacionEmpleador"
    )

    goto_retry(page, url)
    page.locator(
    'button[type="submit"][id*=":dtAlertas:"]'
).first.wait_for(state="visible", timeout=20000)
    primera = page.locator(
        '.dataTables_paginate a'
    ).filter(has_text=re.compile(r'^1$'))

    if primera.count() == 1:
        primera.click()
        page.wait_for_timeout(1500)

    encontrado = False

    for _ in range(120):
        filas = page.locator(
    'tr:has(button[type="submit"][id*=":dtAlertas:"])'
).all()
        candidatas = []

        for fila in filas:
            celdas = fila.locator(":scope > td").all_inner_texts()

            if len(celdas) < 5:
                continue

            asunto = clean(celdas[2])
            fecha = parse_date(celdas[1])

            if not fecha:
                continue

            semilla = "|".join((asunto, "", fecha))
            clave = "SUNAFIL-" + hashlib.sha256(
                semilla.encode("utf-8", "ignore")
            ).hexdigest()[:40]

            if (
                clave == identificador
                or (
                    asunto == coincidencias[0]["asunto"]
                    and fecha == coincidencias[0]["fecha_recepcion"]
                )
            ):
                candidatas.append(fila)

        if len(candidatas) > 1:
            raise RuntimeError("Documento duplicado en la bandeja.")

        if len(candidatas) == 1:
            boton = candidatas[0].locator(
                'button[type="submit"][id*=":dtAlertas:"]'
            )

            if boton.count() != 1:
                raise RuntimeError("No se reconoció el botón del documento.")

            boton.click()

            page.wait_for_url(
                "**/Orientacion/DetallePlantillaOrientacionEmpleador",
                timeout=20000
            )

            encontrado = True
            break

        siguiente = page.locator(
            "li.paginate_button.next:not(.disabled) a"
        )

        if siguiente.count() != 1:
            break

        siguiente.click()
        page.wait_for_timeout(1000)

    if not encontrado:
        raise RuntimeError("No se encontró el documento solicitado.")

    if not is_sunafil_session(page):
        raise RuntimeError("La sesión SUNAFIL se perdió.")

    ruc = re.sub(r"\D", "", str(req.get("ruc", "")))

    if not ruc or ruc not in body_text(page):
        raise RuntimeError("No se pudo verificar el RUC del documento.")

    page.wait_for_load_state("domcontentloaded")

    page.wait_for_function(
        """() => Array.from(document.images).every(
            img => img.complete
        )""",
        timeout=15000
    )

    page.evaluate("""
        () => {
            const contenedor = Array.from(
                document.querySelectorAll('.table-responsive')
            ).find(el =>
                el.innerText.includes('Estimado empleador')
            );

            if (!contenedor) {
                throw new Error('No se encontró el contenido SUNAFIL');
            }

            const contenido = contenedor.cloneNode(true);

            document.body.replaceChildren(contenido);
            document.body.className = '';

            contenido.id = 'documento-sunafil';
        }
    """)

    page.add_style_tag(content="""
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 760px !important;
            height: auto !important;
            background: white !important;
            overflow: visible !important;
        }

        #documento-sunafil {
            width: 760px !important;
            height: auto !important;
            margin: 0 auto !important;
            padding: 0 !important;
            overflow: visible !important;
        }

        #documento-sunafil > table {
            width: 760px !important;
            max-width: 100% !important;
            margin: 0 auto !important;
        }

        table {
            max-width: 100% !important;
        }

        td, tr {
            height: auto !important;
            max-width: 100% !important;
        }

        img {
            max-width: 100% !important;
            height: auto !important;
        }

        button, input, .btn {
            display: none !important;
        }
    """)

    relativo = (
        Path("almacenamiento/sunafil/buzon_sunafil/adjuntos")
        / str(cliente)
        / "oficial"
        / (identificador + ".pdf")
    )

    destino = Path(raiz) / relativo
    destino.parent.mkdir(parents=True, exist_ok=True)


    page.emulate_media(media="screen")

    page.wait_for_function("""
        () => Array.from(document.images).every(
            img => img.complete && img.naturalWidth > 0
        )
    """, timeout=15000)

    medidas = page.locator("#documento-sunafil").evaluate("""
        el => ({
            ancho: Math.ceil(Math.max(
                el.scrollWidth, el.offsetWidth
            )),
            alto: Math.ceil(Math.max(
                el.scrollHeight, el.offsetHeight
            ))
        })
    """)

    pdf = page.pdf(
        width=f"{medidas['ancho'] + 40}px",
        height=f"{medidas['alto'] + 100}px",
        scale=1,
        print_background=True,
        prefer_css_page_size=False,
        display_header_footer=True,
        header_template="<span></span>",
        footer_template="""
            <div style="font-size:9px;width:100%;text-align:center;">
                Copia del contenido SUNAFIL generada por ASESCON
            </div>
        """,
        margin={
            "top": "5mm",
            "bottom": "10mm",
            "left": "5mm",
            "right": "5mm"
        }
    )





    if not pdf.startswith(b"%PDF-") or len(pdf) > 15 * 1024 * 1024:
        raise RuntimeError("No se generó un PDF válido.")

    with NamedTemporaryFile(
        dir=destino.parent,
        delete=False
    ) as temporal:
        temporal.write(pdf)
        ruta_temporal = Path(temporal.name)

    ruta_temporal.replace(destino)

    return {
        "id_documento": id_documento,
        "identificador_externo": identificador,
        "archivo": relativo.as_posix()
    }

def main() -> None:
    try:
        req = json.loads(sys.stdin.read() or "{}")
        modo = clean(req.get("modo", "bandeja")).lower()
        ruc = re.sub(r"\D", "", str(req.get("ruc", "")))
        usuario = clean(req.get("usuario_sol", ""))
        clave = str(req.get("clave_sol", ""))
        if not re.fullmatch(r"\d{11}", ruc):
            raise RuntimeError("El RUC debe contener exactamente 11 dígitos.")
        if not usuario or not clave:
            raise RuntimeError("Usuario SOL y Clave SOL son obligatorios.")

        try:
            from playwright.sync_api import sync_playwright
        except Exception:
            out({"ok": False, "codigo": "PY_PLAYWRIGHT_NO_DISPONIBLE", "mensaje": "Este intérprete de Python no tiene Playwright disponible."})
            return

        with sync_playwright() as p:
            try:
                launch = {"headless": True, "args": ["--no-sandbox", "--disable-dev-shm-usage"]}
                if os.environ.get("ASESCON_CHROMIUM"):
                    launch["executable_path"] = os.environ["ASESCON_CHROMIUM"]
                browser = p.chromium.launch(**launch)
            except Exception:
                raise RuntimeError("No se pudo iniciar el Chromium compartido. Revisa el entorno de Buzón SOL.") from None
            context = browser.new_context(locale="es-PE")
            page = context.new_page()
            page.set_default_timeout(15000)
            try:
                page = login(page, ruc, usuario, clave)
                data: dict[str, Any] = {
                    "ruc": ruc,
                    "sunafil_validado": True,
                    "fuente": "CASILLA_ELECTRONICA_SUNAFIL",
                    "notificaciones": [],
                }
                if modo == "bandeja":
                    data["categorias_no_verificadas"] = []
                    data["notificaciones"] = read_inbox(page, data["categorias_no_verificadas"])
                    data["cantidad"] = len(data["notificaciones"])
                    if os.environ.get("ASESCON_PRECARGAR_PDF", "0") == "1":
                        try:
                            data["pdf_descargados"] = guardar_adjuntos(
                                page, data["notificaciones"], str(req.get("raiz_archivos", "")), int(req.get("id_cliente", 0))
                            )
                        except OSError:
                            sys.stderr.write("SUNAFIL: no se pudo guardar uno o más PDF en el volumen privado.\n")
                    for notificacion in data["notificaciones"]:
                        notificacion.pop("enlace_pdf", None)
                elif modo == "pdf":
                    data["documento"] = descargar_pdf_solicitado(page, req)
            finally:
                context.close()
                browser.close()

        out({"ok": True, "data": data})
    except Exception as exc:
                                                                                     
        message = clean(exc) if isinstance(exc, RuntimeError) else "El conector SUNAFIL encontró un error de navegación o lectura."
        for value in (locals().get("clave", ""), locals().get("usuario", "")):
            if value:
                message = message.replace(value, "[OCULTO]")
                                                                         
                                                                                 
        sys.stderr.write("SUNAFIL_ACCESO_ERROR: " + message[:240] + "\n")
        out({"ok": False, "codigo": "SUNAFIL_ACCESO_ERROR", "mensaje": message[:240]})
        sys.exit(1)

if __name__ == "__main__":
    main()
