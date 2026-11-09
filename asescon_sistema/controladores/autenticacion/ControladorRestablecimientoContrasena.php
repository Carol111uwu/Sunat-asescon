<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../servicios/seguridad/ServicioRecuperacionContrasena.php';

asesconIniciarSesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    asesconRedirigir('vistas/inicio_sesion.php');
}

if (!asesconValidarCsrf((string)($_POST['csrf_token'] ?? ''), 'csrf_restablecer_contrasena')) {
    $_SESSION['mensaje_restablecer'] = 'La solicitud no es válida. Actualiza la página e inténtalo nuevamente.';
    asesconRedirigir('vistas/restablecer_contrasena.php', ['token' => (string)($_POST['token'] ?? '')]);
}

$token = trim((string)($_POST['token'] ?? ''));

try {
    (new ServicioRecuperacionContrasena($conexion))->restablecer(
        $token,
        (string)($_POST['password'] ?? ''),
        (string)($_POST['password_confirmacion'] ?? '')
    );
    $_SESSION['mensaje_login'] = 'Tu contraseña fue restablecida correctamente. Ahora puedes iniciar sesión.';
    asesconRedirigir('vistas/inicio_sesion.php');
} catch (DomainException $error) {
    $mensajes = [
        'token_invalido' => 'El enlace de recuperación no es válido o ya expiró.',
        'password_confirmacion' => 'La nueva contraseña y su confirmación no coinciden.',
        'password_longitud' => 'La contraseña debe tener entre 8 y 72 caracteres.',
    ];
    $_SESSION['mensaje_restablecer'] = $mensajes[$error->getMessage()] ?? 'No fue posible restablecer la contraseña.';
    asesconRedirigir('vistas/restablecer_contrasena.php', ['token' => $token]);
} catch (Throwable $error) {
    error_log('Restablecimiento de contraseña ASESCON: ' . $error->getMessage());
    $_SESSION['mensaje_restablecer'] = 'No se pudo restablecer la contraseña. Inténtalo nuevamente.';
    asesconRedirigir('vistas/restablecer_contrasena.php', ['token' => $token]);
}
