import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {dirname, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';
import vm from 'node:vm';

const raiz = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const leer = ruta => readFileSync(resolve(raiz, ruta), 'utf8');

test('ventas y compras obligan a consultar SIRE sin modo de datos guardados', () => {
  for (const [libro, prefijo] of [['RVIE','rvie'], ['RCE','rce']]) {
    const vista = leer(`vistas/sunat/${prefijo}.php`);
    const js = leer(`publico/js/sunat/${prefijo}_centro.js`);
    const css = leer(`publico/css/modulos/${prefijo}_centro.css`);
    assert.doesNotMatch(vista, new RegExp(`id="${prefijo}VerGuardados"`), libro);
    assert.match(vista, new RegExp(`id="${prefijo}VerConsultar"`), libro);
    assert.doesNotMatch(vista, new RegExp(`id="${prefijo}ForzarSunat"`), libro);
    assert.doesNotMatch(vista, new RegExp(`id="${prefijo}ConsultarSunat"`), libro);
    assert.match(js, /addEventListener\('submit'/);
    assert.doesNotMatch(js, /mostrarGuardado\(\)|forzarSunat\?\.checked/);
    assert.match(js, /const pendientes = tareas\.filter\(tarea => tarea\.mesConsulta <= mesActual\)/);
    assert.match(js, /resultado\.sin_comprobantes !== true/);
    assert.match(js, /periodoElegido > mesActual/);
    assert.match(js, /const fechaLima = \(\) =>/);
    assert.match(css, new RegExp(`\\.${prefijo}-proceso \\[hidden\\]`));
    assert.match(css, /display: none !important/);
    assert.match(vista, /@filemtime\(__DIR__ .*?centro\.js/);
  }
});

test('un cero exige cabecera compatible, ticket terminado, identidad e integridad; el historial queda protegido', () => {
  const mapper = leer('servicios/sunat/MapeadorSunat.php');
  const servicio = leer('servicios/sunat/ServicioOperacionesSire.php');
  const lectura = leer('modelos/sunat/RepositorioLecturaSire.php');
  assert.match(mapper, /\$propuestaSinComprobantes = \$detalles === \[\] && \$estructuraReconocida && \$omitidas === 0/);
  assert.match(mapper, /validarEvidenciaCero\(\$evidencia, \$rucEsperado, \$periodo, \$codLibro, \$archivos\)/);
  for (const condicion of ["'TICKET_SIRE_TERMINADO'", "['ruc']", "['periodo']", "['libro']", "['ticket']", "['hash_zip']", "['hash_archivos']", "hash_file('sha256'"]) {
    assert.ok(mapper.includes(condicion), `Falta comprobación: ${condicion}`);
  }
  assert.match(mapper, /FOR UPDATE/);
  assert.match(mapper, /\(int\)\(\$filaAnterior\['cantidad_registros'\] \?\? 0\) > 0/);
  assert.match(mapper, /\$this->conexion->rollback\(\)/);
  assert.match(mapper, /'sin_comprobantes' => \$propuestaSinComprobantes/);
  assert.match(servicio, /'tipo' => 'TICKET_SIRE_TERMINADO'/);
  assert.match(servicio, /if \(!empty\(\$estado\['con_error'\]\)\)/);
  assert.match(servicio, /'sin_comprobantes' => \(bool\)/);
  assert.match(lectura, /v\.estado='PROCESADO'/);
});

test('el cron no repite sin fin discrepancias de cero frente al historial', () => {
  const cron = leer('procesos/sincronizacion/procesar.php');
  const resumen = leer('servicios/sincronizacion/ResumenSincronizacion.php');
  assert.match(cron, /SIRE_CERO_CON_HISTORIAL/);
  assert.match(cron, /SIRE_CERO_NO_CONFIRMADO/);
  assert.match(cron, /\$terminal\?'ERROR':'PENDIENTE'/);
  assert.match(resumen, /SIRE_CERO_CON_HISTORIAL/);
  assert.match(resumen, /SIRE_CERO_NO_CONFIRMADO/);
});

test('HTTP 504 reintenta estado y descarga usando el mismo ticket, sin pedir otra propuesta', async () => {
  const llamadas = [];
  const respuestas = {
    ticket_reciente: [{data: {num_ticket:''}}, {data: {num_ticket:'TICKET-ORIGINAL',procesado:false}}],
    solicitar: [{data: {num_ticket:'TICKET-ORIGINAL'}}],
    estado: [{http:504}, {data: {terminado:true, estado:'TERMINADO'}}],
    descargar: [{http:504}, {data: {descargado:true, procesado:true, cantidad_registros:0, sin_comprobantes:true}}],
    inconsistencias: [{data: {sin_inconsistencias:true}}],
  };
  const ventana = {};
  const fetch = async (_url, opciones) => {
    const body = JSON.parse(opciones.body);
    llamadas.push(body);
    const r = respuestas[body.accion]?.shift();
    if (!r) throw new Error('Llamada inesperada: ' + body.accion);
    if (r.http) return {ok:false, status:r.http, text:async () => '<html>Gateway Timeout</html>'};
    return {ok:true, status:200, text:async () => JSON.stringify({ok:true, ...r})};
  };
  const contexto = vm.createContext({
    window:ventana,
    document:{querySelector:() => ({content:'csrf-prueba'})},
    fetch,
    AbortController,
    setTimeout: fn => {queueMicrotask(fn); return 1;},
    clearTimeout: () => {},
    console,
  });
  vm.runInContext(leer('publico/js/sunat/sincronizacion_sire.js'), contexto);
  const estados = [];
  const resultado = await ventana.AsesconSireSync.sincronizarLibro(7,'RVIE','202611',s => estados.push(s));
  assert.equal(resultado.sin_comprobantes, true);
  assert.deepEqual(llamadas.map(x => x.accion), ['ticket_reciente','solicitar','estado','estado','descargar','ticket_reciente','descargar','inconsistencias']);
  assert.equal(llamadas.filter(x => x.accion === 'solicitar').length, 1);
  assert.ok(llamadas.filter(x => ['estado','descargar'].includes(x.accion)).every(x => x.ticket === 'TICKET-ORIGINAL'));
  assert.ok(estados.some(x => x.includes('MISMO ticket')));
  assert.ok(estados.some(x => x.includes('Reintentando el ticket existente')));
});
