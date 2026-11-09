<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../modelos/seguridad/Usuario.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    asesconRedirigir('vistas/inicio_sesion.php');
    exit();
}

if (!asesconValidarCsrf((string)($_POST['csrf_token'] ?? ''), 'csrf_login')) {
    asesconRedirigir('vistas/inicio_sesion.php', ['error' => 'sesion']);
}

$usuarioTxt = trim((string)($_POST['usuario'] ?? ''));
$password = (string)($_POST['password'] ?? '');
if ($usuarioTxt === '' || $password === '') {
    asesconRedirigir('vistas/inicio_sesion.php', ['error' => '1']);
    exit();
}

$model = new Usuario($conexion);
$usuario = $model->buscarPorUsuario($usuarioTxt);
if (!$usuario) {
    asesconRedirigir('vistas/inicio_sesion.php', ['error' => '1']);
    exit();
}

if (!empty($usuario['bloqueado_hasta']) && strtotime((string)$usuario['bloqueado_hasta']) > time()) {
    asesconRedirigir('vistas/inicio_sesion.php', ['error' => 'bloqueado']);
    exit();
}

if (!password_verify($password, (string)$usuario['password'])) {
    $intentos = $model->registrarIntentoFallido((int)$usuario['id_usuario']);
    if ($intentos >= 5) {
        $model->bloquearUsuario((int)$usuario['id_usuario'], 15);
        asesconRedirigir('vistas/inicio_sesion.php', ['error' => 'bloqueado']);
        exit();
    }
    asesconRedirigir('vistas/inicio_sesion.php', ['error' => '1']);
    exit();
}

$_SESSION = [];
session_regenerate_id(true);
$model->reiniciarIntentos((int)$usuario['id_usuario']);
$model->registrarAcceso((int)$usuario['id_usuario']);

$_SESSION['firma_password'] = hash('sha256', (string)$usuario['password']);
$_SESSION['id_usuario'] = (int)$usuario['id_usuario'];
$_SESSION['usuario'] = (string)$usuario['usuario'];
$_SESSION['rol'] = (string)$usuario['rol_codigo'];
$_SESSION['rol_nombre'] = (string)$usuario['rol_nombre'];
$_SESSION['id_rol'] = (int)$usuario['id_rol'];
$_SESSION['es_principal'] = (int)($usuario['es_principal'] ?? 0);
$_SESSION['acceso_todos_clientes'] = (int)($usuario['acceso_todos_clientes'] ?? 0);
$_SESSION['autenticado'] = true;

asesconRedirigir('vistas/panel_principal.php');
exit();
