<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/archivos/GeneradorContenedorZip.php';
asesconExigirLogin();if(!array_filter(['CLIENTES','DECLARACIONES','SIRE','BUZON_SOL'],fn($m)=>asesconTienePermiso($conexion,$m,'ver'))){http_response_code(403);exit('No autorizado.');}
$carpeta=dirname(__DIR__,2).'/complementos/navegador';$entradas=[];
foreach(['manifest.json','coordinador.js','puente_asescon.js','acceso_sol.js','LEEME.txt'] as $n){$entradas['asistente_sunat/'.$n]=(string)file_get_contents($carpeta.'/'.$n);}
$bytes=(new GeneradorContenedorZip())->crear($entradas);header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="asistente_sunat.zip"');header('X-Content-Type-Options: nosniff');header('Content-Length: '.strlen($bytes));echo $bytes;
