<?php
declare(strict_types=1);
if (!isset($seccionClientes, $conexion)) { http_response_code(404); exit; }
require_once __DIR__.'/../../../configuracion/inicializacion.php';require_once __DIR__.'/../ControladorPlanes.php';asesconExigirPermiso($conexion,'PLANES','ver');$datos=(new ControladorPlanes($conexion))->datos();extract($datos);$csrf=asesconCsrf('csrf_planes');
