                                                                  
from __future__ import annotations
import os,re,time
from pathlib import Path
from urllib.parse import urlsplit,urljoin
from acceso_sol import clean,frames,body_text,ErrorAccesoSol
from navegacion_buzon_sol import siguiente_pagina_sol
from descarga_autenticada_sol import descargar_pdf
from consultar_buzon_sol import identificador as construir_identificador, identificador_desambiguado, parse_date

def origen_oficial(url:str)->bool:
    if url.startswith('blob:'):url=url[5:]
    u=urlsplit(url)
    return u.scheme=='https' and (u.hostname=='sunat.gob.pe' or (u.hostname or '').endswith('.sunat.gob.pe'))

def _identidad_fila_tabla(item, derivada: bool = False) -> str:
                                                                                  
    try:
        datos = item.evaluate("""el => {
          const tabla = el.closest('table');
          const normalizar = x => (x || '').replace(/\\s+/g, ' ').trim();
          return {
            headers: Array.from((tabla && tabla.querySelectorAll('thead th')) || []).map(x => normalizar(x.innerText)),
            cells: Array.from(el.querySelectorAll('td')).map(x => normalizar(x.innerText)),
            atributos: Object.fromEntries(Array.from(el.attributes).filter(a=>a.name.startsWith('data-')).map(a=>[a.name,a.value])),
            links: Array.from(el.querySelectorAll('a[href]')).map(a => ({text: normalizar(a.innerText), href: a.href || '', atributos: Object.fromEntries(Array.from(a.attributes).filter(x=>x.name.startsWith('data-')).map(x=>[x.name,x.value]))}))
          };
        }""")
    except Exception:
        return ''
    headers = [clean(x).lower() for x in datos.get('headers', [])]
    cells = [clean(x) for x in datos.get('cells', [])]
    if not cells:
        return ''
    joined = ' | '.join(cells)

    def cell(*keys):
        for i, encabezado in enumerate(headers):
            if any(k in encabezado for k in keys) and i < len(cells):
                return cells[i]
        return ''

    asunto = cell('asunto', 'descrip', 'mensaje', 'documento', 'materia')
    tipo = cell('tipo', 'documento')
    fecha = cell('fecha de notific', 'fecha notific', 'fecha recep', 'fecha documento', 'fecha emisión')
    if not fecha:
        fecha = next((cells[i] for i, h in enumerate(headers)
                      if 'fecha' in h and not any(k in h for k in ('consulta', 'lectura', 'revis')) and i < len(cells)), '')
    if not asunto:
        candidatos = [c for c in cells if len(c) > 8 and not re.fullmatch(r'\d+', c)]
        asunto = max(candidatos, key=len) if candidatos else joined[:255]
    fecha_iso = parse_date(fecha or joined)
    clave, _ = construir_identificador(asunto, tipo or 'Notificación SOL', fecha_iso, datos.get('links'),datos.get('atributos'))
    if derivada:
        return identificador_desambiguado(clave, {
            'asunto': asunto[:255], 'tipo_documento': (tipo or 'Notificación SOL')[:100],
            'fecha_recepcion': fecha_iso, 'observaciones': joined,
        })
    return clave


def _identidad_fila_generica(item, derivada: bool = False) -> str:
                                                                                
    try:
        datos=item.evaluate("""el=>({texto:el.innerText||'',atributos:Object.fromEntries(Array.from(el.attributes).filter(x=>x.name.startsWith('data-')).map(x=>[x.name,x.value])),links:Array.from(el.querySelectorAll('a[href]')).map(a=>({href:a.href||'',atributos:Object.fromEntries(Array.from(a.attributes).filter(x=>x.name.startsWith('data-')).map(x=>[x.name,x.value]))}))})""")
        texto = clean(datos.get('texto'))
    except Exception:
        return ''
    if len(texto) < 20 or not re.search(r'notific|resoluci[oó]n|orden|esquela|carta|comunicaci[oó]n|coactiv|multa', texto, re.I):
        return ''
    contenido = re.sub(r'\b(?:no\s+le[ií]do|le[ií]do|revisado|no\s+revisado)\b', '', texto, flags=re.I)
    clave, _ = construir_identificador(contenido, 'Notificación SOL', parse_date(texto),datos.get('links'),datos.get('atributos'))
    if derivada:
        return identificador_desambiguado(clave, {
            'asunto': contenido[:255], 'tipo_documento': 'Notificación SOL',
            'fecha_recepcion': parse_date(texto), 'observaciones': texto,
        })
    return clave


def _identidad_fila_mixta(item, derivada: bool = False) -> str:
                                                                                       
    return _identidad_fila_tabla(item, derivada) or _identidad_fila_generica(item, derivada)


def _clave_documento_fuerte(valor: str) -> str:
                                                                                     
    return re.sub(r'[^A-Z0-9]', '', str(valor or '').upper())


def claves_fuertes_documento(solicitud: dict) -> list[str]:
                                                                                         
    encontrados = []
                                                                              
    for fuente in (solicitud.get('asunto', ''), solicitud.get('observaciones', '')):
        texto = clean(fuente)
        for coincidencia in re.finditer(r'(?<![A-Z0-9])([A-Z0-9][A-Z0-9/-]{9,})(?![A-Z0-9])', texto, re.I):
            token = coincidencia.group(1)
            normalizado = _clave_documento_fuerte(token)
            tiene_letras = bool(re.search(r'[A-Z]', normalizado))
            tiene_digitos = bool(re.search(r'\d', normalizado))
                                                                                                   
            if not tiene_digitos:
                continue
            if (tiene_letras and len(normalizado) >= 10) or (normalizado.isdigit() and len(normalizado) >= 15):
                if normalizado not in encontrados:
                    encontrados.append(normalizado)
    return encontrados[:12]


def buscar_por_clave_fuerte(page, claves: list[str]):
                                                                                          
    if not claves:
        return []
    selectores = ('table tbody tr', '[role="row"]', '.mat-row', '.p-datatable-tbody > tr',
                  '.list-group-item', '[role="listitem"]', '.mat-list-item',
                  '.notification-item', '.inbox-item', '.mensaje-item')
    for clave in claves:
        encontrados = {}
        for marco in frames(page):
            for selector in selectores:
                locales = []
                try:
                    for item in marco.locator(selector).all():
                        if not item.is_visible():
                            continue
                        texto = _clave_documento_fuerte(item.inner_text(timeout=1000))
                        if clave in texto:
                            locales.append(item)
                except Exception:
                    continue
                if locales:
                    for item in locales:
                        llave = _clave_elemento(item)
                        encontrados[llave or f'{marco.url}|{selector}|{len(encontrados)}'] = item
                                                                                         
                    break
        if encontrados:
            return list(encontrados.values())
    return []


def _clave_elemento(item) -> str:
                                                                                                     
    try:
        return str(item.evaluate("""el => {
          const d = el.ownerDocument;
          if (!d.__asesconPdfIds) { d.__asesconPdfIds = new WeakMap(); d.__asesconPdfSeq = 0; }
          if (!d.__asesconPdfIds.has(el)) d.__asesconPdfIds.set(el, ++d.__asesconPdfSeq);
          return String(d.location && d.location.href || '') + '#' + d.__asesconPdfIds.get(el);
        }"""))
    except Exception:
        return ''


def buscar_por_identificador(page, identificador_externo: str):
                                                                                      
    if not identificador_externo:
        return []
    selectores = (
        ('table tbody tr', _identidad_fila_tabla),
        ('[role="row"]', _identidad_fila_mixta),
        ('.mat-row', _identidad_fila_mixta),
        ('.p-datatable-tbody > tr', _identidad_fila_mixta),
        ('.list-group-item', _identidad_fila_generica),
        ('[role="listitem"]', _identidad_fila_generica),
        ('.mat-list-item', _identidad_fila_generica),
        ('.notification-item', _identidad_fila_generica),
        ('.inbox-item', _identidad_fila_generica),
        ('.mensaje-item', _identidad_fila_generica),
    )
    encontrados = {}
    derivada = identificador_externo.startswith('SUNAT3-')
    for marco in frames(page):
                                                                                              
                                                                                       
        for selector, obtener_clave in selectores:
            locales = []
            try:
                for item in marco.locator(selector).all():
                    if not item.is_visible():
                        continue
                    if obtener_clave(item, derivada) == identificador_externo:
                        locales.append(item)
            except Exception:
                continue
            if locales:
                for item in locales:
                    llave = _clave_elemento(item)
                    encontrados[llave or f'{marco.url}|{selector}|{len(encontrados)}'] = item
                break
    return list(encontrados.values())


def buscar_notificacion(page, asunto: str, fecha: str):
                                                                                                
    fechas = [fecha]
    if re.fullmatch(r'\d{4}-\d{2}-\d{2}', fecha):
        fechas.append(fecha[8:10] + '/' + fecha[5:7] + '/' + fecha[:4])
    encontrados = {}
    selectores = ('table tbody tr', '[role="row"]', '.mat-row', '.p-datatable-tbody > tr',
                  '.list-group-item', '[role="listitem"]', '.mat-list-item',
                  '.notification-item', '.inbox-item', '.mensaje-item')
    for marco in frames(page):
        for selector in selectores:
            locales = []
            try:
                for item in marco.locator(selector).all():
                    if not item.is_visible():
                        continue
                    texto = clean(item.inner_text(timeout=1000))
                    if asunto.casefold() not in texto.casefold():
                        continue
                    if fecha and not any(d in texto for d in fechas):
                        continue
                    locales.append((len(texto), item))
            except Exception:
                continue
            if locales:
                menor = min(t for t, _ in locales)
                for _, item in locales:
                    if len(clean(item.inner_text(timeout=1000))) == menor:
                        llave = _clave_elemento(item)
                        encontrados[llave or f'{marco.url}|{selector}|{len(encontrados)}'] = item
                                                                                         
                break
    if not encontrados:
        for marco in frames(page):
            try:
                for item in marco.get_by_text(asunto, exact=True).all():
                    if not item.is_visible():
                        continue
                    texto = clean(item.locator('xpath=..').inner_text(timeout=1000))
                    if fecha and not any(d in texto for d in fechas):
                        continue
                    llave = _clave_elemento(item)
                    encontrados[llave or f'{marco.url}|texto|{len(encontrados)}'] = item
            except Exception:
                continue
    return list(encontrados.values())

def obtener(page,solicitud:dict,directorio:str,id_trabajo:str,informar=None,tiempo_maximo:float=180)->dict:
    if informar:informar('busqueda',forzar=True)
    asunto=re.sub(r'^ASUNTO:\s*','',clean(solicitud.get('asunto')),flags=re.I)
    if not asunto:raise ErrorAccesoSol('SOL_DOCUMENTO_INVALIDO','La notificación no tiene un asunto reconocible.')
    fecha=clean(solicitud.get('fecha'))[:10]
    identificador_externo=clean(solicitud.get('identificador_externo'))
    usar_identificador=identificador_externo.startswith(('SUNAT2-','SUNAT3-'))
    claves_fertes=claves_fuertes_documento(solicitud)
    usar_clave_fuerte=not usar_identificador and bool(claves_fertes)
    coincidencias=[]
    paginas_vistas=set()
    for _ in range(80):
        if usar_identificador:
            coincidencias=buscar_por_identificador(page,identificador_externo)
        elif usar_clave_fuerte:
            coincidencias=buscar_por_clave_fuerte(page,claves_fertes)
        else:
            coincidencias=buscar_notificacion(page,asunto,fecha)
        if coincidencias:break
        firma=body_text(page)[:6000]
        if firma in paginas_vistas:break
        paginas_vistas.add(firma)
        siguiente=siguiente_pagina_sol(page)
        if siguiente is None:break
        siguiente.click(timeout=6000)
        page.wait_for_timeout(600)
    if len(coincidencias)!=1:
        if usar_identificador:
            mensaje='No se encontró una coincidencia única para el identificador guardado de esta notificación. Sincroniza nuevamente el Buzón SOL y vuelve a solicitar el PDF. No se descargó ningún documento ajeno.'
        elif usar_clave_fuerte:
            mensaje='No se encontró una coincidencia única para la referencia de esta notificación en el Buzón SOL. No se descargó ningún documento ajeno; revisa que los datos de esta notificación estén actualizados.'
        else:
            mensaje='No se pudo identificar una única notificación con ese asunto y fecha. No se descargó ningún documento ajeno.'
        raise ErrorAccesoSol('SOL_DOCUMENTO_NO_IDENTIFICADO',mensaje)
    if informar:informar('documento',forzar=True)
    fila=coincidencias[0];descargas=[];pdfs=[];urls_pdf=[];contexto=page.context
    paginas_antes=len(contexto.pages)
    def al_descargar(download):descargas.append(download)
    def al_pagina(p):p.on('download',al_descargar)
    def al_responder(respuesta):
        try:
            if not origen_oficial(respuesta.url):return
            mime=(respuesta.header_value('content-type') or '').lower();disposicion=(respuesta.header_value('content-disposition') or '').lower()
                                                                                    
            if not any(tipo in mime for tipo in ('application/pdf','application/x-pdf','application/octet-stream','application/force-download')) and not urlsplit(respuesta.url).path.lower().endswith('.pdf') and '.pdf' not in disposicion:return
            longitud=respuesta.header_value('content-length')
            if not longitud or int(longitud)>16*1024*1024:
                if origen_oficial(respuesta.url):urls_pdf.append(respuesta.url)
                return
            contenido=respuesta.body()
            if contenido.startswith(b'%PDF-') and len(contenido)<=16*1024*1024:pdfs.append(contenido)
        except Exception:pass
    for p in contexto.pages:p.on('download',al_descargar)
    contexto.on('page',al_pagina);contexto.on('response',al_responder)
    destino=Path(directorio);destino.mkdir(parents=True,exist_ok=True,mode=0o700)
    salida=destino/('sol_'+re.sub(r'[^a-zA-Z0-9-]','',id_trabajo)+'.pdf')
    def guardar(contenido:bytes)->dict:
        if not contenido.startswith(b'%PDF-'):raise ErrorAccesoSol('SOL_ARCHIVO_NO_PDF','SUNAT no devolvió un PDF válido.')
        temporal=salida.with_suffix('.temporal');temporal.write_bytes(contenido);os.chmod(temporal,0o600);os.replace(temporal,salida)
        return {'id_documento':int(solicitud['id_documento']),'archivo':str(salida)}
    try:
        control=None
        for selector in ('a[href*=".pdf" i]','a[download]','[title*="descargar" i]','[aria-label*="pdf" i]','[title*="documento" i]'):
            for x in fila.locator(selector).all():
                etiqueta=clean(x.get_attribute('title') or x.get_attribute('aria-label') or x.inner_text())
                if x.is_visible() and not re.search(r'aceptar|confirmar|acuse|cerrar caso',etiqueta,re.I):control=x;break
            if control is not None:break
        if control is None:
            for role in ('button','link'):
                for x in fila.get_by_role(role,name=re.compile(r'pdf|descargar|ver|detalle|abrir|leer',re.I)).all():
                    if x.is_visible() and not re.search(r'aceptar|confirmar|acuse|cerrar caso',clean(x.inner_text()),re.I):control=x;break
                if control is not None:break
        if control is None:
            x=fila.get_by_text(asunto,exact=False)
            if x.count()==1:control=x
        if control is None:raise ErrorAccesoSol('SOL_CONTROL_DOCUMENTO_NO_DISPONIBLE','La bandeja no expone un control de documento reconocible. No se modificó ningún documento ni acuse.')
        control.click();limite=time.monotonic()+max(1,min(180,tiempo_maximo))
        urls_intentadas=set()
        intento_descarga=False
        while time.monotonic()<limite:
            page.wait_for_timeout(400)
            if pdfs:
                if informar:informar('guardado',len(pdfs[0]),len(pdfs[0]),True)
                return guardar(pdfs[0])
            if not intento_descarga:
                                                                            
                                                                                       
                for ventana in contexto.pages:
                    for marco in frames(ventana):
                        for selector in ('button[title*="descargar" i]',
                                         'a[title*="descargar" i]',
                                         'button[aria-label*="descargar" i]',
                                         'a[aria-label*="descargar" i]',
                                         'a[href*=".pdf" i]', 'a[download]',
                                         '[role="main"] a[href*="descarga" i]'):
                            try:
                                for boton in marco.locator(selector).all():
                                    if not boton.is_visible() or not boton.is_enabled():continue
                                    etiqueta=clean(boton.get_attribute('title') or boton.get_attribute('aria-label') or '')
                                    if re.search(r'confirmar|aceptar|acuse|firmar',etiqueta,re.I):continue
                                    boton.click(timeout=2000)
                                    intento_descarga=True
                                    break
                            except Exception:continue
                            if intento_descarga:break
                        if intento_descarga:break
                    if intento_descarga:break
            if descargas:
                download=descargas.pop(0)
                if not origen_oficial(download.url):continue
                temporal=destino/('descarga_'+re.sub(r'[^a-zA-Z0-9-]','',id_trabajo)+'.temporal')
                try:
                    if informar:informar('descarga',forzar=True)
                    download.save_as(temporal)
                    with temporal.open('rb') as archivo:
                        if archivo.read(5)!=b'%PDF-':
                            raise ErrorAccesoSol('SOL_ARCHIVO_NO_PDF','El archivo descargado no es un PDF válido.')
                    os.chmod(temporal,0o600)
                    if informar:informar('guardado',temporal.stat().st_size,temporal.stat().st_size,True)
                    os.replace(temporal,salida)
                    return {'id_documento':int(solicitud['id_documento']),'archivo':str(salida)}
                finally:
                    if temporal.exists():temporal.unlink()
            for p in contexto.pages:
                for f in frames(p):
                    try:
                        for x in f.locator('iframe[src],embed[src],object[data],a[href*=".pdf" i]').all():
                            url=x.get_attribute('src') or x.get_attribute('data') or x.get_attribute('href') or ''
                            url=urljoin(f.url,url)
                            if not origen_oficial(url):continue
                            if url in urls_intentadas:continue
                            urls_intentadas.add(url)
                            try:
                                if descargar_pdf(p,url,salida,informar):
                                    return {'id_documento':int(solicitud['id_documento']),'archivo':str(salida)}
                            except Exception:
                                continue
                    except Exception:pass
                                                                               
            for url in list(urls_pdf):
                if url in urls_intentadas:continue
                urls_intentadas.add(url)
                try:
                    if descargar_pdf(page,url,salida,informar):
                        return {'id_documento':int(solicitud['id_documento']),'archivo':str(salida)}
                except Exception:continue
        texto_visible=' '.join(clean(body_text(ventana))[:2500] for ventana in contexto.pages)
        if re.search(r'no\s+(?:se\s+)?(?:ha\s+)?podido\s+generar.{0,40}(?:pdf|archivo|documento)|(?:pdf|archivo|documento).{0,40}no\s+(?:est[aá]|se\s+encuentra)\s+disponible',texto_visible,re.I):
            raise ErrorAccesoSol('SOL_PDF_NO_GENERADO_POR_PORTAL',
                'El portal SOL indicó que no pudo generar o facilitar el documento. La notificación sigue disponible y no se confirmó ningún acuse.')
        if re.search(r'confirmar\s+(?:lectura|recepci[oó]n)|acuse\s+de\s+(?:lectura|recibo)|aceptar\s+notificaci[oó]n',texto_visible,re.I):
            raise ErrorAccesoSol('SOL_PDF_REQUIERE_REVISION_MANUAL',
                'El portal muestra una acción de acuse o confirmación antes de entregar el documento. ASESCON no la ejecutó automáticamente; revísala en SUNAT.')
        if len(contexto.pages)>paginas_antes:
            raise ErrorAccesoSol('SOL_PDF_VISTA_SIN_ARCHIVO',
                'Se abrió una vista del documento, pero no llegó un PDF válido ni un enlace de descarga reconocido. No se confirmó ningún acuse.')
        raise ErrorAccesoSol('SOL_PDF_NO_DISPONIBLE',
            'No se recibió un archivo PDF válido en esta sesión SOL. Los datos de notificaciones se conservaron y no se confirmó ningún acuse.')
    finally:
        contexto.remove_listener('response',al_responder);contexto.remove_listener('page',al_pagina)
        for p in contexto.pages:
            try:p.remove_listener('download',al_descargar)
            except Exception:pass
