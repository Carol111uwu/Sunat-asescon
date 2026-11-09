                                                                                   
from __future__ import annotations
import os
import base64
import urllib.request
import http.cookiejar
from pathlib import Path
from urllib.parse import urlsplit

class RedireccionSunat(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, msg, headers, newurl):
        if not origen_sunat(newurl):
            raise ValueError('El documento redirigió fuera de SUNAT.')
        return super().redirect_request(request,fp,code,msg,headers,newurl)

def origen_sunat(url:str)->bool:
    try:
        u=urlsplit(url)
        return u.scheme=='https' and (u.hostname=='sunat.gob.pe' or (u.hostname or '').endswith('.sunat.gob.pe'))
    except ValueError:
        return False

def _guardar_atomico(contenido: bytes, destino: Path) -> None:
    if not contenido.startswith(b'%PDF-'):
        raise ValueError('SUNAT no devolvió un PDF válido.')
    temporal = destino.with_name(destino.name + '.temporal')
    try:
        with temporal.open('wb') as salida:
            salida.write(contenido)
            salida.flush()
            os.fsync(salida.fileno())
        os.chmod(temporal, 0o600)
        os.replace(temporal, destino)
    finally:
        if temporal.exists():
            temporal.unlink()

def _descargar_blob(page, url: str, destino: Path, informar=None) -> bool:
                                                                                        
    origen_blob = url[5:]
    if not origen_sunat(origen_blob):
        return False
    origen = urlsplit(origen_blob)
    origen_esperado = f'{origen.scheme}://{origen.netloc}'
    javascript = r'''async (url) => {
      try {
        const respuesta = await fetch(url, {credentials: 'include'});
        if (!respuesta.ok) return null;
        const blob = await respuesta.blob();
        if (!blob || blob.size < 1 || blob.size > 32 * 1024 * 1024) return null;
        const buffer = await blob.arrayBuffer();
        const bytes = new Uint8Array(buffer);
        let binario = '';
        for (let i = 0; i < bytes.length; i += 0x8000) {
          binario += String.fromCharCode(...bytes.subarray(i, Math.min(i + 0x8000, bytes.length)));
        }
        return {base64: btoa(binario), tipo: blob.type || '', tamano: blob.size};
      } catch (_) { return null; }
    }'''
    for ventana in list(page.context.pages):
        try:
            marcos = [ventana.main_frame] + [f for f in ventana.frames if f != ventana.main_frame]
        except Exception:
            continue
        for marco in marcos:
            try:
                if marco.evaluate('() => location.origin') != origen_esperado:
                    continue
                datos = marco.evaluate(javascript, url)
                if not isinstance(datos, dict) or not datos.get('base64'):
                    continue
                contenido = base64.b64decode(datos['base64'], validate=True)
                if not contenido.startswith(b'%PDF-'):
                    continue
                _guardar_atomico(contenido, destino)
                if informar:
                    informar('guardado', len(contenido), len(contenido), True)
                return True
            except Exception:
                continue
    return False

def descargar_pdf(page,url:str,destino:Path,informar=None)->bool:
                                                                                 
    if url.startswith('blob:'):return _descargar_blob(page,url,destino,informar)
    if not origen_sunat(url):return False
    cookies=page.context.cookies(url)
    jar=http.cookiejar.CookieJar()
    for c in cookies:
        domain=c['domain']
        jar.set_cookie(http.cookiejar.Cookie(0,c['name'],c['value'],None,False,domain,True,domain.startswith('.'),c.get('path','/'),True,c.get('secure',True),int(c['expires']) if c.get('expires',-1)>0 else None,False,None,None,{},False))
    cabeceras={'Accept':'application/pdf,application/octet-stream;q=0.9,*/*;q=0.5',
               'User-Agent':page.evaluate('navigator.userAgent')}
    if origen_sunat(page.url):cabeceras['Referer']=page.url
    peticion=urllib.request.Request(url,headers=cabeceras,method='GET')
    controlador=urllib.request.build_opener(RedireccionSunat(),urllib.request.HTTPCookieProcessor(jar))
    temporal=destino.with_name(destino.name+'.temporal')
    try:
        with controlador.open(peticion,timeout=90) as respuesta:
            if not origen_sunat(respuesta.geturl()):return False
            tipo=respuesta.headers.get('Content-Type','').lower()
            if 'text/html' in tipo or 'application/json' in tipo:return False
            total=int(respuesta.headers.get('Content-Length','0') or '0')
            inicial=respuesta.read(65536)
            if not inicial.startswith(b'%PDF-'):return False
            descargados=len(inicial)
            with temporal.open('wb') as salida:
                os.chmod(temporal,0o600)
                salida.write(inicial)
                if informar:informar('descarga',descargados,total,True)
                while True:
                    parte=respuesta.read(1024*1024)
                    if not parte:break
                    salida.write(parte)
                    descargados+=len(parte)
                    if informar:informar('descarga',descargados,total)
            if total>0 and descargados!=total:
                raise IOError('SUNAT interrumpió la descarga antes de completar el archivo.')
        if informar:informar('guardado',descargados,total,True)
        os.replace(temporal,destino)
        return True
    finally:
        if temporal.exists():temporal.unlink()
