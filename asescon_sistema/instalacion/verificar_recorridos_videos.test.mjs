import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const read=p=>readFileSync(new URL('../'+p,import.meta.url),'utf8');

test('Planes: edición completa, archivo/restauración y borrado físico, con CSRF y permisos',()=>{
 const v=read('vistas/clientes/secciones/planes.php'),c=read('controladores/clientes/ControladorPlanes.php'),m=read('modelos/clientes/PlanServicio.php');
 for(const a of ['editar','eliminar'])assert.match(v,new RegExp(`value="${a}"`));
 assert.match(v,/\$verArchivados\?'restaurar':'archivar'/);
 assert.match(v,/vista=archivados/);assert.match(v,/Editar plan/);assert.match(v,/Eliminar plan/);
 assert.match(c,/asesconValidarCsrf/);assert.match(c,/asesconExigirPermiso/);
 assert.match(m,/DELETE FROM planes_servicio WHERE id_plan=\?/);
});

test('Honorarios: editar, eliminar, confirmar pago y revertir a pendiente',()=>{
 const v=read('vistas/clientes/secciones/honorarios.php'),c=read('controladores/clientes/ControladorHonorarios.php'),m=read('modelos/clientes/Honorario.php');
 for(const a of ['editar','eliminar'])assert.match(v,new RegExp(`value="${a}"`));
 assert.match(v,/Revertir a pendiente/);assert.match(v,/onsubmit="return confirm/);
 assert.match(c,/['"]pagar['"],['"]reabrir['"],['"]editar['"],['"]eliminar['"]/);
 assert.match(m,/DELETE h FROM honorarios/);assert.match(m,/h\.estado='PENDIENTE',h\.fecha_pago=NULL/);
 assert.match(v,/Archivados \/ ocultos/);assert.match(v,/\$verArchivados\?'restaurar':'archivar'/);
 assert.match(c,/new ArchivoTextos/);
});

test('Pantalla de sincronización: menú y navegación funcionales durante el avance',()=>{
 const v=read('vistas/utilitarios/sincronizacion.php');
 assert.match(v,/navegacion\/menu_lateral\.js/);
 assert.match(v,/navegacion\/menu_movil\.js/);
 assert.match(v,/progreso_global\.php/);
 const progreso=read('publico/js/sincronizacion/progreso_total.js');
 assert.match(progreso,/setInterval\(cargar,15000\)/);
 assert.match(progreso,/AbortController/);
 assert.match(progreso,/syncEmpresaBoton/);
 assert.match(progreso,/syncPeriodo/);
 assert.match(progreso,/syncModulo/);
 assert.match(progreso,/Consultas con PDF pendiente/);
 assert.match(progreso,/Notificaciones consultadas · faltan PDF/);
 assert.match(progreso,/PDF pendientes de descargar/);
 assert.doesNotMatch(progreso,/sync-pdf-empresa|sync-pdf-pendientes/);
});

test('Filtros de períodos en módulos restantes: mes y año por selección',()=>{
 const afp=read('vistas/afp/afp.php'),pagos=read('vistas/sunat/pagos_tributarios.php'),detr=read('vistas/sunat/detracciones.php'),controller=read('controladores/sunat/ControladorDetraccion.php');
 for(const v of [afp,pagos,detr]){
  assert.match(v,/<select name="mes">/);
  assert.match(v,/<select name="anio">/);
  assert.match(v,/reference-filter-grid three/);
 }
 assert.match(detr,/name="periodo_archivo"/);
 assert.match(detr,/name="archivo_sunat"/);
 assert.match(controller,/\$a==='importar_sunat'/);
 assert.doesNotMatch(detr,/Registrar detracción manualmente/);
});

test('Cotizaciones: formulario de edición de ítems y eliminación transaccional',()=>{
 const v=read('vistas/clientes/secciones/cotizaciones.php'),c=read('controladores/clientes/ControladorCotizaciones.php'),m=read('modelos/clientes/Cotizacion.php'),js=read('publico/js/clientes/cotizaciones.js');
 assert.match(v,/Editar cotización/);assert.match(v,/Eliminar cotización/);assert.match(v,/value="eliminar_cotizacion"/);
 assert.match(js,/editar_cotizacion/);assert.match(js,/addItem\(i\.descripcion,i\.precio_unitario,i\.tipo_item,i\.id_servicio,i\.cantidad\)/);
 assert.match(c,/asesconValidarCsrf/);assert.match(c,/COTIZACIONES','eliminar'/);
 assert.match(m,/begin_transaction\(\)/);assert.match(m,/DELETE FROM cotizacion_items WHERE id_cotizacion/);assert.match(m,/DELETE FROM cotizaciones WHERE id_cotizacion/);
});

test('Recordatorios: pestaña de archivados, corrección posterior al envío, restauración y borrado real',()=>{
 const v=read('vistas/clientes/secciones/recordatorios.php'),acciones=read('vistas/clientes/secciones/acciones_recordatorio.php'),c=read('controladores/clientes/ControladorWhatsApp.php'),m=read('modelos/clientes/RecordatorioWhatsApp.php');
 assert.match(v,/Archivados \/ ocultos/);assert.match(v,/\$archivados\['recordatorios'\]/);
 for(const a of ['editar_recordatorio','eliminar_recordatorio','archivar_recordatorio','restaurar_recordatorio','reabrir_recordatorio'])assert.match(acciones,new RegExp(a));
 assert.match(acciones,/¿Confirmas que enviaste/);assert.match(acciones,/No deshace mensajes ya enviados/);
 assert.match(c,/new ArchivoTextos/);assert.match(c,/asesconValidarCsrf/);
 assert.match(m,/DELETE w FROM recordatorios_whatsapp/);assert.match(m,/w\.estado='PENDIENTE',w\.fecha_enviado=NULL/);
 assert.match(m,/function archivados\(/);assert.match(c,/\$model->archivados\(/);
});

test('Plantillas: editar todos los campos y eliminar la fila, con restricción de cuenta principal',()=>{
 const a=read('vistas/clientes/secciones/acciones_plantilla.php'),c=read('controladores/clientes/ControladorWhatsApp.php'),m=read('modelos/clientes/PlantillaWhatsApp.php');
 for(const v of ['editar_plantilla','eliminar_plantilla','archivar_plantilla','restaurar_plantilla'])assert.match(a,new RegExp(v));
 assert.match(a,/asesconEsPrincipal/);assert.match(c,/Solo la cuenta principal/);
 assert.match(m,/DELETE FROM plantillas_whatsapp WHERE id_plantilla/);
});

test('Notas del buzón SOL: borra registros internos, nunca documentos oficiales',()=>{
 const c=read('servicios/textos/CatalogoTextos.php');
 assert.match(c,/\['nota_sol','avance_sol','observacion_cliente'\]/);
 assert.match(c,/DELETE FROM \{\$tabla\} WHERE \{\$pk\}=\?/);
 assert.doesNotMatch(c,/DELETE FROM sunat_buzon_documentos/);
});

test('Interfaz: filtros de tres columnas alineados y edición sin botones contradictorios',()=>{
 const css=read('publico/css/base/estructura_panel.css'),js=read('publico/js/textos/gestion_textos.js');
 assert.match(css,/\.reference-filter-grid\.three>\.reference-field\{display:grid;grid-template-rows:18px 42px auto/);
 assert.match(js,/querySelector\('\.gestion-actions'\)/);
});

test('Tareas: archivar oculta de lista y prioridades; restaurar recupera la vista',()=>{
 const v=read('vistas/tareas/tareas.php'),c=read('controladores/tareas/ControladorTarea.php'),p=read('vistas/panel_principal.php');
 assert.match(v,/vista=archivadas/);assert.match(v,/Desarchivar tarea/);assert.match(v,/Archivar \/ ocultar/);
 assert.match(v,/\$marcadasArchivo\['tarea:'\.\$t\['id_tarea'\]\]/);
 assert.match(c,/existeVisible/);assert.match(c,/->cambiar\('tarea',\$id,\$accion==='archivar'\)/);
 assert.match(p,/\$ocultasDashboard/);
});
