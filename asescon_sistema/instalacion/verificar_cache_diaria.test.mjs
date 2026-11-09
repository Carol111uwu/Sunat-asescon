import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read = p => readFileSync(new URL('../'+p,import.meta.url), 'utf8');

test('limpieza diaria restringida a CLI, caché antigua y ventana de Perú',()=>{
  const php = read('procesos/almacenamiento/limpiar_dia_anterior.php');
  assert.match(php,/PHP_SAPI !== 'cli'/);
  assert.match(php,/America\/Lima/);
  assert.match(php,/\$minuto < 40 \|\| \$minuto > 110/);
  assert.match(php,/\$ejecutar = in_array\('--execute'/);
  assert.match(php,/ultima_limpieza_diaria/);
  assert.match(php,/limpieza\.lock/);
  assert.match(php,/mantenimiento\.flag/);
  assert.match(php,/consultas_sire\.lock/);
  assert.match(php,/LOCK_EX \| LOCK_NB/);
  assert.match(php,/is_link\(\$ruta\)/);
  assert.match(php,/filemtime\(\$ruta\)/);
  for(const tabla of ['rvie','rce','sire','sire_descargas','sunat_historial_consultas','sunat_declaraciones']) {
    assert.match(php,new RegExp(`\\['${tabla}'`));
  }
  for(const protegido of ['sincronizacion','casos','credenciales','respaldos']){
    assert.match(php,new RegExp(`'${protegido}'`));
  }
  assert.doesNotMatch(php,/\['(?:usuarios|clientes|credenciales_sunat)'/);
  assert.match(php,/\['buzon_sol_sunat'/);
  assert.match(php,/\['buzon_sunafil'/);
});

test('cron de limpieza reutiliza PHP CLI con las mismas rutas privadas y sin duplicar trabajo',()=>{
  const sh=read('procesos/almacenamiento/cron_limpieza.sh');
  assert.match(sh,/source "\$PROYECTO\/procesos\/entorno_comun\.sh"/);
  assert.match(sh,/"\$PHP_BIN"/);
  assert.match(sh,/--cron/);
  assert.match(sh,/cron_limpieza\.log/);
});

test('ventas/compras consultan SUNAT incluso con caché reciente y dashboard conserva vigencia diaria',()=>{
  for(const libro of ['rvie','rce']){
    const js=read(`publico/js/sunat/${libro}_centro.js`);
    assert.match(js,/Cada nuevo clic consulta todos los períodos elegidos/);
    assert.doesNotMatch(js,/!vigente\(tarea\)|mostrarGuardado\(/);
    assert.match(js,/AsesconSireSync\.sincronizarLibro/);
  }
  assert.match(read('controladores/sunat/ControladorDashboardSire.php'),/usarSoloCacheDeHoy\(\)/);
  const api=read('api/sunat/operaciones_sire.php');
  assert.match(api,/LOCK_SH\|LOCK_NB/);
  assert.match(api,/mantenimiento\.flag/);
  assert.match(api,/ASESCON está limpiando la caché/);
});

test('Buzón SOL incorpora función que invocaba el precargador',()=>{
  assert.match(read('python/sunat/acceso_sol.py'),/def abrir_bandeja_sol\(page\)->bool:/);
  assert.match(read('python/sunat/precargar_adjuntos_sol.py'),/abrir_bandeja_sol/);
});
