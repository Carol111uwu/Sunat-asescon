<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../servicios/seguridad/ServicioRecuperacionContrasena.php';

asesconIniciarSesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    asesconRedirigir('vistas/recuperar_contrasena.php', ['canal' => 'sms']);
}

if (!asesconValidarCsrf((string)($_POST['csrf_token'] ?? ''), 'csrf_verificar_sms')) {
    $_SESSION['mensaje_codigo_sms'] = 'La solicitud no es válida. Inténtalo nuevamente.';
    asesconRedirigir('vistas/verificar_codigo_sms.php');
}

try {
    $token = (new ServicioRecuperacionContrasena($conexion))->verificarCodigoSms((string)($_POST['codigo'] ?? ''));
    asesconRedirigir('vistas/restablecer_contrasena.php', ['token' => $token]);
} catch (DomainException $error) {
    $mensajes = [
        'codigo_sms_invalido' => 'Ingresa el código de 6 dígitos que recibiste por SMS.',
        'codigo_sms_incorrecto' => 'El código no coincide. Revisa el SMS e inténtalo nuevamente.',
        'codigo_sms_expirado' => 'El código venció. Solicita uno nuevo.',
        'codigo_sms_bloqueado' => 'Se alcanzó el máximo de intentos para este código. Solicita uno nuevo.',
    ];
    $_SESSION['mensaje_codigo_sms'] = $mensajes[$error->getMessage()] ?? 'No fue posible validar el código.';
    asesconRedirigir('vistas/verificar_codigo_sms.php');
} catch (Throwable $error) {
    error_log('Verificación SMS ASESCON: ' . $error->getMessage());
    $_SESSION['mensaje_codigo_sms'] = 'No fue posible validar el código en este momento.';
    asesconRedirigir('vistas/verificar_codigo_sms.php');
}
