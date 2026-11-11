import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const raiz = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const leer = ruta => readFileSync(resolve(raiz, ruta), 'utf8');

test('La creación de usuarios presenta las pestañas del diseño solicitado', () => {
  const vista = leer('vistas/equipo/equipo.php');
  for (const texto of ['Datos de usuario', 'Permisos', 'Datos personales', 'Config. documentos',
    'Ruta de inicio', 'Caja', 'nuevoUsuarioEmpresas', 'password_confirmacion']) {
    assert.ok(vista.includes(texto), `Falta sección: ${texto}`);
  }
  assert.match(vista, /name="clientes_asignados\[\]"/);
  assert.match(vista, /name="menu\[\]"/);
  assert.match(vista, /name="correo_secundario"/);
});

test('El controlador valida la contraseña y guarda permisos, empresas y contactos al crear', () => {
  const controlador = leer('controladores/equipo/ControladorEquipo.php');
  assert.match(controlador, /hash_equals\(\$password, \(string\)\$post\['password_confirmacion'\]\)/);
  assert.match(controlador, /guardarPermisosMenuSeleccionados\(\$idUsuario, \$idRol, \$seleccion/);
  assert.match(controlador, /guardarClientesRapidos\(\$idUsuario, \$clientesSeleccionados/);
  assert.match(controlador, /UPDATE usuarios SET correo=\?,correo_secundario=\?,telefono=\?/);
});

test('La interfaz cambia pestañas y avisa cuando las contraseñas no coinciden', () => {
  const script = leer('publico/js/equipo/gestion_usuarios.js');
  assert.match(script, /function abrirTabNuevo/);
  assert.match(script, /password_confirmacion/);
  assert.match(script, /Las contraseñas no coinciden/);
});
