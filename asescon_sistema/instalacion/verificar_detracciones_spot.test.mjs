import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const read=p=>readFileSync(new URL('../'+p,import.meta.url),'utf8');

test('SPOT separa constancias, RCE, consultas adicionales y liberación sin simular pagos',()=>{
  const vista=read('vistas/sunat/detracciones.php');
  const ctl=read('controladores/sunat/ControladorDetraccion.php');
  const parser=read('servicios/sunat/ServicioImportacionDetraccionesSpot.php');
  for(const section of ['consultas','pagos','otras','fondos'])assert.match(vista,new RegExp("'"+section+"'"));
  assert.match(vista,/Sin verificación en vivo|sin verificación en vivo/);
  assert.match(vista,/RCE\/SIRE|comprobantes del RCE/i);
  assert.match(ctl,/r\.estado='PROCESADO'/);
  assert.match(ctl,/asesconValidarCsrf/);
  assert.match(ctl,/asesconPuedeGestionarCliente/);
  assert.match(parser,/ruc_titular/);
  assert.match(parser,/PeriodoSire|PeriodosSire::permitido/);
  assert.doesNotMatch(ctl,/new Detraccion\(/);
  assert.doesNotMatch(vista,/Registrar detracción manualmente/);
});

test('Importaciones transaccionales y deduplicadas, sin borrar la tabla anterior',()=>{
  const repo=read('modelos/sunat/RepositorioDetraccionesSpot.php');
  const sql=read('base_datos/migraciones/2026_11_03_constancias_detracciones_spot.sql');
  assert.match(repo,/begin_transaction\(\)/);
  assert.match(repo,/rollback\(\)/);
  assert.match(repo,/ON DUPLICATE KEY UPDATE/);
  assert.match(sql,/CREATE TABLE IF NOT EXISTS detracciones_archivos_sunat/);
  assert.match(sql,/UNIQUE KEY uk_spot_cliente_clave/);
  assert.doesNotMatch(sql,/(?:DROP|TRUNCATE|DELETE FROM)\s+detracciones/i);
});

test('Botón Exportar borrador responde a selección y valida concepto/porcentaje',()=>{
  const codigo=read('publico/js/sunat/detracciones_spot.js');
  function evento(){return {eventos:new Map(),addEventListener(e,fn){this.eventos.set(e,fn)},emit(e){this.eventos.get(e)?.()}}}
  const pick={...evento(),checked:false,dataset:{registro:JSON.stringify({periodo:'202610',fecha_emision:'2026-10-02',tipo_comprobante:'01',serie:'F001',numero:'17',ruc_proveedor:'20123456789',razon_social_proveedor:'Prueba',importe_total:'100.00'})}};
  const boton={...evento(),disabled:true};
  const concepto={...evento(),value:''};
  const porcentaje={...evento(),value:''};
  const resumen={textContent:''};
  const ids={spotExportarBorrador:boton,spotConcepto:concepto,spotPorcentaje:porcentaje,spotSeleccionResumen:resumen};
  const click=[];
  class BlobMock{constructor(partes){this.partes=partes}}
  vm.runInNewContext(codigo,{
    document:{
      querySelectorAll(sel){return sel==='#spotCandidatos input.spot-pick'?[pick]:[]},
      getElementById(id){return ids[id]||null},
      createElement(){return {set href(v){this._href=v},set download(v){this._download=v},click(){click.push([this._href,this._download])}}}
    },
    URL:{createObjectURL(){return 'blob:prueba'},revokeObjectURL(){}},
    Blob:BlobMock,
    window:{setTimeout(fn){fn()}},
    Date
  });
  assert.equal(boton.disabled,true);
  pick.checked=true;pick.emit('change');
  concepto.value='009';concepto.emit('input');
  porcentaje.value='12.00';porcentaje.emit('input');
  assert.equal(boton.disabled,false);
  boton.emit('click');
  assert.equal(click.length,1);
  assert.match(click[0][1],/borrador_sin_pago/);
  assert.match(resumen.textContent,/No se ha pagado ni enviado/);
});
