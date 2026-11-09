import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const leer = ruta => readFileSync(path.join(root, ruta), 'utf8');

test('RVIE y RCE paginan clientes y conservan el Excel global por lotes', () => {
  const repo = leer('modelos/sunat/RepositorioLecturaSire.php');
  const controlador = leer('controladores/sunat/ControladorVistaLibroSire.php');
  for (const metodo of ['clientesConDetallesGlobales', 'detallesGlobalesDeCliente', 'obtenerDetallesGlobales']) {
    assert.match(repo, new RegExp('function ' + metodo + '\\('));
  }
  assert.match(repo, /SELECT DISTINCT c\.id_cliente,c\.razon_social,c\.ruc/);
  const detallePorCliente = repo.split('function detallesGlobalesDeCliente(')[1].split('function contarDetallesGlobales(')[0];
  assert.doesNotMatch(detallePorCliente, /LIMIT\s+\{/);
  assert.match(detallePorCliente, /AND c\.id_cliente=/);
  assert.match(controlador, /clientesPaginados\[\$pagina - 1\]/);
  assert.doesNotMatch(controlador, /ceil\(\$totalDetallesGlobales\s*\/\s*\$porPagina\)/);
  assert.match(leer('controladores/sunat/ControladorExportacionSire.php'), /obtenerDetallesGlobales\(/);
  for (const vista of ['rvie', 'rce']) {
    const php = leer(`vistas/sunat/${vista}.php`);
    assert.match(php, /max\(1, count\(\$clientesPaginados\)\)/);
    assert.match(php, /Siguiente empresa/);
    assert.match(php, /\$clientePagina\['ruc'\]/);
  }
});

test('Dashboard y Buzón SOL cargan el cliente completo en modo global', () => {
  const repo = leer('modelos/sunat/RepositorioLecturaSire.php');
  const dashboard = leer('controladores/sunat/ControladorDashboardSire.php');
  const buzon = leer('modelos/buzones/BuzonSol.php');
  const controladorBuzon = leer('controladores/buzones/ControladorBuzonSol.php');
  assert.match(repo, /function historialDashboardDeCliente\(/);
  assert.match(dashboard, /clientesConHistorialDashboard\(/);
  assert.match(dashboard, /historialDashboardDeCliente\(/);
  assert.match(buzon, /function clientesConNotificaciones\(/);
  assert.match(buzon, /\$clienteCompleto&&\$idCliente>0\?'':/);
  assert.match(controladorBuzon, /\$idCliente===0\?max\(1,count\(\$clientesPaginados\)\)/);
  assert.match(controladorBuzon, /\$idCliente===0\?0:\(\$pagina-1\)\*\$porPagina,\$idCliente===0/);
  const sunafil = leer('modelos/buzones/BuzonSunafil.php');
  const controladorSunafil = leer('controladores/buzones/ControladorBuzonSunafil.php');
  assert.match(sunafil, /function clientesConNotificaciones\(/);
  assert.match(sunafil, /function condicionListado\(/);
  assert.doesNotMatch(sunafil.split('function listar(')[1].split('function clientesConNotificaciones(')[0], /LIMIT\s+1000/);
  assert.match(controladorSunafil, /\$clienteListado>0\?\$m->listar\(/);
  assert.match(leer('vistas/buzones/buzon_sunafil.php'), /Siguiente empresa/);
  const comprobantes = leer('modelos/sunat/ComprobanteSunat.php');
  const controladorComprobantes = leer('controladores/sunat/ControladorComprobanteSunat.php');
  assert.match(comprobantes, /function clientesConComprobantes\(/);
  assert.match(comprobantes, /\$limiteLocal=\$idCliente>0\?'':/);
  assert.match(comprobantes, /\$limiteRce=\$idCliente>0\?'':/);
  assert.match(controladorComprobantes, /\$clienteListado>0\?\$modelo->listar\(/);
  assert.match(leer('vistas/sunat/comprobantes_sunat.php'), /Siguiente empresa/);
});

test('Detracciones: el servidor muestra un único cliente completo, sin límite por filas', () => {
  const vista = leer('vistas/sunat/detracciones.php');
  const controlador = leer('controladores/sunat/ControladorDetraccion.php');
  const repositorio = leer('modelos/sunat/RepositorioDetraccionesSpot.php');
  const adaptacion = leer('publico/js/interfaz/tablas_adaptables.js');
  assert.match(vista, /\$empresaActual=\$empresas\[\$pagina-1\]/);
  assert.match(vista, /\$ctrl->constancias\(\$idActual>0\?\[\$idActual\]:\[\],\$periodo\)/);
  assert.match(vista, /Cliente siguiente/);
  assert.match(vista, /Una página completa por cliente/);
  assert.match(controlador, /function clientes\(/);
  assert.doesNotMatch(repositorio, /LIMIT\s+\d+/);
  assert.doesNotMatch(adaptacion, /"detracciones\.php":/);
});
