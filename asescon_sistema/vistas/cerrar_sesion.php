<?php

declare(strict_types=1);
require_once __DIR__ . '/../configuracion/sesiones.php';
asesconIniciarSesion();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !asesconValidarCsrf((string)($_POST['csrf_token'] ?? ''), 'csrf_salida')) {
    asesconRedirigir('vistas/panel_principal.php');
}
asesconCerrarSesion();
asesconRedirigir('vistas/inicio_sesion.php');
