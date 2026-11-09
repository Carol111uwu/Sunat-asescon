<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/configuracion/conexion.php';
require_once dirname(__DIR__,2).'/servicios/sunat/ServicioPrecargaAdjuntosSol.php';
try{(new ServicioPrecargaAdjuntosSol($conexion))->programarExistentes();}
catch(Throwable $e){error_log('Precarga de adjuntos SOL: '.get_class($e));exit(1);}
