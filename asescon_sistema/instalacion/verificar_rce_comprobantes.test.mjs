import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const raiz = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const leer = ruta => readFileSync(resolve(raiz, ruta), 'utf8');

test('RCE muestra acciones para consultar, descargar y compartir el reporte', () => {
  const vista = leer('vistas/sunat/rce.php');
  for (const texto of ['data-rce-correo', 'data-rce-whatsapp', 'formato=xlsx',
    'vista=previa', 'rce_comprobante.js', 'acciones_comprobante_rce.php']) {
    assert.ok(vista.includes(texto), `Falta la integración: ${texto}`);
  }
  assert.ok(vista.includes('data-archivo-comprobante'), 'Debe conservarse el acceso a los archivos originales');
});

test('La API del reporte verifica sesión, cliente, permisos y CSRF antes de enviar correos', () => {
  const api = leer('api/sunat/comprobante_rce.php');
  for (const texto of ['asesconTienePermiso', 'asesconPuedeVerCliente', 'asesconValidarCsrf',
    'FILTER_VALIDATE_EMAIL', 'obtenerComprobanteRce', 'Content-Disposition']) {
    assert.ok(api.includes(texto), `Falta una validación: ${texto}`);
  }
  const modelo = leer('modelos/sunat/RepositorioLecturaSire.php');
  assert.match(modelo, /WHERE d\.id_rce_detalle = \? AND h\.id_cliente = \?/);
  assert.match(modelo, /AS id_detalle/);
});

test('El PDF se presenta como reporte informativo y no como comprobante fiscal original', () => {
  const documento = leer('servicios/sunat/DocumentoComprobanteRce.php');
  assert.match(documento, /Reporte informativo/);
  assert.match(documento, /consulta el comprobante original/);
  assert.match(documento, /function excel\(/);
  assert.match(documento, /function pdf\(/);
});

test('SIRE permanece como primer subgrupo del menú SUNAT', () => {
  const menu = leer('vistas/componentes/menu_lateral.php');
  assert.match(menu, /GRUPO_SIRE/);
  assert.match(menu, /RCE \(Compras\)/);
  assert.match(menu, /RVIE \(Ventas\)/);
  assert.match(menu, /Dashboard SIRE/);
  assert.match(menu, /Comprobantes SUNAT/);
});

test('RCE permite seleccionar algunos o todos los comprobantes de la página y exportarlos juntos', () => {
  const vista = leer('vistas/sunat/rce.php');
  const script = leer('publico/js/sunat/rce_seleccion.js');
  const api = leer('api/sunat/comprobantes_rce_lote.php');
  for (const selector of ['rceMarcarTodos', 'rceLimpiarSeleccion', 'rceLotePdf',
    'rceLoteExcel', 'rceLoteEnviar', 'rceEnviarOpciones', 'rce-seleccionar-fila',
    'rceFiltrosDetalle', 'rceFiltrosEstado']) {
    assert.ok(vista.includes(selector), `Falta: ${selector}`);
  }
  assert.match(script, /'pdf'/);
  assert.match(script, /'excel'/);
  assert.match(script, /'correo'/);
  assert.match(script, /'seleccion'/);
  assert.match(script, /function aplicarFiltros\(/);
  assert.match(api, /asesconPuedeVerCliente/);
  assert.match(api, /asesconValidarCsrf/);
  assert.match(api, /60 comprobantes/);
  assert.match(api, /new DocumentoComprobanteRce/);
  assert.match(api, /ZipArchive/);
  assert.match(api, /ExportadorExcelSire/);
});

test('RCE recupera el ticket 42209 devuelto por SUNAT y evita pedir otro innecesariamente', () => {
  const servicio = leer('servicios/sunat/ServicioOperacionesSire.php');
  const repositorio = leer('modelos/sunat/RepositorioOperacionesSire.php');
  const cliente = leer('publico/js/sunat/sincronizacion_sire.js');
  assert.match(servicio, /getCodigoSunat\(\) === '42209'/);
  assert.match(servicio, /RECUPERAR_TICKET_SIRE/);
  assert.match(servicio, /'reutilizado' => true/);
  assert.match(repositorio, /INTERVAL 48 HOUR/);
  assert.match(cliente, /SUNAT ya tiene una exportación SIRE en curso/);
});
