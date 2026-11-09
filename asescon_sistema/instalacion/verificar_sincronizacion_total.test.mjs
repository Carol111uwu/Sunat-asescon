import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read=p=>readFileSync(new URL('../'+p,import.meta.url),'utf8');

test('Cola nocturna prioriza padrones y buzones sin bloquear otros módulos',()=>{
 const cola=read('servicios/sincronizacion/ColaNocturna.php');
 assert.match(cola,/\['PADRON_RUC'=>0,'SSCO'=>1,'SOL'=>2,'SUNAFIL'=>3,'RVIE'=>4,'RCE'=>5,'DECLARACIONES'=>6\]/);
 assert.match(cola,/usort\(\$candidatos/);
 assert.match(cola,/\(int\)\(\$t\['reintentar'\]\?\?0\)>time\(\)\)continue/);
});

test('El planificador nocturno consulta solo el mes calendario actual',()=>{
 const plan=read('servicios/sincronizacion/PlanificadorSincronizacion.php');
 const estado=read('servicios/sincronizacion/EstadoSireGuardado.php');
 assert.match(estado,/periodosActuales/);
 assert.match(plan,/return \[EstadoSireGuardado::periodoActual\(\)\]/);
 assert.match(plan,/!empty\(\$previo\['guardado'\]\)/);
 assert.doesNotMatch(plan,/periodosActuales\(\)/);
 assert.match(estado,/\$fecha===\$ahora->format\('Y-m-d'\)/);
 assert.match(plan,/foreach\(\['RVIE','RCE'\] as \$libro\)/);
});

test('El ejecutor reutiliza los mismos servicios RVIE/RCE y detecta datos ya guardados',()=>{
 const ejecutar=read('servicios/sincronizacion/EjecutorNocturno.php');
 assert.match(ejecutar,/new ServicioOperacionesSire\(\$this->db\)/);
 assert.match(ejecutar,/EstadoSireGuardado\(\$this->db\)/);
 assert.match(ejecutar,/\$guardado\['completo'\]/);
 assert.match(ejecutar,/\$hoy->format\('Y-m'\)\.'-01'/);
});

test('La interfaz contiene tres filtros enlazados y Dashboard independiente',()=>{
 const progreso=read('vistas/utilitarios/componentes/progreso_global.php');
 const dashboard=read('vistas/panel_principal.php');
 const controlador=read('controladores/sincronizacion/ControladorSincronizacionGeneral.php');
 for(const id of ['syncEmpresaBoton','syncPeriodo','syncModulo'])assert.match(progreso,new RegExp(id));
 assert.match(dashboard,/syncDashboardMini/);
 assert.match(dashboard,/utilitarios\/sincronizacion\.php#syncPanel/);
 assert.match(dashboard,/name="origen" value="dashboard"/);
 assert.match(controlador,/asesconValidarCsrf/);
 assert.match(controlador,/\$volverDashboard/);
});

test('El cron general conserva lock, no duplica SOL y planifica a las 02',()=>{
 const cron=read('procesos/sincronizacion/cron_nocturno.sh');
 const cola=read('servicios/sincronizacion/ColaNocturna.php');
 assert.match(cron,/flock -n 8/);
 assert.doesNotMatch(cron,/\$PROYECTO\/procesos\/buzon_sol\/procesar_cola\.sh/);
 assert.match(cron,/\$PROYECTO\/procesos\/buzon_sunafil\/procesar_cola\.sh/);
 assert.match(cola,/\$ahora->format\('H'\)==='02'/);
 assert.doesNotMatch(cola,/ASESCON_SYNC_TOTAL_HOUR/);
});

test('Una espera de ticket o buzón no puede bloquear la cola para siempre',()=>{
 const ejecutar=read('servicios/sincronizacion/EjecutorNocturno.php');
 const resumen=read('servicios/sincronizacion/ResumenSincronizacion.php');
 assert.match(ejecutar,/time\(\)-\$inicioTicket>=7200/);
 assert.match(ejecutar,/time\(\)-\$inicioBuzon>=7200/);
 assert.match(ejecutar,/TICKET_SUNAT_SIN_RESPUESTA/);
 assert.match(ejecutar,/BUZON_SIN_RESPUESTA/);
 assert.match(resumen,/Mes pendiente de verificar/);
});

test('La señal del cron sigue actualizándose cuando está trabajando',()=>{
 const cron=read('procesos/sincronizacion/cron_nocturno.sh');
 const estado=read('api/sincronizacion/estado_global.php');
 assert.match(cron,/MARCA_PID=\$!/);
 assert.match(cron,/ultima_ejecucion_cron/);
 assert.match(estado,/runtime_preparado/);
 assert.match(estado,/cron_activo/);
});
