<?php

declare(strict_types=1);

require_once __DIR__ . '/configuracion/sesiones.php';

asesconIniciarSesion();

asesconRedirigir(
    isset($_SESSION['id_usuario'])
        ? 'vistas/panel_principal.php'
        : 'vistas/inicio_sesion.php'
);
