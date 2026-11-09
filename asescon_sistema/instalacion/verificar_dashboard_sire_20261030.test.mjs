import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const base = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const leer = ruta => readFileSync(resolve(base, ruta), 'utf8');

test('vista SIRE: un selector único buscable, botón claro e historial paginado', () => {
  const vista = leer('vistas/sunat/panel_sire.php');
  for (const id of ['sireCliente', 'sireMes', 'sireAnio',
    'historialSire', 'toggleHistorialPeriodo', 'historial_pagina']) {
    assert.ok(vista.includes(id), `Falta ${id}`);
  }
  assert.ok(!vista.includes('sireBuscarEmpresa'),'No debe existir la búsqueda duplicada');
  assert.match(leer('publico/js/clientes/selector_clientes.js'),/select\.value = opcion\.value/);
  assert.match(vista, /Ver RVIE \+ RCE/);
  assert.match(vista, /Todas las empresas \(por separado\)/);
  assert.match(vista, /componentes\/dashboard_sire_global\.php/);
  assert.match(vista, /componentes\/dashboard_sire_avanzado\.php/);
});

test('no aparecen en el panel duplicados de Excel, comprobantes, IGV ni cobertura', () => {
  const vista = leer('vistas/sunat/panel_sire.php');
  const comparacion = leer('vistas/sunat/componentes/dashboard_sire_avanzado.php');
  for (const antiguo of ['Excel resumen', 'Ver comprobantes de ventas y compras aquí',
    'Ver estado de ventas y compras por empresa', 'Guardar revisión IGV',
    'Crédito fiscal validado', 'Último trabajo', 'Ver resumen']) {
    assert.ok(!vista.includes(antiguo) && !comparacion.includes(antiguo), `Sigue presente: ${antiguo}`);
  }
  assert.match(comparacion, /Comparación anual por meses/);
});

test('ticket como herramienta técnica plegada, explicada y sin presentación oficial', () => {
  const vista = leer('vistas/sunat/panel_sire.php');
  assert.match(vista, /<details class="reference-panel sire-herramientas"/);
  assert.match(vista, /ticket SUNAT/);
  assert.match(vista, /no presenta declaraciones/);
  for (const id of ['sireTicket', 'sireLibro', 'btnPeriodos', 'btnSolicitar', 'btnEstado', 'btnDescargar']) {
    assert.ok(vista.includes(id), `Se perdió el control ${id}`);
  }
});

test('cada empresa aparece con RUC propio y la lista global permite búsqueda/paginación', () => {
  const vista = leer('vistas/sunat/componentes/dashboard_sire_global.php');
  const js = leer('publico/js/sunat/panel_sire.js');
  assert.match(vista, /foreach\(\$clientes as \$cliente\)/);
  assert.match(vista, /data-sire-global-empresa/);
  assert.match(vista, /RUC/);
  assert.match(vista, /exclusivamente al RUC/);
  assert.match(js, /porPagina=25/);
  assert.match(js, /normalizar\(f\.dataset\.busqueda\)/);
  assert.match(js, /const selector=\$\('sireCliente'\)/);
  assert.match(vista, /sireGlobalAnterior/);
  assert.match(vista, /sireGlobalSiguiente/);
});

test('cero comprobantes importados se distingue de no importado', () => {
  const controlador = leer('controladores/sunat/ControladorDashboardSire.php');
  const repo = leer('modelos/sunat/RepositorioLecturaSire.php');
  const comparacion = leer('vistas/sunat/componentes/dashboard_sire_avanzado.php');
  assert.match(controlador, /'SIN_IMPORTAR'/);
  assert.match(controlador, /\$cantidad===0/);
  assert.match(controlador, /'CERO'/);
  assert.match(controlador, /'ANUAL'/);
  assert.match(repo, /COUNT\(DISTINCT periodo\) meses_importados/);
  assert.match(repo, /v\.estado='PROCESADO'/);
  assert.match(comparacion, /Sin importación local|sin importar/);
  assert.match(comparacion, /S\/ 0\.00/);
});

test('historial completo: filtros de permisos, SQL paginado y alternador', () => {
  const controlador = leer('controladores/sunat/ControladorDashboardSire.php');
  const repo = leer('modelos/sunat/RepositorioLecturaSire.php');
  assert.match(controlador, /asesconExigirPermiso/);
  assert.match(controlador, /asesconFiltroClientesSql/);
  assert.match(controlador, /asesconPuedeVerCliente/);
  assert.match(controlador, /historial_modo/);
  assert.match(controlador, /contarHistorialDashboard/);
  assert.match(controlador, /historialDashboard/);
  assert.match(repo, /LIMIT \{\$limite\} OFFSET \{\$offset\}/);
  assert.match(repo, /c\.estado='ACTIVO' AND \(\{\$filtroClientes\}\)/);
  assert.match(repo, /AND s\.id_cliente/);
});

test('comparación anual identifica empresa/RUC, meses y última actualización', () => {
  const comparacion = leer('vistas/sunat/componentes/dashboard_sire_avanzado.php');
  assert.match(comparacion, /clienteSel\['razon_social'\]/);
  assert.match(comparacion, /clienteSel\['ruc'\]/);
  assert.match(comparacion, /\$comparacionMensual\[\$codigo\]/);
  assert.match(comparacion, /Última actualización registrada/);
  assert.match(comparacion, /Dos libros importados/);
  assert.match(comparacion, /Importación parcial/);
  assert.doesNotMatch(comparacion, /Ambos guardados/);
});

test('el dashboard no carga comprobantes completos, escribe MySQL ni cambia cron', () => {
  const controlador = leer('controladores/sunat/ControladorDashboardSire.php');
  assert.match(controlador, /obtenerResumenGlobal\('RVIE'/);
  assert.match(controlador, /obtenerResumenGlobal\('RCE'/);
  assert.doesNotMatch(controlador, /obtenerDetallesGlobales|obtenerDetallesAnuales|agruparDetallesGlobales/);
  assert.doesNotMatch(controlador, /programar\(true\)|establecerPausa\(|shell_exec|INSERT INTO|UPDATE /);
});

test('las revisiones fiscales que siguen en API exigen login, permiso, RUC y CSRF', () => {
  const api = leer('api/sunat/validacion_igv_sire.php');
  const repo = leer('modelos/sunat/RepositorioValidacionIgvSire.php');
  assert.match(api, /asesconExigirLogin\(\)/);
  assert.match(api, /asesconValidarCsrf/);
  assert.match(api, /asesconTienePermiso\(\$conexion,'SIRE','editar'\)/);
  assert.match(api, /asesconPuedeVerCliente/);
  assert.match(repo, /\$this->conexion->prepare/);
});

test('la barra del dashboard principal mantiene ancho, delgadez y fecha solicitados', () => {
  const estilos = leer('publico/css/modulos/sincronizacion_dashboard.css');
  assert.match(estilos, /max-width:470px/);
  assert.match(estilos, /justify-content:flex-start/);
  assert.match(estilos, />\.date\{flex:0 0 auto;order:3;margin-left:auto;white-space:nowrap\}/);
  assert.match(estilos, /height:4px/);
});
