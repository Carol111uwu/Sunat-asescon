                                                                                   

                                                                          
                                                                           
                                                                       
   
from __future__ import annotations

import re
import time
from acceso_sol import clean, frames, ErrorAccesoSol
from navegacion_buzon_sol import siguiente_pagina_sol
from obtener_pdf_sol import (
    buscar_por_identificador, buscar_por_clave_fuerte,
    buscar_notificacion, claves_fuertes_documento,
)


def localizar_unica(page, solicitud:dict):
    asunto=re.sub(r'^ASUNTO:\s*','',clean(solicitud.get('asunto')),flags=re.I)
    if not asunto:
        raise ErrorAccesoSol('SOL_DOCUMENTO_INVALIDO','La notificación no tiene un asunto reconocible.')
    identidad=clean(solicitud.get('identificador_externo'))
    fecha=clean(solicitud.get('fecha'))[:10]
    fuertes=claves_fuertes_documento(solicitud) if not identidad.startswith(('SUNAT2-','SUNAT3-')) else []
    vistas=set()
    for _ in range(80):
        if identidad.startswith(('SUNAT2-','SUNAT3-')):
            encontrados=buscar_por_identificador(page,identidad)
        elif fuertes:
            encontrados=buscar_por_clave_fuerte(page,fuertes)
        else:
            encontrados=buscar_notificacion(page,asunto,fecha)
        if encontrados:
            if len(encontrados)!=1:
                raise ErrorAccesoSol('SOL_DETALLE_AMBIGUO','Se detectaron varias notificaciones posibles; no se abrió ninguna.')
            return encontrados[0]
        siguiente=siguiente_pagina_sol(page)
        if siguiente is None:break
        pagina=clean(page.locator('body').inner_text(timeout=1500))[:3000]
        if pagina in vistas:break
        vistas.add(pagina)
        siguiente.click(timeout=6000)
        page.wait_for_timeout(400)
    raise ErrorAccesoSol('SOL_DETALLE_NO_IDENTIFICADO','No se pudo identificar una única notificación en SUNAT. No se abrió ningún documento ajeno.')


def _contenido_oficial(page,ruc:str)->dict:
                                                                               
    candidatos=[]
    for marco in frames(page):
        try:
            datos=marco.locator('body').evaluate("""body => {
              const nodos=Array.from(body.querySelectorAll('article,section,main,[role="main"],div'));
              const candidatos=[];
              for (const el of nodos) {
                if (!el.getClientRects().length) continue;
                const texto=(el.innerText||'').replace(/\\s+/g,' ').trim();
                if(texto.length<100 || texto.length>45000)continue;
                                                                            
                                                                    
                if (!/constancia de notificaci[oó]n|la presente constancia|fecha de dep[oó]sito/i.test(texto))continue;
                const enlaces=Array.from(el.querySelectorAll('a')).slice(0,50)
                  .map(a=>({nombre:(a.innerText||a.getAttribute('title')||'').replace(/\\s+/g,' ').trim().slice(0,180)}))
                  .filter(a=>a.nombre && /constancia|resoluci[oó]n|orden|pdf|anexo|documento|descarg/i.test(a.nombre));
                candidatos.push({texto:texto.slice(0,30000),enlaces});
              }
              return candidatos.sort((a,b)=>a.texto.length-b.texto.length).slice(0,10);
            }""")
            if isinstance(datos,list):candidatos.extend(datos)
        except Exception:
            continue
                                                                            
                                                                         
    candidatos=[c for c in candidatos if ruc in clean(c.get('texto'))
                and re.search(r'\b(?:\d{2}/\d{2}/\d{4}|\d{4}-\d{2}-\d{2})\b',clean(c.get('texto')))]
    if not candidatos:
        return {}
                                                                             
                                                                   
    elegido=min(candidatos,key=lambda d:len(d.get('texto','')))
    texto=clean(elegido.get('texto'))
    if len(texto)<65:
        return {}
    nombres=[]
    for elemento in elegido.get('enlaces',[]):
        nombre=clean(elemento.get('nombre'))
        if nombre and nombre not in nombres:nombres.append(nombre)
    return {'texto':texto[:30000],'archivos':nombres[:25]}


def consultar(page,solicitud:dict,informar=None)->dict:
    if informar:informar('busqueda',forzar=True)
    ruc=re.sub(r'\D','',''.join(str(solicitud.get('ruc_solicitado',''))))
    if len(ruc)!=11:
        raise ErrorAccesoSol('SOL_CLIENTE_INVALIDO','La consulta no identificó correctamente el RUC solicitado.')
    fila=localizar_unica(page,solicitud)
    asunto=re.sub(r'^ASUNTO:\s*','',clean(solicitud.get('asunto')),flags=re.I)
    control=None
    try:
        for candidato in fila.get_by_text(asunto,exact=False).all():
            if candidato.is_visible():control=candidato;break
    except Exception:pass
    if control is None:
        for selector in ('a:not([download])','[role="button"]','button'):
            try:
                for candidato in fila.locator(selector).all():
                    etiqueta=clean(candidato.inner_text(timeout=700))
                    if candidato.is_visible() and re.search(r'ver|abrir|detalle|notificaci[oó]n',etiqueta,re.I) and not re.search(r'acuse|confirmar|aceptar',etiqueta,re.I):
                        control=candidato;break
            except Exception:continue
            if control is not None:break
    if control is None:
        raise ErrorAccesoSol('SOL_DETALLE_SIN_CONTROL','SUNAT no expuso un control de lectura seguro para esta notificación.')
    if informar:informar('documento',forzar=True)
    control.click(timeout=6000)
    limite=time.monotonic()+25
    datos={}
    while time.monotonic()<limite:
        datos=_contenido_oficial(page,ruc)
        if datos:break
        page.wait_for_timeout(500)
    if not datos:
        raise ErrorAccesoSol('SOL_DETALLE_NO_DISPONIBLE','La notificación se abrió, pero SUNAT no mostró un contenido de constancia verificable. No se guardó información inventada.')
    return {'id_documento':int(solicitud['id_documento']),'contenido':datos['texto'],'archivos':datos['archivos']}
