import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const ver = ruta => readFileSync(new URL('../' + ruta, import.meta.url), 'utf8');

test('SOL: una consulta explícita, selección de cliente y acciones secundarias', () => {
  const vista=ver('vistas/buzones/buzon_sol_sunat.php');
  assert.match(vista,/data-buzon-consulta="SOL"/);
  assert.match(vista,/data-buzon-cliente/);
  assert.match(vista,/Consultar todos en SOL/);
  assert.doesNotMatch(vista,/action="\.\.\/\.\.\/controladores\/buzones\/ControladorSincronizacionBuzones\.php"/);
  assert.match(vista,/data-buzon-detalle="SOL"/);
  assert.match(vista,/data-buzon-pdf/);
  assert.match(vista,/class="buzon-mas-acciones"/);
  assert.match(vista,/data-accion-buzon="notas"/);
  assert.match(vista,/data-accion-buzon="asignacion"/);
  assert.match(vista,/data-accion-inicial="correo"/);
  assert.match(vista,/data-accion-inicial="whatsapp"/);
  assert.match(vista,/data-accion-inicial="caso"/);
  assert.match(vista,/Marcar revisada/);
  assert.doesNotMatch(vista,/href="https:\/\/e-menu\.sunat\.gob\.pe/);
  for(const columna of ['F. notificación','Cliente','Estado','F. consultado','Categoría','Asunto','Prioridad','Acciones']){
    assert.ok(vista.includes('<th>'+columna+'</th>'),columna);
  }
  assert.match(vista,/componentes\/estado_consulta\.php/);
});

test('SUNAFIL: filtros del servidor, consulta separada y detalle de requerimiento', () => {
  const vista=ver('vistas/buzones/buzon_sunafil.php');
  const controlador=ver('controladores/buzones/ControladorBuzonSunafil.php');
  const modelo=ver('modelos/buzones/BuzonSunafil.php');
  assert.match(vista,/data-buzon-consulta="SUNAFIL"/);
  assert.match(vista,/Consultar todos en SUNAFIL/);
  assert.match(vista,/data-buzon-detalle="SUNAFIL"/);
  assert.match(vista,/Marcar revisado/);
  assert.doesNotMatch(vista,/href="https:\/\/casillaelectronica\.sunafil\.gob\.pe/);
  for(const columna of ['Estado','Origen','F. notificación','Cliente','Asunto','Registro','Plazo \/ prioridad','F. límite','PDF \/ acción']){
    assert.ok(vista.includes('<th>'+columna+'</th>'),columna);
  }
  assert.match(controlador,/contarFiltrados\(/);
  assert.match(modelo,/clientesConNotificaciones\(/);
  assert.match(controlador,/\$clienteListado>0\?\$m->listar\(/);
  assert.match(vista,/Siguiente empresa/);
  assert.match(modelo,/b\.tipo_documento=/);
  assert.match(modelo,/DATE\(COALESCE\(b\.fecha_recepcion,b\.fecha_documento\)\)/);
  assert.match(ver('procesos/buzon_sunafil/preparar_solicitud.php'),/obtenerSolPorCliente\(\$idCliente\)/);
});

test('Una consulta masiva solo en el buzón elegido, sin duplicar credenciales', () => {
  const programador=ver('servicios/buzones/ProgramadorBuzones.php');
  assert.match(programador,/in_array\(\$buzon, \['SOL', 'SUNAFIL'\]/);
  assert.match(programador,/asesconFiltroClientesSql/);
  assert.match(programador,/cs\.clave_sol_cifrada/);
  assert.match(ver('controladores/buzones/ControladorBuzonSol.php'),/todos\('SOL'\)/);
  assert.match(ver('controladores/buzones/ControladorBuzonSunafil.php'),/todos\('SUNAFIL'\)/);
});

test('Estado real de la cola, sondeo y exportaciones de columnas correctas', () => {
  const script=ver('publico/js/buzones/consulta_unificada.js');
  assert.match(script,/data-buzon-consulta/);
  assert.match(script,/cron_reciente/);
  assert.match(script,/window\.location\.reload\(\)/);
  assert.match(script,/new FormData\(formulario\)/);
  assert.match(ver('api/buzones/estado_consulta_sol.php'),/cron_reciente/);
  assert.match(ver('api/buzones/estado_consulta_sunafil.php'),/cron_reciente/);
  assert.match(ver('publico/js/buzones/exportar_notificaciones_sol.js'),/F\. notificación','Cliente','Estado','F\. consultado','Categoría','Asunto','Prioridad/);
  assert.match(ver('publico/js/buzones/exportar_notificaciones_sunafil.js'),/Estado','Origen','F\. notificación','Cliente','Asunto','Registro','Plazo \/ prioridad','F\. límite/);
  assert.match(ver('vistas/buzones/componentes/estado_consulta.php'),/buzon-expandir-mas/);
  assert.match(script,/AbortController/);
  assert.match(script,/Comprobar estado ahora/);
  assert.match(ver('api/buzones/estado_consulta_sol.php'),/'cola'=>\$detalleCola/);
  assert.match(ver('api/buzones/estado_consulta_sunafil.php'),/'cola'=>\$detalleCola/);
  assert.match(ver('servicios/sunat/ServicioColaBuzonSol.php'),/time\(\) - 3600/);
  assert.match(ver('servicios/sunafil/ServicioColaBuzonSunafil.php'),/time\(\) - 3600/);
});

test('Cron registra actividad solo después de comprobar el entorno', () => {
  for(const tipo of ['sol','sunafil']){
    const script=ver('procesos/buzon_'+tipo+'/procesar_cola.sh');
    assert.ok(script.indexOf('if [ ! -x "$PYTHON_BIN" ]')<script.indexOf('ultima_ejecucion_cron'));
  }
  assert.match(ver('servicios/sunat/DiagnosticoBuzonSol.php'),/time\(\) - 900/);
});
