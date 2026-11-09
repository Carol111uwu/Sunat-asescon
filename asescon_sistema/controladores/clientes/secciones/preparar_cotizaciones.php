<?php

declare(strict_types=1);
if (!isset($seccionClientes, $conexion)) { http_response_code(404); exit; }

require_once __DIR__.'/../../../configuracion/inicializacion.php';
require_once __DIR__.'/../ControladorCotizaciones.php';
asesconExigirPermiso($conexion,'COTIZACIONES','ver');

$datos=(new ControladorCotizaciones($conexion))->datos();extract($datos);
$csrf=asesconCsrf('csrf_cotizaciones');$puedeCrear=asesconTienePermiso($conexion,'COTIZACIONES','crear');

