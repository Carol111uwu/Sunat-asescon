                                                                           

                                                                      
                                                                                    
   
from __future__ import annotations
import hashlib,importlib.metadata,json,os,platform,stat,tempfile,urllib.request,zipfile
from pathlib import Path

def instalar():
    if platform.system()!='Linux' or platform.machine() not in ('x86_64','AMD64'):
        raise RuntimeError('La fuente alternativa de esta entrega requiere Linux x86_64.')
    if importlib.metadata.version('playwright')!='1.64.1':
        raise RuntimeError('Se requiere Playwright 1.64.1.')
    p=importlib.metadata.distribution('playwright').locate_file('playwright/driver/package/browsers.json')
    navegador=next(b for b in json.loads(Path(p).read_text())['browsers'] if b['name']=='chromium-headless-shell')
    if navegador['browserVersion']!='156.0.8078.4' or navegador['revision']!='1248':
        raise RuntimeError('El manifiesto no coincide con esta entrega.')
    raiz=Path(os.environ['PLAYWRIGHT_BROWSERS_PATH']).resolve();raiz.mkdir(parents=True,exist_ok=True)
    destino=raiz/'chromium_headless_shell-1248'
    if destino.exists():
        raise RuntimeError('Existe una carpeta Chromium parcial. Con los trabajadores detenidos, retirala antes de reintentar.')
    url='https://storage.googleapis.com/chrome-for-testing-public/156.0.8078.4/linux64/chrome-headless-shell-linux64.zip'
    with tempfile.TemporaryDirectory(prefix='descarga_chromium_',dir=raiz) as temporal:
        temporal=Path(temporal);archivo=temporal/'navegador.zip';tamano=0;sha=hashlib.sha256()
        with urllib.request.urlopen(url,timeout=90) as respuesta,archivo.open('wb') as salida:
            if respuesta.geturl()!=url:raise RuntimeError('Redireccion inesperada en la descarga oficial.')
            largo=int(respuesta.headers.get('Content-Length','0'))
            if largo>256*1024*1024:raise RuntimeError('Descarga demasiado grande.')
            while True:
                bloque=respuesta.read(1024*1024)
                if not bloque:break
                tamano+=len(bloque)
                if tamano>256*1024*1024:raise RuntimeError('Descarga demasiado grande.')
                sha.update(bloque);salida.write(bloque)
        if largo and largo!=tamano:raise RuntimeError('Descarga incompleta.')
        extraido=temporal/'extraido';extraido.mkdir(mode=0o700)
        with zipfile.ZipFile(archivo) as z:
            if sum(i.file_size for i in z.infolist())>768*1024*1024:raise RuntimeError('Contenido demasiado grande.')
            for info in z.infolist():
                relativo=Path(info.filename)
                if relativo.is_absolute() or '..' in relativo.parts or not relativo.parts or relativo.parts[0]!='chrome-headless-shell-linux64':raise RuntimeError('Ruta inesperada en el archivo oficial.')
                modo=info.external_attr>>16
                if stat.S_ISLNK(modo):raise RuntimeError('Enlace inesperado en el archivo oficial.')
                objetivo=extraido/relativo
                if info.is_dir():objetivo.mkdir(parents=True,exist_ok=True,mode=0o700);continue
                objetivo.parent.mkdir(parents=True,exist_ok=True,mode=0o700)
                with z.open(info) as entrada,objetivo.open('wb') as salida:
                    while bloque:=entrada.read(1024*1024):salida.write(bloque)
                objetivo.chmod(0o700 if modo&0o111 else 0o600)
        ejecutable=extraido/'chrome-headless-shell-linux64/chrome-headless-shell'
        if not ejecutable.is_file():raise RuntimeError('Falta el ejecutable del navegador oficial.')
        ejecutable.chmod(0o700)
                                                                                         
        (extraido/'INSTALLATION_COMPLETE').touch(mode=0o600)
        extraido.rename(destino)
        informe={'fuente':url,'version':'156.0.8078.4','revision':'1248','bytes':tamano,'sha256':sha.hexdigest()}
        (raiz/'origen_chromium.json').write_text(json.dumps(informe,indent=2))
        print(json.dumps(informe))

if __name__=='__main__':instalar()
