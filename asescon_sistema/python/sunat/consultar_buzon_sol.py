                                                                     
                                                                           
   
from __future__ import annotations

import hashlib
import json
import re
import sys
from datetime import datetime
from typing import Any
from urllib.parse import urlparse, parse_qsl

try:
    sys.stdin.reconfigure(encoding="utf-8")
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")
except Exception:
    pass

from acceso_sol import login, frames, body_text, clean, ErrorAccesoSol
from navegacion_buzon_sol import siguiente_pagina_sol as _siguiente_pagina

                                                                           
                                                                              
                                                              
USER_AGENT_SOL_ANTIGUO = (
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
    'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 '
    'Safari/537.36 Edg/131.0.0.0'
)


def abrir_sol_compatible(browser, ruc: str, usuario: str, clave: str):
                                                                                 
    for intento, perfil_antiguo in enumerate((True, False)):
        opciones = {'locale': 'es-PE', 'ignore_https_errors': False}
        if perfil_antiguo:
            opciones['user_agent'] = USER_AGENT_SOL_ANTIGUO
        contexto = browser.new_context(**opciones)
        pagina = contexto.new_page()
        pagina.set_default_timeout(12000)
        try:
            login(pagina, ruc, usuario, clave)
            return contexto, pagina
        except ErrorAccesoSol as error:
            contexto.close()
            if intento > 0 or error.codigo != 'SOL_PORTAL_NO_DISPONIBLE':
                raise
        except Exception:
            contexto.close()
            raise
    raise ErrorAccesoSol('SOL_PORTAL_NO_DISPONIBLE', 'No se pudo abrir el portal SOL.')

def out(v: dict[str, Any]) -> None:
    sys.stdout.write(json.dumps(v, ensure_ascii=False, separators=(",", ":")))
    sys.stdout.flush()

def parse_date(value:str)->str:
    value=clean(value)
    for pattern in (r"(\d{2}/\d{2}/\d{4})(?:\s+(\d{1,2}:\d{2})(?::\d{2})?)?",r"(\d{4}-\d{2}-\d{2})(?:\s+(\d{1,2}:\d{2})(?::\d{2})?)?"):
        m=re.search(pattern,value)
        if not m: continue
        d,tm=m.group(1),m.group(2) or "00:00"
        for fmt in ("%d/%m/%Y %H:%M","%Y-%m-%d %H:%M"):
            try:return datetime.strptime(f"{d} {tm}",fmt).strftime("%Y-%m-%d %H:%M:%S")
            except ValueError:pass
    return ""

def category(text:str)->str:
    t=text.upper()
    for key,label in (("COACT","COACTIVA"),("ORDEN DE PAGO","ORDEN DE PAGO"),("ESQUELA","ESQUELA"),("FISCAL","FISCALIZACION"),("FRACCION","FRACCIONAMIENTO"),("EMBARGO","EMBARGO"),("INDUCT","INDUCTIVA"),("MULTA","MULTA")):
        if key in t:return label
    return "SUNAT"

def identificador(asunto:str,tipo:str,fecha:str,links=None,atributos=None)->tuple[str,bool]:
                                                                                  

                                                                              
                                                                              
                                                                                
       
    nombres={'idnotificacion','idnotifica','codnotificacion','numeronotificacion',
             'idmensaje','codmensaje','idcomunicacion','codcomunicacion'}
    claves=[]
    def considerar(k,v):
        nombre=re.sub(r'[^a-z0-9]','',clean(k).lower())
        if nombre.startswith('data') and nombre[4:] in nombres:nombre=nombre[4:]
        valor=clean(v)
        if nombre in nombres and re.fullmatch(r'[\w-]{3,90}',valor,re.UNICODE):
            claves.append(nombre+'='+valor)
    for k,v in (atributos or {}).items():considerar(k,v)
    for link in links or []:
        if not isinstance(link,dict):continue
        u=urlparse(clean(link.get('href','')))
        for k,v in parse_qsl(u.query,keep_blank_values=False):considerar(k,v)
        for k,v in (link.get('atributos') or {}).items():considerar(k,v)
                                                                             
                                                                  
    base='|'.join(sorted(set(claves))) if claves else '|'.join((clean(asunto).casefold(),clean(tipo).casefold(),fecha))
    return 'SUNAT2-'+hashlib.sha256(base.encode('utf-8')).hexdigest()[:40],bool(claves)

def huella_notificacion(fila:dict[str,Any])->str:
                                                                                   

                                                                                
                                                                
       
    contenido=clean(fila.get('observaciones','')).casefold()
    contenido=re.sub(r'\b(?:no\s+le[ií]do|le[ií]do|no\s+revisado|revisado)\b','',contenido)
    contenido=re.sub(r'\s+',' ',contenido).strip()
    return '|'.join((clean(fila.get('asunto','')).casefold(),
                     clean(fila.get('tipo_documento','')).casefold(),
                     clean(fila.get('fecha_recepcion','')),contenido))

def identificador_desambiguado(identidad_base:str,fila:dict[str,Any])->str:
                                                                                

                                                                              
       
    firma=identidad_base+'|'+huella_notificacion(fila)
    return 'SUNAT3-'+hashlib.sha256(firma.encode('utf-8')).hexdigest()[:40]

def desambiguar_notificaciones(filas:list[dict[str,Any]])->list[dict[str,Any]]:
    grupos:dict[str,list[dict[str,Any]]]={}
    for fila in filas:
        identidad=clean(fila.get('identificador_externo',''))
        if not identidad or not clean(fila.get('asunto','')):
            raise ErrorAccesoSol('SOL_NOTIFICACION_INCOMPLETA',
                'SUNAT devolvió una fila sin identidad o asunto. Se conserva la bandeja anterior.')
        grupos.setdefault(identidad,[]).append(fila)
    salida=[]
    for identidad,entradas in grupos.items():
        if len(entradas)==1:
            salida.append(entradas[0]);continue
        huellas=[huella_notificacion(fila) for fila in entradas]
        if len(set(huellas))!=len(huellas):
            raise ErrorAccesoSol('SOL_IDENTIDAD_IRRESOLUBLE',
                'Hay notificaciones visualmente idénticas sin otra clave comprobable. No se mezclaron ni se descartaron documentos.')
                                                                              
                                                                               
                                                          
        for fila in entradas:
            entrada=dict(fila)
            entrada['identificador_externo']=identificador_desambiguado(identidad,entrada)
            entrada['clave_oficial']=False
            entrada['identidad_derivada']=True
            salida.append(entrada)
    return salida

def table_records(page)->list[dict[str,Any]]:
    rec=[]
    for f in frames(page):
        try:
            tables=f.locator("table").evaluate_all("""tables => tables.filter(t => t.getClientRects().length && !t.closest('aside,nav,[role=navigation]')).map(t => ({headers:Array.from(t.querySelectorAll('thead th')).map(x=>(x.innerText||'').trim()),rows:Array.from(t.querySelectorAll('tbody tr')).map(tr=>({cells:Array.from(tr.querySelectorAll('td')).map(td=>(td.innerText||'').trim()),atributos:Object.fromEntries(Array.from(tr.attributes).filter(a=>a.name.startsWith('data-')).map(a=>[a.name,a.value])),links:Array.from(tr.querySelectorAll('a[href]')).map(a=>({text:(a.innerText||'').trim(),href:a.href||'',atributos:Object.fromEntries(Array.from(a.attributes).filter(x=>x.name.startsWith('data-')).map(x=>[x.name,x.value]))}))}))}))""")
        except Exception: continue
        for table in tables:
            headers=[clean(x).lower() for x in table.get("headers",[])]
            for row in table.get("rows",[]):
                cells=[clean(x) for x in row.get("cells",[])]
                if not cells or all(not x for x in cells): continue
                joined=" | ".join(cells)
                if re.search(r"no\s+hay|sin\s+registros|ning[uú]n\s+registro",joined,re.I):continue
                def cell(*keys):
                    for i,h in enumerate(headers):
                        if any(k in h for k in keys) and i<len(cells):return cells[i]
                    return ""
                asunto=cell("asunto","descrip","mensaje","documento","materia")
                tipo=cell("tipo","documento")
                fecha=cell("fecha de notific","fecha notific","fecha recep","fecha documento","fecha emisión")
                if not fecha:
                    fecha=next((cells[i] for i,h in enumerate(headers) if "fecha" in h and not any(k in h for k in ("consulta","lectura","revis")) and i<len(cells)),"")
                if not asunto:
                    candidates=[c for c in cells if len(c)>8 and not re.fullmatch(r"\d+",c)]
                    asunto=max(candidates,key=len) if candidates else joined[:255]
                fecha_iso=parse_date(fecha or joined)
                ident,oficial=identificador(asunto,tipo or 'Notificación SOL',fecha_iso,row.get('links'),row.get('atributos'))
                rec.append({"identificador_externo":ident,"clave_oficial":oficial,"asunto":asunto[:255],"tipo_documento":(tipo or "Notificación SOL")[:100],"categoria":category(joined),"fecha_recepcion":fecha_iso,"prioridad":"NORMAL","archivo":"","observaciones":joined})
    return rec

def generic_records(page)->list[dict[str,Any]]:
                                                                    
    found=[]
    selectors="[role=row], .mat-row, .p-datatable-tbody > tr, .list-group-item, [role=listitem], .notification-item, .inbox-item, .mensaje-item"
    for f in frames(page):
        try:
            textos=f.locator(selectors).evaluate_all("""items => items.filter(x=>x.getClientRects().length&&!x.closest('nav,aside')).map(x=>({texto:(x.innerText||'').trim(),atributos:Object.fromEntries(Array.from(x.attributes).filter(a=>a.name.startsWith('data-')).map(a=>[a.name,a.value])),links:Array.from(x.querySelectorAll('a[href]')).map(a=>({href:a.href||'',atributos:Object.fromEntries(Array.from(a.attributes).filter(y=>y.name.startsWith('data-')).map(y=>[y.name,y.value]))}))}))""")
        except Exception: continue
        for item in textos:
            txt=clean(item.get('texto'))
            if len(txt)<20: continue
            if not re.search(r"notific|resoluci[oó]n|orden|esquela|carta|comunicaci[oó]n|coactiv|multa",txt,re.I):continue
            contenido=re.sub(r'\b(?:no\s+le[ií]do|le[ií]do|revisado|no\s+revisado)\b','',txt,flags=re.I)
            ident,oficial=identificador(contenido,'Notificación SOL',parse_date(txt),item.get('links'),item.get('atributos'))
            found.append({"identificador_externo":ident,"clave_oficial":oficial,"asunto":contenido[:255],"tipo_documento":"Notificación SOL","categoria":category(txt),"fecha_recepcion":parse_date(txt),"prioridad":"NORMAL","archivo":"","observaciones":txt})
    return found

def _registros_pagina(page)->list[dict[str,Any]]:
    registros=table_records(page)
    return registros if registros else generic_records(page)

def _total_visible(page)->int|None:
                                                                                    
    texto=body_text(page)
    for patron in (r"\b\d{1,5}\s*[-–]\s*\d{1,5}\s*(?:de|of)\s*(\d{1,6})\b",
                   r"\b(?:mostrando|showing)\s+\d+\s*(?:a|to)\s*\d+\s*(?:de|of)\s*(\d{1,6})\b",
                   r"\bbuz[oó]n\s+notificaciones\s*\(\s*(\d{1,6})\s*\)",
                   r"\bnotificaciones\s*\(\s*(\d{1,6})\s*\)"):
        m=re.search(patron,texto,re.I)
        if m:return int(m.group(1))
    return None

def read_inbox(page)->list[dict[str,Any]]:
    registros:list[dict[str,Any]]=[]
    vistos=set()
    total_esperado=_total_visible(page)
    for _ in range(80):
        filas=_registros_pagina(page)
        firma=tuple((r.get('identificador_externo',''),huella_notificacion(r)) for r in filas)
        if firma in vistos:
            raise ErrorAccesoSol('SOL_PAGINACION_SIN_AVANCE','El Buzón repitió una página. Se conservaron los datos anteriores para evitar una sincronización incompleta.')
        vistos.add(firma)
        registros.extend(filas)
        if len(registros)>10000:
            raise ErrorAccesoSol('SOL_LIMITE_COBERTURA','La bandeja supera el límite de seguridad de esta ejecución. Se conservaron los registros anteriores.')
        siguiente=_siguiente_pagina(page)
        if siguiente is None:
            if total_esperado is not None and len(registros)<total_esperado:
                raise ErrorAccesoSol('SOL_PAGINACION_INCOMPLETA',f'La bandeja indica {total_esperado} resultados, pero solo se identificaron {len(registros)}. No se guardará una lectura incompleta.')
            return desambiguar_notificaciones(registros)
        siguiente.click(timeout=6000)
                                                                               
        avanzo=False
        for _ in range(12):
            page.wait_for_timeout(300)
            nueva=tuple((r.get('identificador_externo',''),huella_notificacion(r)) for r in _registros_pagina(page))
            if nueva!=firma:
                avanzo=True
                break
        if not avanzo:
            raise ErrorAccesoSol('SOL_PAGINACION_ESTANCADA','El navegador no pudo avanzar a la siguiente página del Buzón. No se guardaron datos parciales.')
    raise ErrorAccesoSol('SOL_PAGINACION_EXTENSA','La bandeja requiere más de 80 páginas. Se necesita sincronizarla por lotes sin descartar la información anterior.')

def mensaje_seguro(error,req):
    mensaje=clean(error)
    for clave in ('clave_sol','usuario_sol','client_secret_sunat','access_token'):
        valor=str(req.get(clave,'') or '')
        if valor:mensaje=mensaje.replace(valor,'[OCULTO]')
    mensaje=re.sub(r'(idCache|token|password|j_password)=([^\s&\"\']+)',r'\1=[OCULTO]',mensaje,flags=re.I)
    return mensaje[:600]

def main()->None:
    req={}
    try:
        req=json.loads(sys.stdin.read() or "{}")
        ruc=re.sub(r"\D","",str(req.get("ruc","")));usuario=clean(req.get("usuario_sol",""));clave=str(req.get("clave_sol",""))
        if not re.fullmatch(r"\d{11}",ruc):raise RuntimeError("El RUC debe contener exactamente 11 dígitos.")
        if not usuario or not clave:raise RuntimeError("Usuario SOL y Clave SOL son obligatorios.")
        try:from playwright.sync_api import sync_playwright
        except Exception:out({"ok":False,"codigo":"PY_PLAYWRIGHT_NO_DISPONIBLE","mensaje":"Este intérprete de Python no tiene Playwright disponible."});return
        from progreso_descarga_sol import ProgresoDescargaSol
        progreso=ProgresoDescargaSol(str(req.get("ruta_progreso", "")),str(req.get("id_trabajo", "")))
        progreso.informar("acceso",forzar=True)
        with sync_playwright() as p:
            browser=None
            argumentos_servidor=["--no-sandbox","--disable-dev-shm-usage"]
            launch_sets=[]
            chromium_configurado=clean(__import__("os").environ.get("ASESCON_CHROMIUM",""))
            if chromium_configurado:
                launch_sets.append({"executable_path":chromium_configurado,"headless":True,"args":argumentos_servidor})
            launch_sets.append({"headless":True,"args":argumentos_servidor})
            errores_lanzamiento=[]
            for a in launch_sets:
                try:
                    browser=p.chromium.launch(**a);break
                except Exception as exc:
                    errores_lanzamiento.append(clean(exc)[:220])
            if browser is None:
                detalle=" | ".join(errores_lanzamiento[-2:])
                mensaje="No se pudo iniciar Chromium en el servidor para consultar el Buzón SOL."
                if detalle:
                    mensaje += " " + detalle
                out({"ok":False,"codigo":"PY_NAVEGADOR_NO_DISPONIBLE","mensaje":mensaje});return
            ctx=None
            try:
                ctx,page=abrir_sol_compatible(browser,ruc,usuario,clave)
                if isinstance(req.get("documento"),dict):
                    if req.get('modo_documento')=='DETALLE':
                        from consultar_detalle_oficial_sol import consultar
                        solicitud_detalle=dict(req['documento'],ruc_solicitado=ruc)
                        detalle=consultar(page,solicitud_detalle,progreso.informar)
                        data={"ruc":ruc,"sol_validado":True,"detalle":detalle}
                    else:
                        from obtener_pdf_sol import obtener
                        documento=obtener(page,req['documento'],str(req.get('directorio_adjuntos','')),str(req.get('id_trabajo','')),progreso.informar)
                        data={"ruc":ruc,"sol_validado":True,"documento":documento}
                else:
                    docs=[] if req.get('solo_adjuntos') else read_inbox(page)
                    if not req.get('solo_adjuntos') and not docs and not re.search(r"sin\s+(?:notificaciones|mensajes|documentos)|no\s+(?:hay|tiene)\s+(?:notificaciones|mensajes|documentos)|0\s+(?:notificaciones|mensajes|documentos)",body_text(page),re.I):
                        raise ErrorAccesoSol("SOL_FORMATO_BANDEJA_NO_RECONOCIDO","La bandeja respondió, pero no se pudo reconocer sus filas ni confirmar que esté vacía. Se conservan los registros anteriores.")
                    data={"ruc":ruc,"sol_validado":True,"fuente":"BUZON_ELECTRONICO_SOL","notificaciones":docs,"cantidad":len(docs),"cobertura":"PAGINAS_RECONOCIDAS_DE_BANDEJA"}
                    if req.get('documentos_adjuntos'):
                        try:
                            import time
                            from precargar_adjuntos_sol import precargar, preparar_bandeja
                            preparar_bandeja(page,page.url)
                            data['adjuntos']=precargar(page,req['documentos_adjuntos'],str(req.get('id_trabajo','')),time.monotonic()+600)
                        except Exception as error_adjuntos:
                                                                                      
                            if req.get('solo_adjuntos'):
                                raise
                            data['advertencia_adjuntos']=mensaje_seguro(error_adjuntos,req)

            finally:
                if ctx is not None:ctx.close()
                browser.close()
        out({"ok":True,"data":data})
    except Exception as exc:
        out({"ok":False,"codigo":getattr(exc,"codigo","BUZON_SOL_ACCESO_ERROR"),"mensaje":mensaje_seguro(exc,req)})
        sys.exit(1)

if __name__=="__main__":main()
