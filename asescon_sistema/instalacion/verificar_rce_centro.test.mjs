import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const raiz = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const leer = ruta => readFileSync(resolve(raiz, ruta), 'utf8');

test('RCE conserva la misma estructura de consulta central de RVIE, sin enlaces al Dashboard', () => {
  const vista = leer('vistas/sunat/rce.php');
  for (const id of ['rceCliente', 'rceMes', 'rceAnio',
    'rceVerConsultar', 'rceVerEstado', 'rceBarraProgreso', 'rceListaProcesos',
    'rceResultados', 'toggleExcluirAnteriores']) {
    assert.ok(vista.includes(id), `Falta ${id}`);
  }
  assert.equal((vista.match(/⇩ Excel resumen/g) || []).length, 1);
  assert.ok(!vista.includes('id="rceVerGuardados"'));
  assert.ok(!vista.includes('id="rceForzarSunat"'));
  assert.ok(!vista.includes('resumen_global_sire.php'));
  assert.ok(!vista.includes('btnSyncRCE'));
  assert.ok(!vista.includes('Dashboard SIRE'));
});

test('RCE consulta períodos directamente sin recuperar un inventario anual al entrar', () => {
  const js = leer('publico/js/sunat/rce_centro.js');
  assert.doesNotMatch(js, /post\('inventario', \{libro:'RCE'\}/);
  assert.match(js, /const pendientes = tareas\.filter\(tarea => tarea\.mesConsulta <= mesActual\)/);
  assert.doesNotMatch(js, /forzarSunat|!vigente\(tarea\)|reanudacion/);
  assert.match(js, /sincronizarLibro\(Number\(tarea\.opcion\.value\), 'RCE', tarea\.mesConsulta/);
  assert.match(js, /progreso\(importados \+ errores \+ sinConfirmar/);
  assert.match(js, /resultado\.sin_comprobantes !== true/);
  assert.doesNotMatch(js, /detenerSolicitado|Ver selección|Ver resultados actualizados/);
  assert.match(js, /Reintentar solo mes/);
  assert.match(js, /Pausar después del mes actual/);
  assert.match(js, /registroPendientes\?\.marcarTodos\(pendientes\)/);
  assert.doesNotMatch(js, /bloquearFiltros\(true\)/);
  assert.doesNotMatch(js, /rceRecuperarDatos|post\('reprocesar'/);
});

test('RCE permite ocultar y volver a mostrar el estado sin interrumpir la consulta', () => {
  const vista = leer('vistas/sunat/rce.php');
  const js = leer('publico/js/sunat/rce_centro.js');
  assert.match(vista, /id="rceVerEstado" aria-controls="rceProceso" aria-expanded="false"/);
  assert.match(js, /botonEstado\.textContent = 'Ocultar estado de consultas'/);
  assert.match(js, /botonEstado\.textContent = 'Ver estado de consultas'/);
  assert.match(js, /estadoOcultoPorUsuario = true/);
  assert.match(js, /if \(estadoOcultoPorUsuario\) return/);
  assert.doesNotMatch(js, /'Ocultar datos guardados'/);
});

test('RCE presenta proveedores reales, PDF/archivos y consolidados paginados por empresa', () => {
  const vista = leer('vistas/sunat/rce.php');
  assert.match(vista, /componentes\/botones_comprobante_sire\.php/);
  const contenidoVista = vista + '\n' + leer('vistas/sunat/componentes/botones_comprobante_sire.php');
  const modelo = leer('modelos/sunat/RepositorioLecturaSire.php');
  const controlador = leer('controladores/sunat/ControladorVistaLibroSire.php');
  const exportacion = leer('controladores/sunat/ControladorExportacionSire.php');
  for (const campo of ['ruc_proveedor', 'razon_social_proveedor', 'Archivos / cuenta',
    'archivo_comprobante.php', 'totalDetallesGlobales', 'rce-paginacion']) {
    assert.ok(contenidoVista.includes(campo), `RCE no incluye ${campo}`);
  }
  assert.match(modelo, /d\.importe_total,d\.estado_registro,h\.id_cliente/);
  assert.ok(controlador.includes('contarDetallesGlobales'));
  assert.ok(!controlador.includes("if ($libro === 'RVIE') {"));
  assert.match(exportacion, /\$global \? \$repo->agruparDetallesGlobales/);
});
