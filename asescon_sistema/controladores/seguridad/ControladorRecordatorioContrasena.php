<?php

declare(strict_types=1);

require_once __DIR__ . '/../../configuracion/inicializacion.php';
require_once __DIR__ . '/../../servicios/seguridad/ServicioRecordatorioContrasena.php';

asesconExigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    asesconRedirigir('vistas/panel_principal.php');
}

if (!asesconValidarCsrf((string)($_POST['csrf_token'] ?? ''), 'csrf_recordatorio_contrasena')) {
    asesconRedirigir('vistas/panel_principal.php');
}

if ((string)($_POST['accion'] ?? '') === 'descartar') {
    (new ServicioRecordatorioContrasena($conexion))->descartarAvisoActual();
}

asesconRedirigir('vistas/panel_principal.php');
