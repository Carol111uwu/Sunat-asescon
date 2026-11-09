import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {dirname,resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

const raiz=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const leer=ruta=>readFileSync(resolve(raiz,ruta),'utf8');

test('El período identifica mes actual/seleccionado y año actual/seleccionado usando la fecha de Perú',()=>{
  const controlador=leer('controladores/sunat/ControladorVistaLibroSire.php');
  assert.match(controlador,/\$hoyPeru = PeriodosSire::ahoraPeru\(\)/);
  assert.match(controlador,/\$periodo === \$hoyPeru->format\('Ym'\)/);
  assert.match(controlador,/\$anio === \$hoyPeru->format\('Y'\)/);
  for(const etiqueta of ['Mes actual','Mes seleccionado','Año actual','Año seleccionado']){
    assert.ok(controlador.includes(etiqueta),`Falta ${etiqueta}`);
  }
  assert.match(controlador,/'periodoContexto' => \$periodoContexto/);
});

for(const modulo of ['rce','rvie']){
  test(`${modulo.toUpperCase()}: una explicación y un solo estado del interruptor`,()=>{
    const html=leer(`vistas/sunat/${modulo}.php`);
    assert.match(html,/Propuesta SUNAT: 0 comprobantes' : 'Resultados de la consulta'/);
    assert.match(html,/Período SIRE: <\?= \$h\(\$periodo\) \?>/);
    assert.ok((html.match(/\$h\(\$periodoContexto\)/g)||[]).length>=2);
    assert.equal((html.match(/Filtro desactivado/g)||[]).length,1);
    assert.equal((html.match(/Filtro activado/g)||[]).length,1);
    assert.doesNotMatch(html,/Desactivado: muestra todos/);
    assert.doesNotMatch(html,/Activado: muestra únicamente/);
    assert.match(html,/role="status"/);
    assert.match(html,/no borra registros/);
  });
}
