import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const ver=ruta=>readFileSync(new URL('../'+ruta, import.meta.url),'utf8');

test('acciones SOL y SUNAFIL alineadas a la derecha sin dividir la fila',()=>{
  const css=ver('publico/css/modulos/buzones_ordenados.css');
  assert.match(css,/\.buzon-renovado \.buzon-barra-acciones \{[\s\S]*?flex-flow: row nowrap;/);
  assert.match(css,/justify-content: flex-end;/);
  assert.match(css,/white-space: nowrap;/);
  assert.match(css,/\.buzon-renovado \.buzon-enviar\[open\] \.buzon-canales/);
  const vistaSol=ver('vistas/buzones/buzon_sol_sunat.php');
  const vistaSunafil=ver('vistas/buzones/buzon_sunafil.php');
  assert.match(vistaSol,/class="buzon-barra-acciones"/);
  assert.match(vistaSunafil,/class="buzon-barra-acciones"/);
});

test('usuarios: contactos opcionales y permisos explicados sin cambiar validaciones de RUC',()=>{
  const vista=ver('vistas/equipo/equipo.php');
  assert.match(vista,/Correo electrónico \(opcional\)/);
  assert.match(vista,/Otro correo de contacto \(opcional\)/);
  assert.match(vista,/Teléfono de contacto \(opcional\)/);
  assert.match(vista,/Los permisos de edición dependen de su rol/);
  const ctl=ver('controladores/equipo/ControladorEquipo.php');
  assert.match(ctl,/\$correo !== '' && !filter_var\(\$correo, FILTER_VALIDATE_EMAIL\)/);
  assert.match(ctl,/\(\$post\['tipo_identificacion'\] \?\? ''\) === 'RUC'/);
});

test('PDF de SOL no falla si hay una consulta activa; se difiere',()=>{
  const cola=ver('servicios/sunat/ServicioColaBuzonSol.php');
  assert.match(cola,/encolarDocumentoDiferido/);
  assert.match(cola,/promoverDocumentosDiferidos/);
  assert.match(cola,/obtenerEstadoDocumento/);
  assert.match(ver('publico/js/buzones/ventanas/pdf.js'),/EN_COLA/);
});
