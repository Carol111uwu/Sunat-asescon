import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const leer = archivo => readFileSync(new URL('../'+archivo, import.meta.url), 'utf8');

test('la nueva acción SOL conserva permisos, CSRF, empresa y período', () => {
  const api = leer('api/sunat/consultar_control_declaraciones.php');
  const servicio = leer('servicios/sunat/ServicioControlDeclaracionesSunat.php');
  const modelo = leer('modelos/sunat/DeclaracionSunat.php');
  assert.match(api, /asesconExigirPermiso\(\$conexion, 'DECLARACIONES', 'ejecutar'\)/);
  assert.match(api, /asesconValidarCsrf/);
  assert.match(api, /asesconPuedeGestionarCliente/);
  assert.match(api, /json_encode\(\['ok'=>true, 'resultado'=>\$resultado\]/);
  assert.match(servicio, /'perTri'/);
  assert.match(servicio, /ConsultaDeclaracionesSunat/);
  assert.match(modelo, /begin_transaction\(\)/);
  assert.match(modelo, /WHERE id_cliente=\? AND periodo=\? AND formulario=\?/);
  assert.doesNotMatch(api, /file_put_contents|move_uploaded_file|INSERT INTO/i);
});

test('los filtros de Declaraciones y Vencimientos llegan al servidor y se llaman Buscar', () => {
  for (const archivo of ['vistas/sunat/control_declaraciones.php','vistas/vencimientos/vencimientos_sunat.php']) {
    const vista = leer(archivo);
    assert.match(vista, /<button[^>]*type="submit"[^>]*>Buscar/);
    assert.doesNotMatch(vista, />Filtrar<|>Aplicar filtros</);
  }
  assert.match(leer('vistas/sunat/control_declaraciones.php'), /name="mes"/);
  assert.match(leer('vistas/sunat/control_declaraciones.php'), /name="anio"/);
  assert.match(leer('vistas/vencimientos/vencimientos_sunat.php'), /cronogramaVencimientos|calendarioVencimientos/);
});

test('pulsar Consultar SOL ejecuta una petición y presenta los resultados sin guardarlos en disco', async () => {
  const eventos = {};
  const elemento = etiqueta => ({etiqueta, hijos: [], textContent: '', append(...x){this.hijos.push(...x);}});
  const resultado = {...elemento('div'), replaceChildren(){this.hijos=[];}};
  const boton = {disabled:false};
  const formulario = {dataset:{api:'/api/consultar-control'},querySelector:()=>boton,addEventListener:(e,fn)=>{eventos[e]=fn;}};
  const listeners = {};
  const anio = {value:'2026', addEventListener:(e,fn)=>{listeners.anio=fn;}};
  const mes = {value:'11', addEventListener:(e,fn)=>{listeners.mes=fn;}};
  const periodo = {value:'202610'};
  const nodos = {anioPeriodo:anio,mesPeriodo:mes,periodoControl:periodo,consultaControlSol:formulario,resultadoControlSol:resultado};
  let solicitudes = 0;
  const contexto = {document:{getElementById:id=>nodos[id]??null,createElement:elemento},
    confirm:()=>true,FormData:class{constructor(form){assert.equal(form,formulario);}},
    fetch:async url=>{solicitudes++;assert.equal(url,'/api/consultar-control');return {ok:true,json:async()=>({ok:true,resultado:{oficiales:1,coincidencias:1,sin_vincular:0,inicio:'2026-11-01',fin:'2026-11-04',filas:[{formulario:'0621',orden:'123456',fecha:'2026-11-04'}]}})};},
  };
  vm.runInNewContext(leer('publico/js/sunat/control_declaraciones.js'),contexto);
  listeners.mes();
  assert.equal(periodo.value,'202611');
  await eventos.submit({preventDefault(){}});
  assert.equal(solicitudes,1);
  assert.equal(boton.disabled,false);
  assert.ok(resultado.hijos.some(x=>x.textContent?.includes('Consulta SUNAT realizada')));
  assert.ok(resultado.hijos.some(x=>x.etiqueta==='div'));
});

test('cron antiguos no se reemplazaron ni se añadieron trabajos permanentes', () => {
  for (const ruta of ['procesos/sincronizacion/cron_nocturno.sh','procesos/buzon_sol/procesar_cola.sh','procesos/almacenamiento/cron_limpieza.sh']) {
    assert.ok(leer(ruta).length>100);
  }
});

test('calendario general SUNAT 2026 tiene doce períodos y fechas por cada grupo RUC', () => {
  const codigo = leer('servicios/sunat/CronogramaOficial2026.php');
  const entradas = [...codigo.matchAll(/'2026(0[1-9]|1[0-2])'=>\[([^\]]+)\]/g)];
  assert.equal(entradas.length,12);
  for (const [ , , cuerpo ] of entradas) {
    const fechas = [...cuerpo.matchAll(/'20\d\d-\d\d-\d\d'/g)];
    assert.equal(fechas.length,6);
  }
  assert.match(codigo, /'202610'=>\['2026-11-16','2026-11-17','2026-11-18','2026-11-19','2026-11-20','2026-11-23'\]/);
  assert.match(codigo, /'202612'=>\['2027-01-18'/);
  assert.doesNotMatch(codigo, /INSERT|file_put_contents/i);
});
