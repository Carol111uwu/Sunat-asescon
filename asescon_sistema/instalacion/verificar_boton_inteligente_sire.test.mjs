import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {dirname, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';
import vm from 'node:vm';

const base = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const leer = ruta => readFileSync(resolve(base, ruta), 'utf8');
const fechaPeru = () => {
  const partes = new Intl.DateTimeFormat('en-US', {timeZone:'America/Lima',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());
  const val = k => partes.find(x => x.type === k)?.value || '';
  return `${val('year')}-${val('month')}-${val('day')}`;
};

function entorno(libro, inventario, {periodo='202510', sinConector=false, fechaFalsa=null}={}) {
  const prefijo = libro.toLowerCase();
  const rutas = [];
  const remotas = [];
  const elementos = new Map();
  const inicializar = id => {
    const eventos = new Map();
    const elem = {
      id, eventos, value:'', hidden:true, disabled:false, checked:false,
      dataset:{}, textContent:'', children:[], options:[], selectedOptions:[],
      classList:{toggle(){}},
      addEventListener(tipo,fn) {eventos.set(tipo,fn);},
      setAttribute(){}, scrollIntoView(){}, replaceChildren(){this.children=[];},
      append(...elementos) {this.children.push(...elementos);},
      querySelectorAll(){return [];}, querySelector(){return null;}
    };
    elementos.set(id,elem);
    return elem;
  };
  const ids = [
    `${prefijo}Cliente`,`${prefijo}Mes`,`${prefijo}Anio`,`${prefijo}VerConsultar`,
    `${prefijo}ConsultaFormulario`,`${prefijo}Proceso`,`syncStatus${libro}`,
    `${prefijo}BarraProgreso`,`${prefijo}Contadores`,`${prefijo}ListaProcesos`,
    `${prefijo}Pausar`,`${prefijo}VerEstado`
  ];
  ids.forEach(inicializar);
  const cliente = elementos.get(`${prefijo}Cliente`);
  cliente.value = '7';
  const opcion = {value:'7',dataset:{nombre:'Empresa de prueba'},textContent:'Empresa de prueba'};
  cliente.options = [{value:'0'},opcion];
  cliente.selectedOptions = [opcion];
  elementos.get(`${prefijo}Mes`).value = periodo.slice(4);
  elementos.get(`${prefijo}Anio`).value = periodo.slice(0,4);
  const conector = {
    async post(accion,payload) {
      assert.equal(accion,'inventario');
      return {periodos:inventario};
    },
    async sincronizarLibro(id, libroRecibido, per) {
      remotas.push({id,libro:libroRecibido,periodo:per});
      return {descargado:true,procesado:true,cantidad_registros:0,sin_comprobantes:true};
    }
  };
  const window = {
    location:{href:`https://sistema.asescon.net/vistas/sunat/${prefijo}.php?cliente=7&mes=${periodo.slice(4)}&anio=${periodo.slice(0,4)}`,
      assign:url=>rutas.push(url)},
    confirm:()=>true,
    AsesconSireSync:sinConector?undefined:conector
  };
  const document = {
    getElementById:id=>elementos.get(id)||null,
    querySelectorAll:()=>[],
    createElement:tag=>inicializar(`creado-${tag}-${elementos.size}`),
    createTextNode:text=>({textContent:text})
  };
  const Reloj = fechaFalsa ? class extends Date {
    constructor(...args) {super(...(args.length?args:[fechaFalsa]));}
  } : Date;
  const contexto=vm.createContext({window,document,AsesconSireSync:sinConector?undefined:conector,
    Date:Reloj,Intl,URL,URLSearchParams,console,setTimeout:(fn)=>{queueMicrotask(fn);return 1;}});
  vm.runInContext(leer(`publico/js/sunat/${prefijo}_centro.js`),contexto);
  const enviar = async () => {
    const fn = elementos.get(`${prefijo}ConsultaFormulario`).eventos.get('submit');
    assert.equal(typeof fn,'function','El formulario debe tener acción');
    let cancelado=false;
    await fn({preventDefault:()=>{cancelado=true;}});
    assert.equal(cancelado,true,'La consulta inteligente debe controlar el formulario');
  };
  return {enviar,rutas,remotas,elementos};
}

for(const libro of ['RVIE','RCE']){
  test(`${libro}: un mes histórico consultado hoy vuelve a pedir datos a SUNAT`,async()=>{
    const e=entorno(libro,[{id_cliente:7,periodo:'202510',fecha_actualizacion:fechaPeru()+' 08:00:00',cantidad_registros:6}]);
    await e.enviar();
    assert.equal(e.remotas.length,1);
    assert.equal(e.rutas.length,1);
    assert.doesNotMatch(e.rutas[0],/ver_guardado=1/);
  });

  test(`${libro}: el mes histórico consultado ayer se actualiza al consultar`,async()=>{
    const e=entorno(libro,[{id_cliente:7,periodo:'202510',fecha_actualizacion:'2025-11-01 08:00:00',cantidad_registros:6}]);
    await e.enviar();
    assert.deepEqual(e.remotas,[{id:7,libro,periodo:'202510'}]);
  });

  test(`${libro}: mes sin datos inicia consulta y valida incluso cero comprobantes`,async()=>{
    const e=entorno(libro,[]);
    await e.enviar();
    assert.deepEqual(e.remotas,[{id:7,libro,periodo:'202510'}]);
  });

  test(`${libro}: dos pulsaciones consecutivas hacen dos consultas reales`,async()=>{
    const e=entorno(libro,[{id_cliente:7,periodo:'202510',fecha_actualizacion:fechaPeru()+' 08:00:00'}]);
    await e.enviar();
    await e.enviar();
    assert.equal(e.remotas.length,2);
    assert.equal(e.elementos.get(libro.toLowerCase()+'VerConsultar').textContent,'Consultar');
  });

  test(`${libro}: mes actual consultado hoy se vuelve a solicitar remotamente`,async()=>{
    const p=fechaPeru().slice(0,7).replace('-','');
    const e=entorno(libro,[{id_cliente:7,periodo:p,fecha_actualizacion:fechaPeru()+' 08:30:00',cantidad_registros:0}],{periodo:p});
    await e.enviar();
    assert.equal(e.remotas.length,1);
    assert.equal(e.rutas.length,1);
  });

  test(`${libro}: mes actual guardado ayer se actualiza desde SUNAT`,async()=>{
    const p=fechaPeru().slice(0,7).replace('-','');
    const e=entorno(libro,[{id_cliente:7,periodo:p,fecha_actualizacion:'2026-01-01 08:30:00',cantidad_registros:3}],{periodo:p});
    await e.enviar();
    assert.equal(e.remotas.length,1);
  });

  test(`${libro}: consultar es obligatorio, sin casilla de consulta local`,async()=>{
    const e=entorno(libro,[{id_cliente:7,periodo:'202510',fecha_actualizacion:'2025-11-01 08:00:00',cantidad_registros:6}]);
    await e.enviar();
    assert.equal(e.remotas.length,1);
    assert.doesNotMatch(leer(`vistas/sunat/${libro.toLowerCase()}.php`),/ForzarSunat|ver_guardado|Ver\/consultar/);
  });

  test(`${libro}: la vista anual solicita todos los meses`,async()=>{
    const e=entorno(libro,[],{periodo:'202500'});
    await e.enviar();
    assert.equal(e.remotas.length,12);
    assert.equal(e.rutas.length,1);
  });

  test(`${libro}: anual consulta enero-diciembre uno por uno, incluso meses ya consultados`,async()=>{
    const e=entorno(libro,[{id_cliente:7,periodo:'202501',fecha_actualizacion:'2025-01-31 19:00:00',cantidad_registros:8}],{periodo:'202500'});
    await e.enviar();
    assert.deepEqual(e.remotas.map(t=>t.periodo),Array.from({length:12},(_,i)=>'2025'+String(i+1).padStart(2,'0')));
    assert.ok(e.remotas.every(t=>t.id===7&&t.libro===libro));
    assert.equal(e.rutas.length,1,'Al completar el lote sin fallos se actualiza la misma vista automáticamente.');
    assert.match(e.elementos.get(libro.toLowerCase()+'Contadores').textContent,/12\/12/);
  });

  test(`${libro}: anual del año actual se detiene en el mes vigente en Perú`,async()=>{
    const actual=fechaPeru();const year=actual.slice(0,4);const meses=Number(actual.slice(5,7));
    const e=entorno(libro,[],{periodo:year+'00'});
    await e.enviar();
    assert.equal(e.remotas.length,meses);
    assert.equal(e.remotas.at(-1).periodo,year+String(meses).padStart(2,'0'));
  });

  test(`${libro}: enero de 2027 consulta solo 202701, sin febrero ni meses futuros`,async()=>{
    const e=entorno(libro,[],{periodo:'202700',fechaFalsa:'2027-01-15T12:00:00-05:00'});
    await e.enviar();
    assert.deepEqual(e.remotas.map(t=>t.periodo),['202701']);
  });

  test(`${libro}: dos empresas y anual se consultan en orden por empresa y mes`,async()=>{
    const e=entorno(libro,[],{periodo:'202500'});
    const selector=e.elementos.get(libro.toLowerCase()+'Cliente');
    selector.options.push({value:'8',dataset:{nombre:'Segunda empresa'},textContent:'Segunda empresa'});
    selector.value='0';
    await e.enviar();
    assert.equal(e.remotas.length,24);
    assert.equal(e.remotas[0].id,7);
    assert.equal(e.remotas[11].periodo,'202512');
    assert.equal(e.remotas[12].id,8);
    assert.equal(e.remotas[23].periodo,'202512');
  });

  test(`${libro}: los permisos de lectura impiden el lote anual remoto`,async()=>{
    const e=entorno(libro,[],{periodo:'202500'});
    e.elementos.get(libro.toLowerCase()+'VerConsultar').dataset.soloLectura='1';
    await e.enviar();
    assert.equal(e.remotas.length,0);
    assert.match(e.elementos.get('syncStatus'+libro).textContent,/no tiene permiso/);
  });

  test(`${libro}: no ofrece recuperar resultados sin consultar SUNAT`,async()=>{
    const e=entorno(libro,[],{periodo:'202510'});
    const prefijo=libro.toLowerCase();
    assert.doesNotMatch(leer(`vistas/sunat/${prefijo}.php`),new RegExp(`${prefijo}RecuperarDatos`));
    assert.doesNotMatch(leer(`publico/js/sunat/${prefijo}_centro.js`),/post\('reprocesar'/);
    await e.enviar();
    assert.equal(e.remotas.length,1);
  });

  test(`${libro}: informa cuando falta el conector en vez de ignorar el clic`,async()=>{
    const e=entorno(libro,[],{sinConector:true});
    await e.enviar();
    assert.match(e.elementos.get('syncStatus'+libro).textContent,/No se cargó el módulo/);
    assert.equal(e.elementos.get(libro.toLowerCase()+'Proceso').hidden,false);
  });
}

test('RVIE/RCE renuevan el JS y CSS automáticamente para evitar botones sin manejadores en caché',()=>{
  for(const prefijo of ['rvie','rce']){
    const vista=leer(`vistas/sunat/${prefijo}.php`);
    for(const archivo of [`${prefijo}_centro.js`,'sincronizacion_sire.js',`${prefijo}_centro.css`]){
      assert.match(vista,new RegExp(`@filemtime\\(__DIR__ .*?${archivo.replace('.','\\.')}.*?\\)`));
    }
    assert.doesNotMatch(vista,/20261029_v3/);
    assert.match(vista,new RegExp(`id="${prefijo}VerConsultar"`));
    assert.match(vista,new RegExp(`id="${prefijo}VerConsultar" disabled`));
    assert.match(vista,/>Consultar<\/button>/);
  }
});
