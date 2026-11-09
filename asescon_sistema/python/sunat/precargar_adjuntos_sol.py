                                                                                              
from __future__ import annotations
import time
from acceso_sol import goto_retry, BUZON_URLS, body_text, find_input, ErrorAccesoSol, abrir_bandeja_sol
from obtener_pdf_sol import obtener

def preparar_bandeja(page,url):
    for destino in dict.fromkeys((url,)+BUZON_URLS):
        try:
            goto_retry(page,destino,1,10000)
            if find_input(page,['input[type="password"]']) is not None:
                raise ErrorAccesoSol('SOL_SESION_VENCIDA','La sesión SOL terminó durante la precarga. Se reintentará en segundo plano.')
                                                                                    
            page.wait_for_timeout(400)
            if any(k in body_text(page).casefold() for k in ('notificaciones','bandeja','buzón')):
                abrir_bandeja_sol(page)
                return
        except ErrorAccesoSol:continue
    raise ErrorAccesoSol('SOL_BANDEJA_NO_DISPONIBLE','No se pudo recuperar la bandeja para continuar con los adjuntos.')

def precargar(page,documentos,id_trabajo,limite):
    resultados=[];entrada=page.url
    for indice,documento in enumerate(documentos):
        if time.monotonic()+32>=limite:break
        existentes=set(page.context.pages)
        try:
            if indice:preparar_bandeja(page,entrada)
            datos=obtener(page,documento,str(documento['directorio_adjuntos']),id_trabajo+'-'+str(documento['id_documento']),tiempo_maximo=min(30,limite-time.monotonic()-2))
            resultados.append(dict(datos,ok=True))
        except Exception as exc:
            resultados.append({'id_documento':int(documento['id_documento']),'ok':False,'codigo':getattr(exc,'codigo','SOL_PDF_NO_DISPONIBLE'),'mensaje':'SUNAT no entregó un PDF identificable para esta notificación. Se reintentará automáticamente.'})
        finally:
            for ventana in list(page.context.pages):
                if ventana not in existentes:
                    try:ventana.close()
                    except Exception:pass
    return resultados
