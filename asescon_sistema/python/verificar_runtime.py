                                                                                                
from __future__ import annotations
import os,sys,json,platform,time,importlib.metadata,re
from pathlib import Path

def comprobar(estricto=False):
    contrato={'python':'3.13.18','playwright':'1.64.1','greenlet':'3.5.6','pyee':'14.0.0','typing_extensions':'4.16.0','mysql-connector-python':'9.7.0','chromium':'156.0.8078.4'}
    r={'python':sys.version.split()[0],'interprete':sys.executable,'sistema':platform.system(),'arquitectura':platform.machine(),'libc':list(platform.libc_ver()),'python_compatible':sys.version_info>=(3,10),'playwright':None,'navegador_ok':False,'instalacion_ok':False,'estricto':estricto,'contrato':contrato,'error':None,'comprobado_en':int(time.time())}
    if not r['python_compatible']:r['error']='La instalacion nueva requiere Python 3.13.18.';return r
    try:
        r['playwright']=importlib.metadata.version('playwright')
        r['python_requerido_playwright']=importlib.metadata.metadata('playwright').get('Requires-Python','')
        minimo=re.search(r'>=\s*(\d+)\.(\d+)',r['python_requerido_playwright'])
        if minimo and sys.version_info[:2] < tuple(map(int,minimo.groups())):
            r['python_compatible']=False
            r['error']='La versión de Playwright instalada requiere '+r['python_requerido_playwright']
            return r
        r['dependencias']={nombre:importlib.metadata.version(nombre) for nombre in ('playwright','greenlet','pyee','typing_extensions','mysql-connector-python')}
        import mysql.connector
        r['conector_mysql_importable']=hasattr(mysql.connector,'connect')
        if estricto and not r['conector_mysql_importable']:
            r['error']='No se puede importar el conector MySQL de Python.'
            return r
        if estricto and (r['python']!=contrato['python'] or any(r['dependencias'][k]!=contrato[k] for k in r['dependencias'])):
            r['error']='Las versiones instaladas no coinciden con el contrato de esta entrega.'
            return r
        manifiesto=importlib.metadata.distribution('playwright').locate_file('playwright/driver/package/browsers.json')
        if Path(manifiesto).is_file():
            r['navegadores_esperados']=[{k:b[k] for k in ('name','revision','browserVersion') if k in b} for b in json.loads(Path(manifiesto).read_text()).get('browsers',[]) if b.get('name') in ('chromium','chromium-headless-shell')]
        from playwright.sync_api import sync_playwright
        with sync_playwright() as p:
            opciones={'headless':True,'timeout':20000,'args':['--no-sandbox','--disable-dev-shm-usage']}
            if not estricto and os.environ.get('ASESCON_CHROMIUM'):opciones['executable_path']=os.environ['ASESCON_CHROMIUM']
            b=p.chromium.launch(**opciones)
            ctx=b.new_context();page=ctx.new_page();page.set_content('<title>ASESCON LOCAL</title><main id="prueba">OK_LOCAL</main>')
            r['navegador_ok']=page.title()=='ASESCON LOCAL' and page.locator('#prueba').inner_text()=='OK_LOCAL'
            r['version_navegador']=b.version;ctx.close();b.close()
            r['instalacion_ok']=r['navegador_ok'] and (not estricto or r['version_navegador']==contrato['chromium'])
            if not r['instalacion_ok']:r['error']='El navegador no coincide con el contrato o no supero la prueba local.'
    except Exception as e:
                                                                                 
        r['error']=str(e)[:3500]
    return r

if __name__=='__main__':
    resultado=comprobar('--estricto' in sys.argv);contenido=json.dumps(resultado,ensure_ascii=False,indent=2)
    if len(sys.argv)>1:
        destino=Path(sys.argv[1]);destino.parent.mkdir(parents=True,exist_ok=True,mode=0o700);tmp=destino.with_suffix('.tmp')
        tmp.write_text(contenido);tmp.chmod(0o600);os.replace(tmp,destino)
    print(contenido);sys.exit(0 if resultado['instalacion_ok'] else 1)
