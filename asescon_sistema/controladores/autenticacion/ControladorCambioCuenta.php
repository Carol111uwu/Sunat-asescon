<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../servicios/seguridad/ServicioCambioCuenta.php';

asesconExigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    asesconRedirigir('vistas/panel_principal.php');
}

if (!asesconValidarCsrf((string)($_POST['csrf_token'] ?? ''), 'csrf_cambiar_cuenta')) {
    asesconRedirigir('vistas/panel_principal.php', ['cuenta' => 'sesion']);
}

try {
    (new ServicioCambioCuenta($conexion))->cambiarA((int)($_POST['id_usuario'] ?? 0));
    asesconRedirigir('vistas/panel_principal.php');
} catch (DomainException $e) {
    asesconRedirigir('vistas/panel_principal.php', ['cuenta' => 'no_autorizada']);
} catch (Throwable $e) {
    error_log('ControladorCambiarCuenta: ' . $e->getMessage());
    asesconRedirigir('vistas/panel_principal.php', ['cuenta' => 'error']);
}
