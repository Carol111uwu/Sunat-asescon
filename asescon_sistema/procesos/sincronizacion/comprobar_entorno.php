<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

$extensiones=[];foreach(['mysqli','curl','openssl','mbstring','dom','zip'] as $e)$extensiones[$e]=extension_loaded($e);
$salida=['php'=>['version'=>PHP_VERSION,'cli'=>PHP_SAPI==='cli','requisito_funcional'=>PHP_VERSION_ID>=80200],'extensiones'=>$extensiones,'zona_programacion'=>'America/Lima','ventana'=>'02:00-03:00','cli'=>PHP_SAPI];
echo json_encode($salida,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
