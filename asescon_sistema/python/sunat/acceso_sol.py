                                                                                   
from __future__ import annotations
import re
import time
from urllib.parse import urlsplit

BUZON_URLS=(
                                                                     
    'https://e-menu.sunat.gob.pe/cl-ti-itmenucabina/MenuInternet.htm?exe=buzon',
    'https://e-menu.sunat.gob.pe/cl-ti-itmenu/MenuInternet.htm?exe=buzon',
                                                                            
    'https://e-menu.sunat.gob.pe/cl-ti-itmenu2/MenuInternetPlataforma.htm?exe=buzon',
)
ENTRADAS_SOL=BUZON_URLS+('https://e-menu.sunat.gob.pe/cl-ti-itmenu/MenuInternet.htm',)

class ErrorAccesoSol(RuntimeError):
    def __init__(self, codigo: str, mensaje: str, causa: str | None = None):
        super().__init__(mensaje)
        self.codigo = codigo
        self.causa = causa

def clean(v)->str:return re.sub(r'\s+',' ',str(v or '')).strip()
def frames(page):
    yield page
    for f in page.frames:
        if f!=page.main_frame:yield f
def body_text(page)->str:
    partes=[]
    for f in frames(page):
        try:partes.append(f.locator('body').inner_text(timeout=1500))
        except Exception:pass
    return '\n'.join(partes)
def find_input(page,selectors):
    for f in frames(page):
        for selector in selectors:
            try:
                for elemento in f.locator(selector).all():
                    if elemento.is_visible() and elemento.is_enabled():return elemento
            except Exception:pass
    return None
def por_etiqueta(page,patron):
    for f in frames(page):
        for consulta in (lambda:f.get_by_label(re.compile(patron,re.I)),lambda:f.get_by_role('textbox',name=re.compile(patron,re.I))):
            try:
                for x in consulta().all():
                    if x.is_visible() and x.is_enabled():return x
            except Exception:pass
    return None
def click_text(page,patterns)->bool:
    for f in frames(page):
        if not origen_sol(f.url):
            continue
        for patron in patterns:
            for role in ('button','link','tab'):
                try:
                    for x in f.get_by_role(role,name=re.compile(patron,re.I)).all():
                        if x.is_visible():
                            x.click(timeout=2000)
                            return True
                except Exception:pass
    return False

PATRON_BANDEJA = re.compile(
    r'(?:lista|bandeja|buz[oó]n)\s+(?:de\s+)?(?:notificaciones|comunicaciones)|'
    r'(?:notificaciones|comunicaciones)\s+recibidas', re.I
)
PATRON_VERIFICACION = re.compile(
    r'no soy un robot|verificaci[oó]n interactiva|resuelve.{0,30}captcha', re.I
)
PATRON_CREDENCIALES = re.compile(
    r'(usuario|clave|credencial).{0,100}(incorrect|inv[aá]lid|no existe)', re.I
)


def origen_sol(url: str) -> bool:
    origen = urlsplit(url)
    host = origen.hostname or ''
    return origen.scheme == 'https' and (host == 'sunat.gob.pe' or host.endswith('.sunat.gob.pe'))


def bandeja_visible(page) -> bool:
    for marco in frames(page):
        if not origen_sol(marco.url):
            continue
        try:
            if marco.locator('#listaMensajes > li:visible').count():
                return True
            texto = clean(marco.locator('body').inner_text(timeout=1000))
            # Algunos módulos no usan encabezados semánticos.
            if not PATRON_BANDEJA.search(texto):
                continue
            if marco.locator(
                'table:visible tbody tr:visible td, [role=row]:visible, .mat-row:visible, '
                '.p-datatable-tbody > tr:visible, .list-group-item:visible, [role=listitem]:visible, '
                '.notification-item:visible, .inbox-item:visible, .mensaje-item:visible'
            ).count():
                return True
            if re.search(
                r'no\s+(?:hay|tiene)\s+(?:notificaciones|mensajes|documentos)|'
                r'(?:sin|0)\s+(?:notificaciones|mensajes|documentos)', texto, re.I
            ):
                return True
        except Exception:
            continue
    return False


def abrir_bandeja_sol(page)->bool:
    return bandeja_visible(page) or abrir_menu_buzon(page)


def abrir_menu_buzon(page) -> bool:
    return click_text(page, [
        r'^buz[oó]n(?:\s+sol|\s+electr[oó]nico)?$',
        r'^mis\s+notificaciones$',
        r'^bandeja(?:\s+de\s+entrada)?$',
        r'^comunicaciones\s+y\s+notificaciones$',
    ])


def esperar_bandeja_sol(page, segundos: int, paginas_previas: set, menus_abiertos: set):
    limite = time.monotonic() + segundos
    while time.monotonic() < limite:
        paginas = [p for p in page.context.pages if not p.is_closed()
                   and (p is page or p not in paginas_previas) and origen_sol(p.url)]
        for candidata in reversed(paginas):
            if bandeja_visible(candidata):
                return candidata
            texto = body_text(candidata)
            if PATRON_VERIFICACION.search(texto):
                raise ErrorAccesoSol('SOL_VERIFICACION_INTERACTIVA',
                                     'SUNAT solicita una verificación interactiva; complétala en el portal oficial.')
            if PATRON_CREDENCIALES.search(texto):
                raise ErrorAccesoSol('SOL_CREDENCIALES_RECHAZADAS',
                                     'SOL rechazó el RUC, usuario o clave; los datos guardados no se modificaron.')
            if find_input(candidata, ['input[type="password"]']) is not None:
                continue
            if candidata not in menus_abiertos and abrir_menu_buzon(candidata):
                menus_abiertos.add(candidata)
        if not page.is_closed():
            page.wait_for_timeout(300)
        elif paginas:
            paginas[-1].wait_for_timeout(300)
        else:
            break
    return None


def _codigo_navegacion(error:Exception)->str:
                                                                               
    if isinstance(error,ErrorAccesoSol):return error.codigo
    detalle=str(error).lower()
    for marca,codigo in (
        ('err_name_not_resolved','DNS'),
        ('err_cert_','TLS_CERTIFICADO'),
        ('err_ssl_','TLS_SSL'),
        ('err_connection_refused','CONEXION_RECHAZADA'),
        ('err_connection_reset','CONEXION_REINICIADA'),
        ('err_timed_out','TIEMPO_AGOTADO'),
        ('timeout','TIEMPO_AGOTADO'),
        ('err_empty_response','RESPUESTA_VACIA'),
        ('err_http2_protocol_error','PROTOCOLO_HTTP2'),
        ('err_','RED_NAVEGADOR'),
    ):
        if marca in detalle:return codigo
    return 'ERROR_NAVEGACION'

def goto_retry(page,url:str,attempts:int=2,timeout:int=16000)->None:
    fallos=[]
    for _ in range(attempts):
        try:
            respuesta=page.goto(url,wait_until='domcontentloaded',timeout=timeout)
            if respuesta is not None and respuesta.status>=400:
                fallos.append('HTTP_'+str(int(respuesta.status)))
                continue
            return
        except Exception as error:
            fallos.append(_codigo_navegacion(error))
    causa=fallos[-1] if fallos else 'SIN_RESPUESTA'
    raise ErrorAccesoSol('SOL_PORTAL_NO_DISPONIBLE',
        'No se pudo cargar la entrada oficial de SOL desde este servidor. '
        f'Causa de navegación: {causa}. Los registros anteriores se conservan.', causa=causa)
def campos(page):
    ruc=find_input(page,['#txtRuc','#ruc','input[name*="ruc" i]','input[id*="ruc" i]','input[placeholder*="ruc" i]']) or por_etiqueta(page,r'\bruc\b')
    usuario=find_input(page,['#txtUsuario','#txtUser','#usuario','input[name*="usuario" i]','input[id*="usuario" i]','input[autocomplete="username"]']) or por_etiqueta(page,r'usuario|user')
    clave=find_input(page,['input[type="password"]']) or por_etiqueta(page,r'clave|contrase[nñ]a|password')
    return ruc,usuario,clave
def login(page,ruc:str,usuario:str,clave:str):
    formulario=None;error_carga=None;hubo_pagina=False
    for url in ENTRADAS_SOL:
        try:goto_retry(page,url,1,18000)
        except ErrorAccesoSol as e:error_carga=e;continue
        hubo_pagina=True
        click_text(page,[r'^ruc$',r'ingresa\s+por\s+ruc',r'ingresar\s+con\s+ruc'])
        limite=time.monotonic()+12
        while time.monotonic()<limite:
            ri,ui,pwd=campos(page)
            if ri is not None and ui is not None and pwd is not None:formulario=(ri,ui,pwd);break
            texto=body_text(page)
            if PATRON_VERIFICACION.search(texto):
                raise ErrorAccesoSol('SOL_VERIFICACION_INTERACTIVA','SUNAT solicita una verificación interactiva; complétala en el portal oficial.')
            page.wait_for_timeout(300)
        if formulario:break
    if not formulario:
        if hubo_pagina:
            raise ErrorAccesoSol('SOL_FORMULARIO_NO_DISPONIBLE',
                'SUNAT respondió, pero no mostró un formulario reconocible de RUC, usuario y clave. '
                'Revisar redirección, cambios de interfaz o verificaciones interactivas.')
        if error_carga:raise error_carga
        raise ErrorAccesoSol('SOL_FORMULARIO_NO_DISPONIBLE','SOL no mostró un formulario reconocible de RUC, usuario y clave. Puede haber una redirección o carga incompleta; esto no indica que tus credenciales hayan cambiado.')
    if not origen_sol(page.url):
        raise ErrorAccesoSol('SOL_ORIGEN_INVALIDO','El formulario no pertenece a un dominio HTTPS oficial de SUNAT.')
    ri,ui,pwd=formulario
    for elemento in formulario:
        if not origen_sol(elemento.evaluate('(e)=>e.ownerDocument.location.href')):
            raise ErrorAccesoSol('SOL_ORIGEN_INVALIDO','El campo de acceso no pertenece a un dominio HTTPS oficial de SUNAT.')
    paginas_previas = set(page.context.pages)
    menus_abiertos = set()
    ri.fill(ruc);ui.fill(usuario);pwd.fill(clave)
    if not click_text(page,[r'iniciar\s*sesi[oó]n',r'^ingresar$',r'^entrar$']):pwd.press('Enter')

    bandeja = esperar_bandeja_sol(page, 20, paginas_previas, menus_abiertos)
    if bandeja is not None:
        return bandeja

    candidatas = [p for p in page.context.pages if not p.is_closed()
                  and (p is page or p not in paginas_previas) and origen_sol(p.url)]
    sesion = next((p for p in reversed(candidatas)
                   if find_input(p, ['input[type="password"]']) is None), None)
    if sesion is None:
        raise ErrorAccesoSol('SOL_AUTENTICACION_INCOMPLETA',
                             'SUNAT no terminó el acceso. Revisa si el portal solicita una verificación o muestra un aviso.')

    causas = []
    for url in BUZON_URLS:
        try:
            goto_retry(sesion, url, 1, 15000)
        except ErrorAccesoSol as error:
            causas.append(error.causa or error.codigo)
            continue
        bandeja = esperar_bandeja_sol(sesion, 6, paginas_previas, set())
        if bandeja is not None:
            return bandeja
    detalle = ' Último error de navegación: '+causas[-1]+'.' if causas else ''
    raise ErrorAccesoSol('SOL_BANDEJA_NO_DISPONIBLE',
        'SUNAT no mostró una bandeja reconocible después de iniciar el acceso.'+detalle+' Se conservan los documentos anteriores.')
