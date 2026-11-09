import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const leer=ruta=>readFileSync(new URL('../'+ruta,import.meta.url),'utf8');

test('Detracciones tiene un selector nativo de empresas accesible sin JavaScript',()=>{
  const vista=leer('vistas/sunat/detracciones.php');
  assert.match(vista,/<select name="cliente" aria-label="Seleccionar empresa para detracciones">/);
  assert.match(vista,/<option value="0"/);
  assert.match(vista,/\$clientes as \$c.*?<option value="<\?=\(int\)\$c\['id_cliente'\]\?>"/);
  assert.match(vista,/name="id_cliente" data-sin-autocompletar required/);
  assert.doesNotMatch(vista,/spotSelectorClientes|spot-client-picker/);
});

test('Detracciones, Buzón SOL y SUNAFIL no tienen botones de salida a portales oficiales',()=>{
  for(const ruta of [
    'vistas/sunat/detracciones.php',
    'vistas/buzones/buzon_sol_sunat.php',
    'vistas/buzones/buzon_sunafil.php'
  ]){
    const vista=leer(ruta);
    assert.doesNotMatch(vista,/href="https:\/\/(?:e-menu\.sunat\.gob\.pe|casillaelectronica\.sunafil\.gob\.pe)/,ruta);
  }
  assert.match(leer('vistas/buzones/buzon_sol_sunat.php'),/Consultar todos en SOL/);
  assert.match(leer('vistas/buzones/buzon_sunafil.php'),/Consultar todos en SUNAFIL/);
  assert.match(leer('vistas/sunat/detracciones.php'),/aún no está conectada/);
});

test('El selector mantiene RUC, razón social y selección explícita',()=>{
  const vista=leer('vistas/sunat/detracciones.php');
  assert.match(vista,/\$h\(\$c\['ruc'\]\.' — '\.\$c\['razon_social'\]\)/);
  assert.match(vista,/\$idCliente===\(int\)\$c\['id_cliente'\]\?'selected'/);
  assert.doesNotMatch(leer('publico/js/sunat/detracciones_spot.js'),/data-spot-cliente-buscar/);
});
