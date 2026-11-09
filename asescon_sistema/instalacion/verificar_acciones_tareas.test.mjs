                                                                             
                                                                                                         
import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const leer = relativo => readFileSync(new URL(`../${relativo}`, import.meta.url), 'utf8');
const vista = leer('vistas/tareas/tareas.php');
const panel = leer('vistas/panel_principal.php');
const controlador = leer('controladores/tareas/ControladorTarea.php');
const modelo = leer('modelos/tareas/Tarea.php');
const repoPanel = leer('modelos/panel/RepositorioPanelPrincipal.php');
const estilos = leer('publico/css/modulos/tareas.css');
const textosJs = leer('publico/js/textos/gestion_textos.js');

test('Tareas muestra las acciones Editar y Eliminar en cada registro editable', () => {
  assert.match(vista, /class="task-action-cell"/);
  assert.match(vista, /class="task-action-group"/);
  assert.match(vista, /class="task-edit-details"/);
  assert.match(vista, /name="accion" value="editar"/);
  assert.match(vista, /name="accion" value="eliminar"/);
  assert.match(vista, /class="mini-btn task-delete-btn">.*Eliminar/u);
  assert.match(vista, /name="csrf_token"/);
  assert.match(vista, /name="id_tarea"/);
});

test('El Dashboard incluye ID real y botón de eliminar', () => {
  assert.match(repoPanel, /SELECT t\.id_tarea,t\.titulo/);
  assert.match(panel, /class="dashboard-task-actions"/);
  assert.match(panel, /name="accion" value="eliminar"/);
  assert.match(panel, /\(int\)\$t\['id_tarea'\]/);
  assert.match(panel, /asesconCsrf\('csrf_tareas'\)/);
});

test('Controlador protege edición y borrado con CSRF y permiso', () => {
  assert.match(controlador, /asesconValidarCsrf\(.*'csrf_tareas'/s);
  assert.match(controlador, /\$accion==='editar'/);
  assert.match(controlador, /\$accion==='eliminar'/);
  assert.match(controlador, /'TAREAS','editar'/);
  assert.match(controlador, /\$model->editar\(/);
  assert.match(controlador, /\$model->eliminar\(/);
});

test('Modelo valida alcance de usuario/cliente al editar y eliminar', () => {
  assert.match(modelo, /public function editar\(/);
  assert.match(modelo, /public function eliminar\(/);
  assert.match(modelo, /DELETE t FROM tareas t LEFT JOIN clientes/);
  assert.match(modelo, /WHERE t\.id_tarea=\? AND \{\$filtro\}/);
  assert.match(modelo, /t\.id_usuario=\?/);
  assert.match(modelo, /\$afectadas!==1/);
});

test('Interfaz no esconde botones y muestra confirmación y resultado', () => {
  assert.match(estilos, /\.task-action-group\{display:flex/);
  assert.match(estilos, /\.task-delete-btn\{/);
  assert.match(vista, /confirm\('¿Eliminar definitivamente esta tarea\?/);
  assert.match(vista, /Tarea eliminada definitivamente/);
  assert.match(vista, /Tarea corregida correctamente/);
});

test('Borrar solo la descripción nunca elimina ni esconde una tarea', () => {
  assert.match(textosJs, /Borrar descripción/);
  assert.match(textosJs, /if\(tipo==='tarea'\)\{/);
  assert.match(textosJs, /la tarea sigue visible/);
  assert.match(textosJs, /el botón rojo «Eliminar»/);
});
