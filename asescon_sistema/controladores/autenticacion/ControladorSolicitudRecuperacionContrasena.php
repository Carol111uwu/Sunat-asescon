<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../servicios/seguridad/ServicioRecuperacionContrasena.php';

asesconIniciarSesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    asesconRedirigir('vistas/recuperar_contrasena.php');
}

if (!asesconValidarCsrf((string)($_POST['csrf_token'] ?? ''), 'csrf_recuperar_contrasena')) {
    $_SESSION['mensaje_recuperacion'] = 'La solicitud no es válida. Inténtalo nuevamente.';
    $_SESSION['tipo_mensaje_recuperacion'] = 'error';
    asesconRedirigir('vistas/recuperar_contrasena.php');
}

$canal = strtolower(trim((string)($_POST['canal'] ?? 'correo')));
$servicio = new ServicioRecuperacionContrasena($conexion);

try {
    if ($canal === 'correo') {
        $servicio->solicitarPorCorreo((string)($_POST['correo'] ?? ''));
        $_SESSION['mensaje_recuperacion'] = 'Si existe una cuenta asociada a este correo, recibirás instrucciones para recuperar tu contraseña.';
        $_SESSION['tipo_mensaje_recuperacion'] = 'info';
        asesconRedirigir('vistas/recuperar_contrasena.php');
    }

    if ($canal === 'sms') {
        $resultado = $servicio->solicitarPorSms((string)($_POST['telefono'] ?? ''));
        $_SESSION['telefono_recuperacion_oculto'] = (string)$resultado['telefono_oculto'];
        asesconRedirigir('vistas/verificar_codigo_sms.php');
    }

    throw new DomainException('canal_invalido');
} catch (DomainException $error) {
    $mensajes = [
        'correo_invalido' => 'Ingresa un correo electrónico válido.',
        'telefono_invalido' => 'Ingresa los 9 dígitos de un celular peruano válido.',
        'sms_no_disponible' => 'La recuperación por SMS todavía no está configurada en el servidor. Usa correo electrónico o inténtalo más tarde.',
        'canal_invalido' => 'Selecciona un método de recuperación válido.',
    ];
    $_SESSION['mensaje_recuperacion'] = $mensajes[$error->getMessage()] ?? 'No fue posible procesar la recuperación.';
    $_SESSION['tipo_mensaje_recuperacion'] = 'error';
    asesconRedirigir('vistas/recuperar_contrasena.php', ['canal' => $canal === 'sms' ? 'sms' : 'correo']);
} catch (Throwable $error) {
    error_log('Recuperación ASESCON: ' . $error->getMessage());
    $_SESSION['mensaje_recuperacion'] = 'No fue posible procesar la recuperación en este momento.';
    $_SESSION['tipo_mensaje_recuperacion'] = 'error';
    asesconRedirigir('vistas/recuperar_contrasena.php', ['canal' => $canal === 'sms' ? 'sms' : 'correo']);
}
