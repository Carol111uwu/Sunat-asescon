<?php
declare(strict_types=1);
require_once __DIR__.'/configuracion/inicializacion.php';
asesconExigirLogin();if(!asesconEsPrincipal($conexion)){http_response_code(403);exit('Acceso exclusivo de la cuenta creadora.');}
header('Location: '.asesconUrl('vistas/seguridad/estado_bases_datos.php'));exit;
