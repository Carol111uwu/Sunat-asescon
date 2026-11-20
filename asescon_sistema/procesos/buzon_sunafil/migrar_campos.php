<?php
declare(strict_types=1);
if(PHP_SAPI !== 'cli'){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/configuracion/conexion.php';
$sql=file_get_contents(dirname(__DIR__,2).'/base_datos/migraciones/2026_11_20_campos_sunafil.sql');
if(!$conexion->query($sql)){fwrite(STDERR,"No se aplicó la migración.\n");exit(1);}
echo "Campos SUNAFIL: migración aplicada.\n";
