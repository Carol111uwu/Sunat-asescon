                                                                              

                                                                                            
                                  
   
from __future__ import annotations

import hashlib
import os
import time
import unicodedata
import json
import re
import sys
from datetime import datetime
from typing import Any
from urllib.parse import urljoin, urlparse

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
    return (url.scheme == "https" and url.hostname == HOST
            and find_input(page, ["input[type=password]"]) is None
            and bool(re.search(r"cerrar sesion|salir|logout", text))
            and bool(re.search(r"bandeja|notificaciones|comunicaciones", text)))


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
            key = (text + " " + href).lower()
            if not re.fullmatch(r"(?:bandeja(?: de entrada)?|notificaciones|comunicaciones|mensajes|alertas)(?:\s*\(\d+\))?", text, re.I):
                continue
                                                                            
            if any(k in key for k in ("detalle", "verdocumento", "archivo", ".pdf", "acuse", "confirm")):
                continue
            if href in seen:
                continue
            seen.add(href)
            links.append((text, href))
    return links[:4]

def table_records(page, section: str) -> list[dict[str, Any]]:
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

        for table in tables:
            headers = [folded(x) for x in table.get("headers", [])]
            if not any(any(k in h for k in ("asunto", "documento", "descripcion", "materia")) for h in headers):
                continue
            for row in table.get("rows", []):
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
                                                                              
                        parsed = urlparse(href)
                        if parsed.scheme == "https" and parsed.hostname == HOST and not parsed.query:
                            pdf = href

                ident = hashlib.sha256(identifier_seed.encode("utf-8", "ignore")).hexdigest()[:40]
                records.append({
                    "identificador_externo": "SUNAFIL-" + ident,
                    "asunto": asunto[:255],
                    "tipo_documento": (tipo or section)[:100],
                    "categoria": (section or "SUNAFIL")[:80],
                    "fecha_recepcion": fecha_iso,
                    "prioridad": "NORMAL",
                    "archivo": pdf[:255],
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

def read_inbox(page) -> list[dict[str, Any]]:
    pages = [("SUNAFIL", page.url)] + safe_internal_links(page)
    records = []
    recognized = False
    for section, url in pages:
        if page.url != url:
            goto_retry(page, url, attempts=1)
            page.wait_for_timeout(700)
        if not is_sunafil_session(page):
            raise RuntimeError("La sesión SUNAFIL se perdió al consultar la bandeja.")
        signatures = set()
        for _ in range(30):
            deadline = time.monotonic() + 10
            while True:
                text = folded(body_text(page))
                batch = table_records(page, section)
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
            raise RuntimeError("La bandeja supera el límite de 30 páginas. No se guardaron resultados parciales.")
    if not recognized:
        raise RuntimeError("La sesión abrió, pero no se reconoció la bandeja ni un aviso de bandeja vacía. No se guardaron resultados.")
    return list({r["identificador_externo"]: r for r in records}.values())

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
                    data["notificaciones"] = read_inbox(page)
                    data["cantidad"] = len(data["notificaciones"])
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
