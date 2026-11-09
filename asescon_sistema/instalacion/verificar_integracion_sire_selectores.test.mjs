import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {dirname, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

const base = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const leer = ruta => readFileSync(resolve(base, ruta), 'utf8');

test('el servicio SIRE incorpora validación 2021–actual y cero verificado en la misma implementación', () => {
  const s = leer('servicios/sunat/ServicioOperacionesSire.php');
  assert.match(s, /require_once __DIR__ \. '\/PeriodosSire\.php';/);
  assert.match(s, /PeriodosSire::permitido\(\$periodo, \$admitirAnual\)/);
  assert.match(s, /static fn\(\$fila\):bool => is_array\(\$fila\) && PeriodosSire::permitido/);
  assert.match(s, /'tipo' => 'TICKET_SIRE_TERMINADO'/);
  assert.match(s, /'hash_archivos' => \$guardado\['hash_archivos'\]/);
  assert.match(s, /'sin_comprobantes' => \(bool\)/);
  assert.match(s, /if \(!empty\(\$estado\['con_error'\]\)\)/);
});

test('los selectores globales conviven con los botones independientes de RVIE y RCE', () => {
  const barra = leer('vistas/componentes/barra_superior.php');
  assert.match(barra, /publico\/js\/clientes\/selector_clientes\.js/);
  assert.match(barra, /publico\/js\/interfaz\/periodos_2021_actual\.js/);
  const selector = leer('publico/js/clientes/selector_clientes.js');
  assert.match(selector, /select\.dispatchEvent\(new Event\('change'/);
  assert.match(selector, /select\.value = opcion\.value/);
  for(const prefijo of ['rvie','rce']){
    const vista=leer(`vistas/sunat/${prefijo}.php`);
    const js=leer(`publico/js/sunat/${prefijo}_centro.js`);
    assert.doesNotMatch(vista,new RegExp(`id="${prefijo}VerGuardados"`));
    assert.match(vista,new RegExp(`id="${prefijo}VerConsultar"`));
    assert.doesNotMatch(vista,new RegExp(`id="${prefijo}ForzarSunat"`));
    assert.match(js,/const fechaLima =/);
    assert.match(js,/resultado\.sin_comprobantes/);
  }
});

test('el importador preserva datos anteriores y la selección de años no altera detalles de Compras', () => {
  const mapper=leer('servicios/sunat/MapeadorSunat.php');
  const lectura=leer('modelos/sunat/RepositorioLecturaSire.php');
  const periodos=leer('servicios/sunat/PeriodosSire.php');
  assert.match(mapper,/FOR UPDATE/);
  assert.match(mapper,/Ya tiene comprobantes guardados/i);
  assert.match(mapper,/hash_file\('sha256'/);
  assert.match(lectura,/d\.importe_total,d\.estado_registro,h\.id_cliente/);
  assert.match(periodos,/ANIO_INICIAL = 2021/);
});
