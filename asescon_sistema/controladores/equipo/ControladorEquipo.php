<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/seguridad/Usuario.php';
require_once __DIR__ . '/../../servicios/seguridad/GestorContrasenas.php';

final class ControladorEquipo
{
    public function __construct(private mysqli $conexion)
    {
    }

    public function datosVista(): array
    {
        asesconExigirPermiso($this->conexion, 'EQUIPO', 'ver');
        $actor = asesconUsuarioActual($this->conexion);

        $model = new Usuario($this->conexion);
        $usuarios = $model->listarGestionables((int)$actor['id_usuario'], asesconEsPrincipal($this->conexion));
        $roles = asesconRolesCreables($this->conexion);

        $modulos = [];
        $res = $this->conexion->query('SELECT id_modulo,codigo,nombre,grupo,ruta,orden_menu FROM modulos WHERE estado=1 ORDER BY orden_menu,id_modulo');
        if ($res) {
            while ($m = $res->fetch_assoc()) {
                $modulos[] = $m;
            }
        }

        $clientes = [];
        $res = asesconClientesVisibles($this->conexion, false);
        if ($res) {
            while ($c = $res->fetch_assoc()) {
                $clientes[] = $c;
            }
        }

        $asignacionesPorUsuario = [];
        $permisosMenuPorUsuario = [];
        if ($usuarios !== []) {
            $ids = array_values(array_filter(array_map(static fn(array $u): int => (int)$u['id_usuario'], $usuarios), static fn(int $id): bool => $id > 0));
            if ($ids !== []) {
                $lista = implode(',', $ids);
                $r = $this->conexion->query('SELECT id_usuario,id_cliente,puede_ver,puede_gestionar FROM usuario_clientes WHERE id_usuario IN (' . $lista . ')');
                if ($r) {
                    while ($x = $r->fetch_assoc()) {
                        $asignacionesPorUsuario[(int)$x['id_usuario']][(int)$x['id_cliente']] = $x;
                    }
                }

                $sql = "SELECT u.id_usuario,m.codigo,COALESCE(um.puede_ver,rm.puede_ver,0) puede_ver
                        FROM usuarios u CROSS JOIN modulos m
                        LEFT JOIN usuario_modulos um ON um.id_usuario=u.id_usuario AND um.id_modulo=m.id_modulo
                        LEFT JOIN rol_modulos rm ON rm.id_rol=u.id_rol AND rm.id_modulo=m.id_modulo
                        WHERE u.id_usuario IN ({$lista}) AND m.estado=1";
                $r = $this->conexion->query($sql);
                if ($r) {
                    while ($x = $r->fetch_assoc()) {
                        $permisosMenuPorUsuario[(int)$x['id_usuario']][(string)$x['codigo']] = (int)$x['puede_ver'];
                    }
                }
            }
        }

        return compact('actor', 'usuarios', 'roles', 'modulos', 'clientes', 'asignacionesPorUsuario', 'permisosMenuPorUsuario');
    }

    public function guardar(array $post): void
    {
        if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_equipo')) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'csrf']);
        }

        $idUsuario = (int)($post['id_usuario'] ?? 0);
        $creando = $idUsuario <= 0;
        if ($creando) {
            if (!asesconPuedeCrearUsuarios($this->conexion)) {
                http_response_code(403);
                exit('Tu rol no puede crear usuarios.');
            }
        } elseif (!asesconPuedeAdministrarUsuario($this->conexion, $idUsuario)) {
            http_response_code(403);
            exit('No puedes modificar esta cuenta.');
        }

        $usuario = trim((string)($post['usuario'] ?? ''));
        $password = (string)($post['password'] ?? '');
        $idRol = (int)($post['id_rol'] ?? 0);
        if (!preg_match('/^[A-Za-z0-9._-]{4,50}$/', $usuario)) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'usuario_formato']);
        }
        if ($creando && (strlen($password) < 8 || strlen($password) > 72)) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'password']);
        }
        if ($creando && isset($post['password_confirmacion']) && !hash_equals($password, (string)$post['password_confirmacion'])) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'password_confirmacion']);
        }
        if (!$creando && $password !== '' && (strlen($password) < 8 || strlen($password) > 72)) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'password']);
        }
        $correo = trim((string)($post['correo'] ?? ''));
        $correoSecundario = trim((string)($post['correo_secundario'] ?? ''));
        $telefono = trim((string)($post['telefono'] ?? ''));
        if ($creando && (
            (strlen($correo) > 180 || ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)))
            || (strlen($correoSecundario) > 180 || ($correoSecundario !== '' && !filter_var($correoSecundario, FILTER_VALIDATE_EMAIL)))
            || strlen($telefono) > 30
        )) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'datos_contacto']);
        }
        if (!asesconPuedeAsignarRol($this->conexion, $idRol)) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'rol']);
        }

        $model = new Usuario($this->conexion);
        if ($model->existeUsuario($usuario, $idUsuario)) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'usuario_existe']);
        }

        $actor = asesconUsuarioActual($this->conexion);
        $actorGlobal = asesconEsPrincipal($this->conexion) || (int)$actor['acceso_todos_clientes'] === 1;
        $accesoTodos = ($actorGlobal && isset($post['acceso_todos_clientes'])) ? 1 : 0;

        $this->conexion->begin_transaction();
        try {
            if ($creando) {
                $idUsuario = $model->crear($usuario, password_hash($password, PASSWORD_DEFAULT), $idRol, $accesoTodos, (int)$actor['id_usuario']);
                $stmtContacto = $this->conexion->prepare('UPDATE usuarios SET correo=?,correo_secundario=?,telefono=? WHERE id_usuario=?');
                if (!$stmtContacto) {
                    throw new RuntimeException('No se pudieron guardar los datos de contacto.');
                }
                $correoGuardar = $correo !== '' ? $correo : null;
                $secundarioGuardar = $correoSecundario !== '' ? $correoSecundario : null;
                $telefonoGuardar = $telefono !== '' ? $telefono : null;
                $stmtContacto->bind_param('sssi', $correoGuardar, $secundarioGuardar, $telefonoGuardar, $idUsuario);
                $stmtContacto->execute();
                $stmtContacto->close();
            } else {
                $model->actualizar($idUsuario, $usuario, $idRol, $accesoTodos, $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null);
            }

            if ($creando) {
                $seleccion = is_array($post['menu'] ?? null) ? array_map('strval', $post['menu']) : [];
                $this->guardarPermisosMenuSeleccionados($idUsuario, $idRol, $seleccion, isset($post['ver_todo']));
                $clientesSeleccionados = is_array($post['clientes_asignados'] ?? null) ? $post['clientes_asignados'] : [];
                $this->guardarClientesRapidos($idUsuario, $clientesSeleccionados, $accesoTodos === 1, (int)$actor['id_usuario']);
            } elseif (isset($post['menu'])) {
                $sel = is_array($post['menu'] ?? null) ? array_map('strval', $post['menu']) : [];
                $this->guardarPermisosMenuSeleccionados($idUsuario, $idRol, $sel, isset($post['ver_todo']));
            }
            if (!$creando && (isset($post['clientes_asignados']) || $accesoTodos === 1)) {
                $sel = is_array($post['clientes_asignados'] ?? null) ? $post['clientes_asignados'] : [];
                $this->guardarClientesRapidos($idUsuario, $sel, $accesoTodos === 1, (int)$actor['id_usuario']);
            }

            $this->conexion->commit();
            asesconRegistrarAuditoria($this->conexion, $creando ? 'CREAR_USUARIO' : 'EDITAR_USUARIO', 'EQUIPO', null, 'usuarios', (string)$idUsuario, $usuario);
            asesconRedirigir('vistas/equipo/equipo.php', [$creando ? 'creado' : 'guardado' => 1]);
        } catch (DomainException $e) {
            $this->conexion->rollback();
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => $e->getMessage() === 'USUARIO_DUPLICADO' ? 'usuario_existe' : 'bd']);
        } catch (Throwable $e) {
            $this->conexion->rollback();
            error_log('ControladorEquipo guardar: ' . $e->getMessage());
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'bd']);
        }
    }

    private function guardarPermisosMenuSeleccionados(int $idUsuario, int $idRol, array $seleccion, bool $verTodo = false): void
    {
        $this->conexion->query('DELETE FROM usuario_modulos WHERE id_usuario=' . (int)$idUsuario);
        $mods = $this->conexion->query('SELECT id_modulo,codigo FROM modulos WHERE estado=1 ORDER BY orden_menu');
        $defaults = [];
        $s = $this->conexion->prepare('SELECT id_modulo,puede_ver,puede_crear,puede_editar,puede_eliminar,puede_ejecutar FROM rol_modulos WHERE id_rol=?');
        if ($s) {
            $s->bind_param('i', $idRol);
            $s->execute();
            $r = $s->get_result();
            while ($x = $r->fetch_assoc()) {
                $defaults[(int)$x['id_modulo']] = $x;
            }
$s->close();
        }
        $ins = $this->conexion->prepare('INSERT INTO usuario_modulos(id_usuario,id_modulo,puede_ver,puede_crear,puede_editar,puede_eliminar,puede_ejecutar) VALUES(?,?,?,?,?,?,?)');
        if (!$mods || !$ins) {
            throw new RuntimeException('No se pudieron guardar los permisos.');
        }
        while ($m = $mods->fetch_assoc()) {
            $mid = (int)$m['id_modulo'];
            $d = $defaults[$mid] ?? [];
            $permitido = (int)($d['puede_ver'] ?? 0) === 1;
            $visible = $permitido && ($verTodo || in_array((string)$m['codigo'], $seleccion, true));
            $v = $visible ? 1 : 0;
            $cr = $visible ? (int)($d['puede_crear'] ?? 0) : 0;
            $ed = $visible ? (int)($d['puede_editar'] ?? 0) : 0;
            $el = $visible ? (int)($d['puede_eliminar'] ?? 0) : 0;
            $ej = $visible ? (int)($d['puede_ejecutar'] ?? 0) : 0;
            $ins->bind_param('iiiiiii', $idUsuario, $mid, $v, $cr, $ed, $el, $ej);
            if (!$ins->execute()) {
                throw new RuntimeException('No se pudo guardar un módulo.');
            }
        }
        $ins->close();
    }

    private function guardarClientesRapidos(int $idUsuario, array $seleccion, bool $accesoTodos, int $actorId): void
    {
        $this->conexion->query('DELETE FROM usuario_clientes WHERE id_usuario=' . (int)$idUsuario);
        if ($accesoTodos) {
            return;
        }
        $idsActor = asesconIdsClientesPermitidos($this->conexion);
        $ins = $this->conexion->prepare('INSERT INTO usuario_clientes(id_usuario,id_cliente,puede_ver,puede_gestionar,asignado_por) VALUES(?,?,?,?,?)');
        if (!$ins) {
            throw new RuntimeException('No se pudieron guardar las empresas.');
        }
        foreach ($seleccion as $x) {
            $idCliente = (int)$x;
            if ($idCliente <= 0) {
                continue;
            }
if ($idsActor !== null && !in_array($idCliente, $idsActor, true)) {
                continue;
            }
$ver = 1;
            $gest = asesconPuedeGestionarCliente($this->conexion, $idCliente) ? 1 : 0;
            $ins->bind_param('iiiii', $idUsuario, $idCliente, $ver, $gest, $actorId);
            if (!$ins->execute()) {
                throw new RuntimeException('Error asignando una empresa.');
            }
        }
        $ins->close();
    }

    public function guardarPermisosMenu(array $post): void
    {
        if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_equipo')) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'csrf']);
        }
asesconExigirPermiso($this->conexion, 'EQUIPO', 'editar');
        $id = (int)($post['id_usuario'] ?? 0);
        if ($id <= 0 || !asesconPuedeAdministrarUsuario($this->conexion, $id)) {
            http_response_code(403);
            exit('No puedes cambiar permisos de esta cuenta.');
        }
        $stmt = $this->conexion->prepare('SELECT id_rol FROM usuarios WHERE id_usuario=? LIMIT 1');
        if (!$stmt) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'bd']);
        }
$stmt->bind_param('i', $id);
        $stmt->execute();
        $idRol = (int)($stmt->get_result()->fetch_assoc()['id_rol'] ?? 0);
        $stmt->close();
        $sel = is_array($post['menu'] ?? null) ? array_map('strval', $post['menu']) : [];
        $this->conexion->begin_transaction();
        try {
            $this->guardarPermisosMenuSeleccionados($id, $idRol, $sel, isset($post['ver_todo']));
            $this->conexion->commit();
            asesconRegistrarAuditoria($this->conexion, 'PERMISOS_MENU', 'EQUIPO', null, 'usuarios', (string)$id, 'Permisos visibles del menú actualizados');
            asesconRedirigir('vistas/equipo/equipo.php', ['permisos' => 1]);
        } catch (Throwable $e) {
            $this->conexion->rollback();
            error_log($e->getMessage());
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'bd']);
        }
    }

    public function asignarClientes(array $post): void
    {
        if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_equipo')) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'csrf']);
        }
asesconExigirPermiso($this->conexion, 'EQUIPO', 'editar');
        $id = (int)($post['id_usuario'] ?? 0);
        if ($id <= 0 || !asesconPuedeAdministrarUsuario($this->conexion, $id)) {
            http_response_code(403);
            exit('No puedes asignar clientes a esta cuenta.');
        }
$actor = asesconUsuarioActual($this->conexion);
        $sel = is_array($post['clientes_asignados'] ?? null) ? $post['clientes_asignados'] : [];
        $this->conexion->begin_transaction();
        try {
            $s = $this->conexion->prepare('UPDATE usuarios SET acceso_todos_clientes=0 WHERE id_usuario=?');
            if ($s) {
                $s->bind_param('i', $id);
                $s->execute();
                $s->close();
            }
$this->guardarClientesRapidos($id, $sel, false, (int)$actor['id_usuario']);
            $this->conexion->commit();
            asesconRegistrarAuditoria($this->conexion, 'ASIGNAR_CLIENTES', 'EQUIPO', null, 'usuarios', (string)$id, 'Asignación de clientes actualizada');
            asesconRedirigir('vistas/equipo/equipo.php', ['asignado' => 1]);
        } catch (Throwable $e) {
            $this->conexion->rollback();
            error_log($e->getMessage());
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'bd']);
        }
    }

    public function cambiarPasswordUsuario(array $post): void
    {
        $this->procesarCambioPassword($post);
    }

    private function procesarCambioPassword(array $post): void
    {
        if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_equipo')) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'csrf']);
        }
        try {
            $gestor = new GestorContrasenas($this->conexion);
            $gestor->cambiarDeUsuario((int)($post['id_usuario'] ?? 0), (string)($post['password_nueva'] ?? ''));
            asesconRedirigir('vistas/equipo/equipo.php', ['password' => 1]);
        } catch (DomainException $error) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => $error->getMessage()]);
        } catch (Throwable $error) {
            error_log('Cambio de contraseña: ' . $error->getMessage());
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'bd']);
        }
    }

    public function cambiarRol(array $post): void
    {
        if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_equipo')) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'csrf']);
        }
$id = (int)($post['id_usuario'] ?? 0);
        $idRol = (int)($post['id_rol'] ?? 0);
        if ($id <= 0 || !asesconPuedeAdministrarUsuario($this->conexion, $id)) {
            http_response_code(403);
            exit('Usuario no autorizado.');
        }
if (!asesconPuedeAsignarRol($this->conexion, $idRol)) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'rol']);
        }
$model = new Usuario($this->conexion);
        $model->cambiarRol($id, $idRol);
        $this->conexion->query('DELETE FROM usuario_modulos WHERE id_usuario=' . (int)$id);
        asesconRegistrarAuditoria($this->conexion, 'CAMBIAR_ROL', 'EQUIPO', null, 'usuarios', (string)$id, 'Rol actualizado y permisos personalizados reiniciados');
        asesconRedirigir('vistas/equipo/equipo.php', ['rol_cambiado' => 1]);
    }

    public function eliminar(array $post): void
    {
        if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_equipo')) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'csrf']);
        }
asesconExigirPermiso($this->conexion, 'EQUIPO', 'eliminar');
        $id = (int)($post['id_usuario'] ?? 0);
        $confirmacion = trim((string)($post['confirmacion'] ?? ''));
        if ($id <= 0 || !asesconPuedeAdministrarUsuario($this->conexion, $id)) {
            http_response_code(403);
            exit('Cuenta protegida o no autorizada.');
        }
$model = new Usuario($this->conexion);
        $u = $model->obtener($id);
        if (!$u) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'usuario_no_encontrado']);
        }
if ($confirmacion !== (string)$u['usuario']) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'confirmacion']);
        }
$nombre = (string)$u['usuario'];
        $model->eliminar($id);
        asesconRegistrarAuditoria($this->conexion, 'ELIMINAR_USUARIO', 'EQUIPO', null, 'usuarios', (string)$id, 'Usuario eliminado: ' . $nombre);
        asesconRedirigir('vistas/equipo/equipo.php', ['eliminado' => 1]);
    }

    public function estado(array $post): void
    {
        if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_equipo')) {
            asesconRedirigir('vistas/equipo/equipo.php', ['error' => 'csrf']);
        }
$id = (int)($post['id_usuario'] ?? 0);
        $estado = (int)($post['estado'] ?? 0) === 1 ? 1 : 0;
        if (!asesconPuedeAdministrarUsuario($this->conexion, $id)) {
            http_response_code(403);
            exit('Cuenta protegida o no autorizada.');
        }(new Usuario($this->conexion))->cambiarEstado($id, $estado);
        asesconRegistrarAuditoria($this->conexion, $estado ? 'ACTIVAR_USUARIO' : 'DESACTIVAR_USUARIO', 'EQUIPO', null, 'usuarios', (string)$id, null);
        asesconRedirigir('vistas/equipo/equipo.php', ['estado' => 1]);
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    asesconExigirLogin();
    $c = new ControladorEquipo($conexion);
    $a = (string)($_POST['accion'] ?? '');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'guardar') {
        $c->guardar($_POST);
    }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'asignar_clientes') {
        $c->asignarClientes($_POST);
    }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'permisos_menu') {
        $c->guardarPermisosMenu($_POST);
    }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'cambiar_password_usuario') {
        $c->cambiarPasswordUsuario($_POST);
    }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'cambiar_rol') {
        $c->cambiarRol($_POST);
    }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'eliminar') {
        $c->eliminar($_POST);
    }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'estado') {
        $c->estado($_POST);
    }
asesconRedirigir('vistas/equipo/equipo.php');
}
