import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const base = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const ver = ruta => readFileSync(resolve(base, ruta), 'utf8');

test('RVIE conserva filtros y la consulta en el mismo módulo, sin listado histórico permanente', () => {
  const vista = ver('vistas/sunat/rvie.php');
  for (const id of ['rvieCliente', 'rvieMes', 'rvieAnio',
    'rvieVerConsultar', 'rvieBarraProgreso', 'rvieListaProcesos',
    'toggleExcluirAnteriores']) assert.ok(vista.includes(id), `Falta ${id}`);
  assert.equal((vista.match(/⇩ Excel resumen/g) || []).length, 1);
  assert.ok(!vista.includes('rvieExcelTop'));
  assert.ok(!vista.includes('id="rvieVerGuardados"'));
  assert.ok(!vista.includes('id="rvieForzarSunat"'));
  assert.ok(!vista.includes('resumen_global_sire.php'));
  assert.ok(!vista.includes('Sincronizar todas en segundo plano'));
  assert.ok(!vista.includes('btnSyncRVIE'));
  assert.ok(!vista.includes('Dashboard SIRE'));
});

test('La consulta remota se hace en cada clic y admite un año de meses individuales', () => {
  const js = ver('publico/js/sunat/rvie_centro.js');
  assert.doesNotMatch(js, /post\('inventario'/);
  assert.match(js, /const pendientes = tareas\.filter\(tarea => tarea\.mesConsulta <= mesActual\)/);
  assert.doesNotMatch(js, /forzarSunat|!vigente\(tarea\)|reanudacion/);
  assert.match(js, /rvieConsultaFormulario'\)\?\.addEventListener\('submit'/);
  assert.doesNotMatch(js, /mostrarGuardado\(\)/);
  assert.match(js, /AsesconSireSync\.sincronizarLibro/);
  assert.match(js, /progreso\(importados \+ errores \+ sinConfirmar/);
  assert.match(js, /resultado\.sin_comprobantes !== true/);
  assert.doesNotMatch(js, /detenerSolicitado|Ver selección|Ver resultados actualizados/);
  assert.match(js, /Reintentar solo mes/);
  assert.match(js, /Pausar después del mes actual/);
  assert.match(js, /registroPendientes\?\.marcarTodos\(pendientes\)/);
  assert.doesNotMatch(js, /bloquearFiltros\(true\)/);
});

test('RVIE permite ocultar y volver a mostrar el estado sin interrumpir la consulta', () => {
  const vista = ver('vistas/sunat/rvie.php');
  const js = ver('publico/js/sunat/rvie_centro.js');
  assert.match(vista, /id="rvieVerEstado" aria-controls="rvieProceso" aria-expanded="false"/);
  assert.match(js, /botonEstado\.textContent = 'Ocultar estado de consultas'/);
  assert.match(js, /botonEstado\.textContent = 'Ver estado de consultas'/);
  assert.match(js, /estadoOcultoPorUsuario = true/);
  assert.match(js, /if \(estadoOcultoPorUsuario\) return/);
  assert.doesNotMatch(js, /'Ocultar datos guardados'/);
});

test('Los detalles globales se consultan por páginas y con permisos por empresa', () => {
  const repo = ver('modelos/sunat/RepositorioLecturaSire.php');
  const controlador = ver('controladores/sunat/ControladorVistaLibroSire.php');
  const api = ver('api/sunat/operaciones_sire.php');
  assert.ok(repo.includes('obtenerDetallesGlobales'));
  assert.ok(repo.includes('LIMIT {$limite} OFFSET {$offset}'));
  assert.ok(controlador.includes('contarDetallesGlobales'));
  assert.ok(api.includes("asesconFiltroClientesSql($conexion, 'c')"));
  assert.ok(api.includes("asesconExigirPermiso($conexion, $libro, 'ver')"));
});

test('Excel consolidado permite resumen sin consultar cliente por cliente', () => {
  const excel = ver('controladores/sunat/ControladorExportacionSire.php');
  assert.match(excel, /\$global \? \$repo->agruparDetallesGlobales/);
  assert.match(excel, /get\/|obtenerDetallesGlobales/);
  assert.ok(excel.includes('Empresa ASESCON'));
});

test('Los fallos PDF conservan bandeja y nunca aceptan acuses automáticamente', () => {
  const pdf = ver('python/sunat/obtener_pdf_sol.py');
  const lector = ver('python/sunat/consultar_buzon_sol.py');
  assert.ok(pdf.includes('SOL_PDF_NO_GENERADO_POR_PORTAL'));
  assert.ok(pdf.includes('SOL_PDF_REQUIERE_REVISION_MANUAL'));
  assert.ok(lector.includes('advertencia_adjuntos'));
});
