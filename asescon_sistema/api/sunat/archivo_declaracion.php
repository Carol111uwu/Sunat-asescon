<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/sunat/DeclaracionSunat.php';
require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
asesconExigirPermiso($conexion,'DECLARACIONES','ver');
$d=(new DeclaracionSunat($conexion))->obtener((int)($_GET['id']??0),asesconFiltroClientesSql($conexion,'c'));
if(!$d){http_response_code(404);exit('Constancia no disponible.');}
$archivos=new ServicioArchivosPrivados();$ruta=$archivos->resolver((string)($d['archivo_constancia']??''),'DECLARACIONES');
if(!$ruta){http_response_code(404);exit('El PDF no está vinculado o ya no está disponible.');}
$archivos->servir($ruta,!empty($_GET['descargar']),'constancia_'.preg_replace('/[^0-9]/','',(string)$d['ruc']).'_'.preg_replace('/[^0-9]/','',(string)$d['periodo']).'.pdf');
