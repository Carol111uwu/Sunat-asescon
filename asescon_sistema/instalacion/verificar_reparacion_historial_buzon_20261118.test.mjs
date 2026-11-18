import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const ver = ruta => readFileSync(new URL('../'+ruta, import.meta.url),'utf8');

test('RCE y RVIE muestran periodos guardados de fechas anteriores',()=>{
  for(const libro of ['rce','rvie']) {
    const vista=ver(`vistas/sunat/${libro}.php`);
    assert.match(vista,/Datos guardados por empresa y período/);
    assert.doesNotMatch(vista,/Guardados de hoy|datos guardados hoy/i);
  }
  const lector=ver('modelos/sunat/RepositorioLecturaSire.php');
  assert.match(lector,/\$this->cacheDesde = null;/);
  assert.doesNotMatch(lector,/AND s\.fecha_actualizacion >=/);
  assert.match(ver('publico/js/sunat/datos_guardados_sire.js'),/Períodos guardados disponibles/);
});

test('Buzón SOL permite navegar por empresas autorizadas y separa totales',()=>{
  const controlador=ver('controladores/buzones/ControladorBuzonSol.php');
  assert.match(controlador,/\$clientesPaginados=\$idCliente===0\?\$clientes:/);
  assert.match(controlador,/cliente_en_vista_id/);
  const vista=ver('vistas/buzones/buzon_sol_sunat.php');
  assert.match(vista,/aria-label="Empresa anterior"/);
  assert.match(vista,/aria-label="Empresa siguiente"/);
  assert.match(vista,/Notificaciones de esta empresa/);
  assert.match(vista,/Notificaciones globales/);
  assert.match(vista,/Ver todas/);
  assert.match(vista,/data-cliente-visible/);
  assert.match(ver('publico/js/buzones/consulta_unificada.js'),/dataset.clienteVisible/);
});

test('Estilos de Buzón SOL legibles, con acciones alineadas a la derecha y tamaño adaptable',()=>{
  const css=ver('publico/css/modulos/asescon_sol_ajustes_20261118.css');
  assert.match(css,/\[data-tema="oscuro"\]/);
  assert.match(ver('publico/css/modulos/buzones_ordenados.css'),/justify-content: flex-end/);
  assert.match(css,/@media \(max-width: 430px\)/);
  assert.match(css,/\.ventana-sol/);
  const logo=ver('publico/css/base/tema.css');
  assert.match(logo,/logotipo y dimensiones entre tema claro y oscuro/);
});

test('Limpieza puede caducar consultas solo con permiso expreso y conserva buzones',()=>{
  const limpieza=ver('procesos/almacenamiento/limpiar_dia_anterior.php');
  assert.match(limpieza,/ASESCON_LIMPIEZA_SQL_DIARIA/);
  assert.doesNotMatch(limpieza,/\['buzon_sol_sunat'/);
  assert.doesNotMatch(limpieza,/\['buzon_sunafil'/);
  assert.match(limpieza,/'sunat', 'sunafil'/);
});
