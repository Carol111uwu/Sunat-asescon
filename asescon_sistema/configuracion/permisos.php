<?php

declare(strict_types=1);

require_once __DIR__ . '/sesiones.php';

function asesconUsuarioActual(mysqli $conexion): array
{
    asesconExigirLogin();

    static $usuarioValidado = null;
    if ($usuarioValidado !== null && (int)$usuarioValidado['id_usuario'] === (int)$_SESSION['id_usuario']) {
        return $usuarioValidado;
    }

    $idUsuario = (int)$_SESSION['id_usuario'];
    $stmt = $conexion->prepare(
        "SELECT u.id_usuario,u.usuario,u.password,u.id_rol,u.acceso_todos_clientes,u.estado,u.creado_por,u.tipo_cuenta,
                r.codigo AS rol_codigo,r.nombre AS rol_nombre,r.nivel AS rol_nivel,
                r.es_principal,r.puede_crear_usuarios,r.nivel_maximo_creable,r.protegido AS rol_protegido
         FROM usuarios u
         INNER JOIN roles r ON r.id_rol=u.id_rol AND r.estado=1
         WHERE u.id_usuario=? AND u.estado=1
         LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException('No se pudo validar el usuario actual.');
    }
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$usuario || !hash_equals((string)($_SESSION['firma_password'] ?? ''), hash('sha256', (string)$usuario['password']))) {
        asesconCerrarSesion();
        asesconRedirigir('vistas/inicio_sesion.php', ['error' => 'sesion']);
    }
    unset($usuario['password']);

    foreach (['id_usuario','id_rol','acceso_todos_clientes','rol_nivel','es_principal','puede_crear_usuarios','nivel_maximo_creable','rol_protegido'] as $campo) {
        $usuario[$campo] = (int)($usuario[$campo] ?? 0);
    }

    $usuarioValidado = $usuario;
    $_SESSION['rol'] = (string)$usuario['rol_codigo'];
    return $usuario;
}

function asesconCuentaConPrivilegiosPrincipales(array $usuario): bool
{
    $tipoCuenta = strtoupper((string)($usuario['tipo_cuenta'] ?? 'EQUIPO'));
    if (!in_array($tipoCuenta, ['CLIENTE', 'CREADOR'], true)) {
        return false;
    }

    return (int)($usuario['es_principal'] ?? 0) === 1
        || strtoupper((string)($usuario['rol_codigo'] ?? '')) === 'MAIN';
}

function asesconEsPrincipal(mysqli $conexion): bool
{
    return asesconCuentaConPrivilegiosPrincipales(asesconUsuarioActual($conexion));
}

function asesconPuedeAdministrarCuentasEquipo(mysqli $conexion): bool
{
    return asesconCuentaConPrivilegiosPrincipales(asesconUsuarioActual($conexion));
}

function asesconPuedeAutogestionarCredenciales(mysqli $conexion): bool
{
    $usuario = asesconUsuarioActual($conexion);
    return in_array(strtoupper((string)($usuario['tipo_cuenta'] ?? 'EQUIPO')), ['CLIENTE', 'CREADOR'], true);
}

function asesconTienePermiso(mysqli $conexion, string $codigoModulo, string $accion = 'ver'): bool
{
    $codigoModulo = strtoupper(trim($codigoModulo));
    if ($codigoModulo === '') {
        return false;
    }
    if ($codigoModulo === 'EQUIPO' && !asesconPuedeAdministrarCuentasEquipo($conexion)) {
        return false;
    }
    if (asesconEsPrincipal($conexion)) {
        return true;
    }

    $columnas = [
        'ver' => 'puede_ver',
        'crear' => 'puede_crear',
        'editar' => 'puede_editar',
        'eliminar' => 'puede_eliminar',
        'ejecutar' => 'puede_ejecutar',
    ];
    $columna = $columnas[strtolower($accion)] ?? 'puede_ver';
    $idUsuario = (int)$_SESSION['id_usuario'];

    $sql = "SELECT COALESCE(um.{$columna},rm.{$columna},0) AS permitido
            FROM usuarios u
            INNER JOIN modulos m ON m.codigo=? AND m.estado=1
            LEFT JOIN usuario_modulos um ON um.id_usuario=u.id_usuario AND um.id_modulo=m.id_modulo
            LEFT JOIN rol_modulos rm ON rm.id_rol=u.id_rol AND rm.id_modulo=m.id_modulo
            WHERE u.id_usuario=? AND u.estado=1 LIMIT 1";
    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $codigoModulo, $idUsuario);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($fila['permitido'] ?? 0) === 1;
}

function asesconExigirPermiso(mysqli $conexion, string $codigoModulo, string $accion = 'ver'): void
{
    asesconExigirLogin();
    if (asesconTienePermiso($conexion, $codigoModulo, $accion)) {
        return;
    }

    http_response_code(403);
    $mod = htmlspecialchars($codigoModulo, ENT_QUOTES, 'UTF-8');
    $dashboard = htmlspecialchars(asesconUrl('vistas/panel_principal.php'), ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Acceso restringido</title>'
       . '<style>body{font-family:Segoe UI,Arial,sans-serif;background:#f7f9fc;color:#182b45;padding:50px}.box{max-width:680px;margin:auto;background:#fff;padding:28px;border-radius:14px;box-shadow:0 4px 18px #0b234112}.btn{display:inline-block;margin-top:18px;padding:10px 14px;background:#0b2341;color:#fff;text-decoration:none;border-radius:8px}</style>'
       . '</head><body><div class="box"><h1>Acceso restringido</h1><p>Tu cuenta no tiene permiso para este módulo (' . $mod . ').</p><a class="btn" href="' . $dashboard . '">Volver al Dashboard</a></div></body></html>';
    exit();
}

function asesconPuedeCrearUsuarios(mysqli $conexion): bool
{
    $u = asesconUsuarioActual($conexion);
    return asesconPuedeAdministrarCuentasEquipo($conexion);
}

function asesconRolesCreables(mysqli $conexion): array
{
    $u = asesconUsuarioActual($conexion);
    $max = (int)$u['nivel_maximo_creable'];
    if (!asesconPuedeAdministrarCuentasEquipo($conexion) || $max <= 0) {
        return [];
    }

    $stmt = $conexion->prepare(
        "SELECT id_rol,codigo,nombre,descripcion,nivel
         FROM roles
         WHERE estado=1 AND es_principal=0 AND nivel<=?
         ORDER BY nivel DESC,nombre"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $max);
    $stmt->execute();
    $res = $stmt->get_result();
    $roles = [];
    while ($fila = $res->fetch_assoc()) {
        $roles[] = $fila;
    }
    $stmt->close();
    return $roles;
}

function asesconPuedeAsignarRol(mysqli $conexion, int $idRol): bool
{
    if ($idRol <= 0 || !asesconPuedeAdministrarCuentasEquipo($conexion)) {
        return false;
    }
    $actor = asesconUsuarioActual($conexion);
    $stmt = $conexion->prepare('SELECT nivel,es_principal FROM roles WHERE id_rol=? AND estado=1 LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $idRol);
    $stmt->execute();
    $rol = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$rol || (int)$rol['es_principal'] === 1) {
        return false;
    }
    return (int)$rol['nivel'] <= (int)$actor['nivel_maximo_creable'];
}

function asesconPuedeAdministrarUsuario(mysqli $conexion, int $idObjetivo): bool
{
    if ($idObjetivo <= 0) {
        return false;
    }

    $actor = asesconUsuarioActual($conexion);
    if (!asesconPuedeAdministrarCuentasEquipo($conexion) || $idObjetivo === (int)$actor['id_usuario']) {
        return false;
    }

    $stmt = $conexion->prepare(
        "SELECT u.creado_por,u.tipo_cuenta,r.nivel,r.es_principal,r.protegido
         FROM usuarios u INNER JOIN roles r ON r.id_rol=u.id_rol
         WHERE u.id_usuario=? LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $idObjetivo);
    $stmt->execute();
    $objetivo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$objetivo || (string)($objetivo['tipo_cuenta'] ?? 'EQUIPO') !== 'EQUIPO' || (int)$objetivo['es_principal'] === 1 || (int)$objetivo['protegido'] === 1) {
        return false;
    }

    return asesconEsPrincipal($conexion);
}

function asesconIdsClientesPermitidos(mysqli $conexion): ?array
{
    $u = asesconUsuarioActual($conexion);
    if (asesconEsPrincipal($conexion) || (int)$u['acceso_todos_clientes'] === 1) {
        return null;
    }

    $idUsuario = (int)$u['id_usuario'];
    $stmt = $conexion->prepare('SELECT id_cliente FROM usuario_clientes WHERE id_usuario=? AND puede_ver=1 ORDER BY id_cliente');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($fila = $res->fetch_assoc()) {
        $ids[] = (int)$fila['id_cliente'];
    }
    $stmt->close();
    return $ids;
}

function asesconFiltroClientesSql(mysqli $conexion, string $alias = 'c'): string
{
    $ids = asesconIdsClientesPermitidos($conexion);
    if ($ids === null) {
        return '1=1';
    }
    if ($ids === []) {
        return '1=0';
    }
    return $alias . '.id_cliente IN (' . implode(',', array_map('intval', $ids)) . ')';
}

function asesconPuedeVerCliente(mysqli $conexion, int $idCliente): bool
{
    if ($idCliente <= 0) {
        return false;
    }

    $ids = asesconIdsClientesPermitidos($conexion);
    return $ids === null || in_array($idCliente, $ids, true);
}

function asesconPuedeGestionarCliente(mysqli $conexion, int $idCliente): bool
{
    if ($idCliente <= 0) {
        return false;
    }

    if (asesconEsPrincipal($conexion)) {
        return true;
    }

    $u = asesconUsuarioActual($conexion);
    if ((int)$u['acceso_todos_clientes'] === 1) {
        return asesconTienePermiso($conexion, 'CLIENTES', 'editar');
    }

    $idUsuario = (int)$u['id_usuario'];
    $stmt = $conexion->prepare('SELECT puede_gestionar FROM usuario_clientes WHERE id_usuario=? AND id_cliente=? AND puede_ver=1 LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $idUsuario, $idCliente);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($fila['puede_gestionar'] ?? 0) === 1;
}

function asesconRegistrarAuditoria(
    mysqli $conexion,
    string $accion,
    string $modulo,
    ?int $idCliente = null,
    ?string $entidad = null,
    ?string $idEntidad = null,
    ?string $detalle = null
): void {
    $idUsuario = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null;
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $stmt = $conexion->prepare(
        'INSERT INTO auditoria (id_usuario,id_cliente,accion,modulo,entidad,id_entidad,detalle,ip) VALUES (?,?,?,?,?,?,?,?)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('iissssss', $idUsuario, $idCliente, $accion, $modulo, $entidad, $idEntidad, $detalle, $ip);
    @$stmt->execute();
    $stmt->close();
}

function asesconClientesVisibles(mysqli $conexion, bool $soloActivos = false): mysqli_result|false
{
    $filtro = asesconFiltroClientesSql($conexion, 'c');
    $estado = $soloActivos ? " AND c.estado='ACTIVO'" : '';
    return $conexion->query(
        "SELECT c.* FROM clientes c WHERE {$filtro}{$estado} ORDER BY c.razon_social,c.ruc"
    );
}
