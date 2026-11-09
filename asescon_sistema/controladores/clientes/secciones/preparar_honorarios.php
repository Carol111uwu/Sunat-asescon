<?php
declare(strict_types=1);
if (!isset($seccionClientes, $conexion)) { http_response_code(404); exit; }
require_once __DIR__.'/../../../configuracion/inicializacion.php';require_once __DIR__.'/../ControladorHonorarios.php';asesconExigirPermiso($conexion,'HONORARIOS','ver');$datos=(new ControladorHonorarios($conexion))->datos();extract($datos);$csrf=asesconCsrf('csrf_honorarios');
