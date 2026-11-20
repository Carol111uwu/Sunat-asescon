import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const ver=ruta=>readFileSync(new URL('../'+ruta,import.meta.url),'utf8');
const docker=readFileSync(new URL('../../docker/tareas.cron',import.meta.url),'utf8');

test('Rondas automáticas: medianoche, cola escalonada, sin consultar sesión web',()=>{
  const php=ver('procesos/buzones/planificar_rondas.php');
  assert.match(docker,/planificar_rondas\.php/);
  assert.match(docker,/buzon_sunafil\/procesar_cola\.sh/);
  assert.match(php,/America\/Lima/);
  assert.match(php,/getenv\('ASESCON_INTERVALO_BUZONES'\)/);
  assert.match(php,/getenv\('ASESCON_BUZONES_POR_RONDA'\)/);
  assert.match(php,/\$fechaAnterior!==\$fecha/);
  assert.match(php,/\$enCurso/);
  assert.doesNotMatch(php, /asesconFiltroClientesSql/);
});
test('La sincronización histórica no restringe el año y reporta límites reales',()=>{
  const py=ver('python/sunat/consultar_buzon_sol.py');
  assert.match(py,/ASESCON_SOL_MAX_PAGINAS/);
  assert.match(py,/SOL_PAGINACION_EXTENSA/);
  assert.doesNotMatch(py,/range\(80\)/);
  assert.match(py,/SOL_PAGINACION_INCOMPLETA/);
});
test('Ambos buzones permiten saltar a cualquier empresa autorizada en Ver todas',()=>{
  const nav=ver('vistas/buzones/componentes/navegador_empresas.php');
  const js=ver('publico/js/buzones/navegador_empresas.js');
  for(const vista of ['vistas/buzones/buzon_sol_sunat.php','vistas/buzones/buzon_sunafil.php'])
    assert.match(ver(vista),/componentes\/navegador_empresas\.php/);
  assert.match(nav,/clientes_disponibles/);
  assert.match(js,/searchParams\.set\('pagina'/);
  assert.match(js,/searchParams\.set\('id_cliente','0'\)/);
});
test('Avisos nuevos: primera lectura = historial, después sólo identificadores nuevos',()=>{
  const php=ver('servicios/buzones/RegistroNovedadesBuzones.php');
  assert.match(php,/if\(\$yaInicializado\)/);
  assert.match(php,/id_documento > \?/);
  assert.match(php,/INSERT IGNORE INTO buzon_novedades/);
  assert.match(ver('procesos/buzon_sol/guardar_resultado.php'),/registrarExito\('SOL'/);
  assert.match(ver('procesos/buzon_sunafil/guardar_resultado.php'),/registrarExito\('SUNAFIL'/);
  assert.match(ver('api/buzones/avisos_nuevos.php'),/asesconFiltroClientesSql/);
  assert.match(ver('api/buzones/avisos_nuevos.php'),/asesconValidarCsrf/);
  assert.match(ver('vistas/componentes/avisos_casos_sol.php'),/avisos_nuevos\.js/);
});
test('Los PDF no se precargan, la eliminación requiere opción y descarga completa',()=>{
  const sh=ver('procesos/buzon_sol/procesar_cola.sh');
  const api=ver('api/buzones/archivo_documento.php');
  const arch=ver('servicios/archivos/ServicioArchivosPrivados.php');
  assert.match(sh,/ASESCON_PRECARGAR_PDF:-0/);
  assert.match(api,/ASESCON_PDF_SOLO_DISPOSITIVO/);
  assert.match(arch,/\$restante===0/);
  assert.match(arch,/!connection_aborted\(\)/);
});
