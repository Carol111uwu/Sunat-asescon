<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
date_default_timezone_set('America/Lima');
require_once __DIR__.'/../../configuracion/conexion.php';
require_once __DIR__.'/../../servicios/comunicaciones/ProcesadorAvisosBuzones.php';
try { (new ProcesadorAvisosBuzones($conexion))->procesar(); }
catch (Throwable $e) { error_log('Cola de avisos: proceso no completado ('.get_class($e).').');exit(1); }
