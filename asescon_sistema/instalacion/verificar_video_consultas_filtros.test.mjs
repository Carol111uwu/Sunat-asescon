import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {dirname,resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

const raiz=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const leer=r=>readFileSync(resolve(raiz,r),'utf8');

test('El interruptor de fechas funciona tanto en mes como en vista anual, sin borrado',()=>{
  const presentacion=leer('servicios/sunat/PresentacionDatosSire.php');
  const vista=leer('controladores/sunat/ControladorVistaLibroSire.php');
  const excel=leer('controladores/sunat/ControladorExportacionSire.php');
  const repo=leer('modelos/sunat/RepositorioLecturaSire.php');
  assert.match(presentacion,/function limitesEmision\(string \$periodo\)/);
  assert.match(presentacion,/\$mes === 0\) return \[sprintf\('%04d-01-01'/);
  assert.match(presentacion,/\$fecha !== '' && \$fecha >= \$inicio && \$fecha < \$fin/);
  assert.doesNotMatch(vista,/if \(\$esAnual\)\s*\{\s*\$excluirAnteriores\s*=\s*false/);
  assert.doesNotMatch(excel,/if \(\$esAnual\) \$excluir = false/);
  assert.match(repo,/if \(\$excluirAnteriores\) \{/);
  assert.match(repo,/d\.fecha_emision >=/);
  assert.match(repo,/d\.fecha_emision < /);
  assert.match(vista,/comprobantesExcluidos/);
  for(const modulo of ['rce','rvie']){
    const html=leer(`vistas/sunat/${modulo}.php`);
    assert.match(html,/no borra registros/);
    assert.match(html,/Filtro activado · /);
    assert.match(html,/Excel original sigue íntegro/);
    assert.match(html,/name="excluir_anteriores"/);
  }
});

test('Recuperación local rellena campos vacíos en lugar de reimportar y sustituir detalles',()=>{
  const servicio=leer('servicios/sunat/ServicioOperacionesSire.php');
  const mapa=leer('servicios/sunat/MapeadorSunat.php');
  const bloque=servicio.split('public function reprocesarLocal')[1];
  const reparacion=mapa.split('public function recuperarCamposFaltantes')[1].split('private static function claveDocumento')[0];
  assert.ok(bloque);
  assert.match(bloque,/obtenerCache/);
  assert.match(bloque,/recuperarCamposFaltantes/);
  assert.doesNotMatch(bloque,/importarArchivos\(/);
  assert.match(reparacion,/WHERE id_cliente=\? AND periodo=\? AND estado='PROCESADO'/);
  assert.match(reparacion,/UPDATE \{\$detalle\} SET fecha_emision=COALESCE\(fecha_emision,\?\)/);
  assert.match(reparacion,/\$car === '' \|\| \$carCandidata === '' \|\| \$car === \$carCandidata/);
  assert.match(reparacion,/\$this->conexion->begin_transaction\(\)/);
  assert.match(reparacion,/\$this->conexion->rollback\(\)/);
  assert.doesNotMatch(reparacion,/DELETE FROM|TRUNCATE|reemplazarRceDetalle|reemplazarRvieDetalle/);
});

test('Los tres mensajes del video están atendidos en Compras y Ventas',()=>{
  for(const modulo of ['rce','rvie']){
    const html=leer(`vistas/sunat/${modulo}.php`);
    const js=leer(`publico/js/sunat/${modulo}_centro.js`);
    const css=leer(`publico/css/modulos/${modulo}_centro.css`);
    assert.match(html,/consulta anual solicita cada mes a SUNAT en orden/);
    assert.match(html,/Mostrar solo comprobantes emitidos en el período seleccionado/);
    assert.doesNotMatch(html,new RegExp(`id="${modulo}RecuperarDatos"`));
    assert.match(js,/mesesElegidos\.map\(mesConsulta/);
    assert.doesNotMatch(js,/post\('reprocesar'/);
    assert.match(js,/sincronizarLibro/);
    assert.match(css,/max-width:1200px/);
    assert.match(css,/reference-switch input:focus-visible/);
  }
});
