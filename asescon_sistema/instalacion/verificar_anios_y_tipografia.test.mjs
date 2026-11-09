import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {dirname,resolve} from 'node:path';
import {fileURLToPath} from 'node:url';
import vm from 'node:vm';

const base=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const leer=ruta=>readFileSync(resolve(base,ruta),'utf8');

test('Vencimientos muestra el rango real de años y no llama actual al año 2021',()=>{
  const php=leer('vistas/vencimientos/vencimientos_sunat.php');
  assert.match(php,/Todos los años \(2021–<\?= \$anioActual \?>\)/);
  assert.doesNotMatch(php,/Todos los años \(2021–actual\)/);
  assert.match(php,/new DateTimeImmutable\('now',new DateTimeZone\('America\/Lima'\)\)/);
});

test('Historial 2021–actual tiene filtro independiente del cron del año vigente',()=>{
  const api=leer('api/sincronizacion/estado_global.php');
  const estado=leer('servicios/sincronizacion/EstadoSireGuardado.php');
  const plan=leer('servicios/sincronizacion/PlanificadorSincronizacion.php');
  assert.match(api,/\$anioConsulta<2021\|\|\$anioConsulta>\$anioActual/);
  assert.match(api,/inventario\(\$anioConsulta\)/);
  assert.match(api,/periodosAnio\(\$anioConsulta\)/);
  assert.match(api,/\$anioConsulta/);
  assert.match(estado,/public static function periodosAnio\(int \$anio/);
  assert.match(estado,/\$limite=\$anio===\(int\)\$ahora->format\('Y'\)\?\(int\)\$ahora->format\('n'\):12/);
  assert.match(plan,/return \[EstadoSireGuardado::periodoActual\(\)\]/);
});

test('Una sola familia tipográfica se aplica también al logotipo, controles y tarjetas',()=>{
  const barra=leer('vistas/componentes/barra_superior.php');
  const ui=leer('publico/css/interfaz/tipografia_global.css');
  assert.match(barra,/tipografia_global\.css/);
  assert.match(barra,/@filemtime\(__DIR__.*?tipografia_global\.css/);
  assert.match(ui,/"Segoe UI", Arial, Helvetica, sans-serif/);
  assert.match(ui,/\.reference-panel, \.reference-metric, \.summary-card/);
  assert.match(ui,/body :is\(input, select, textarea, button, option, optgroup, table, code, pre\)/);
  assert.match(ui,/\.logo-text strong \{ font-family: var\(--fuente-ui\) !important/);
});

test('El selector real de Sincronización ofrece 2021–2027 y pide solo el año elegido',async()=>{
  class Elemento {
    constructor(tag,id=''){
      this.tag=tag;this.id=id;this.children=[];this.dataset={};this.eventos=new Map();
      this.value='';this.hidden=false;this.disabled=false;this.textContent='';this.isConnected=true;
    }
    append(...nodos){this.children.push(...nodos);}
    replaceChildren(...nodos){this.children=nodos.flatMap(n=>n?.tag==='fragment'?n.children:[n]);}
    addEventListener(tipo,fn){this.eventos.set(tipo,fn);}
    setAttribute(){}
    querySelector(s){
      if(s==='summary')return this.children.find(c=>c?.tag==='summary')||null;
      return null;
    }
    querySelectorAll(){return [];}
    get options(){
      const out=[];
      const andar=e=>{if(e?.tag==='option')out.push(e);else for(const hijo of e?.children||[])andar(hijo);};
      for(const hijo of this.children)andar(hijo);
      return out;
    }
    focus(){}
  }
  const elementos=new Map();
  for(const id of ['syncPanel','syncEmpresaBoton','syncEmpresaPanel','syncBuscar','syncOpcionesEmpresa',
    'syncPeriodo','syncModulo','syncEmpresas','syncGlobalEstado','syncGlobalBarra','syncGlobalCifras',
    'syncGlobalAviso','syncTodas','syncCronEstado','syncSinResultados'])elementos.set(id,new Elemento('div',id));
  const panel=elementos.get('syncPanel');panel.dataset={endpoint:'https://sistema.asescon.net/api/sincronizacion/estado_global.php',enlaces:'{}'};
  const periodo=elementos.get('syncPeriodo');periodo.tag='select';periodo.value='todos';
  elementos.get('syncModulo').value='TODOS';
  const urls=[];
  const fechaFija=Date.UTC(2027,0,15,14,0,0);
  class FechaFija extends Date {constructor(...args){super(...(args.length?args:[fechaFija]));}}
  const documento={
    hidden:false,activeElement:null,
    getElementById:id=>elementos.get(id)||null,
    createElement:tag=>new Elemento(tag),
    createDocumentFragment:()=>new Elemento('fragment'),
    querySelector:()=>null,
    addEventListener:()=>{}
  };
  const fetch=async url=>{
    urls.push(String(url));
    const anio=Number(new URL(url).searchParams.get('anio'));
    const max=anio===2027?1:12;
    return {ok:true,json:async()=>({
      anio_consulta:anio,periodos_sire:Array.from({length:max},(_,i)=>String(anio)+String(i+1).padStart(2,'0')),
      catalogo_clientes:[],cobertura_sire:{},cobertura_buzones:{},empresas:[],
      total:0,finalizados:0,completados:0,pendientes:0,procesando:0,errores:0,advertencias:0,
      porcentaje:0,cron_activo:true,ultima_senal:'15/01/2027 09:00:00',pausado:false
    })};
  };
  vm.runInNewContext(leer('publico/js/sincronizacion/progreso_total.js'),{
    document:documento,window:{location:{href:'https://sistema.asescon.net/vistas/utilitarios/sincronizacion.php'}},
    location:{href:'https://sistema.asescon.net/vistas/utilitarios/sincronizacion.php'},
    fetch,Date:FechaFija,Intl,URL,AbortController,console,
    setInterval:()=>0,setTimeout:()=>0,clearTimeout:()=>{}
  });
  const fluir=()=>new Promise(resolve=>setImmediate(resolve));
  await fluir();
  assert.equal(new URL(urls[0]).searchParams.get('anio'),'2027');
  assert.ok(periodo.options.some(o=>o.value==='202101'));
  assert.ok(periodo.options.some(o=>o.value==='202112'));
  assert.ok(periodo.options.some(o=>o.value==='202701'));
  assert.ok(!periodo.options.some(o=>o.value==='202702'));
  assert.ok(periodo.options.some(o=>o.value==='anio_2021'));
  periodo.value='anio_2021';
  periodo.eventos.get('change')();
  await fluir();
  assert.equal(new URL(urls.at(-1)).searchParams.get('anio'),'2021');
  assert.ok(periodo.options.some(o=>o.value==='202112'));
});
