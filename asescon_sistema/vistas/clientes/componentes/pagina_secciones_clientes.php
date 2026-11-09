<?php
declare(strict_types=1);
if (!isset($grupoClientes,$conexion)) { http_response_code(404); exit; }
require_once __DIR__.'/../../../configuracion/secciones_clientes.php';
$grupoConfigurado=asesconGruposClientes()[$grupoClientes];
$seccionesVisibles=asesconSeccionesClientesVisibles($conexion,$grupoClientes);
if (!$seccionesVisibles) { http_response_code(403); exit('No tienes acceso a estas funciones.'); }
$seccionClientes=(string)($_GET['seccion']??array_key_first($seccionesVisibles));
if (!isset($grupoConfigurado['secciones'][$seccionClientes])) { http_response_code(404); exit('La sección no existe.'); }
asesconExigirPermiso($conexion,$grupoConfigurado['secciones'][$seccionClientes]['permiso'],'ver');
$directorioPreparadores=__DIR__.'/../../../controladores/clientes/secciones/';
require $directorioPreparadores.'preparar_'.$seccionClientes.'.php';
$moduloActivo=$grupoClientes;
$escClientes=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=$escClientes($grupoConfigurado['titulo'])?> - ASESCON</title>
<?php foreach(['base/estructura_panel','modulos/sunat','modulos/clientes','modulos/secciones_clientes'] as $hoja): ?>
<link rel="stylesheet" href="<?=$escClientes(asesconUrl('publico/css/'.$hoja.'.css'))?>?v=<?=(int)filemtime(__DIR__.'/../../../publico/css/'.$hoja.'.css')?>">
<?php endforeach; ?></head><body><div class="app">
<?php require __DIR__.'/../../componentes/menu_lateral.php'; ?>
<main class="main-content"><?php require __DIR__.'/../../componentes/barra_superior.php'; ?>
<section class="content"><div class="page-header"><h1><?=$escClientes($grupoConfigurado['titulo'])?></h1></div>
<nav class="clientes-secciones" aria-label="Secciones de <?=$escClientes($grupoConfigurado['titulo'])?>">
<?php foreach($seccionesVisibles as $claveSeccion=>$opcionSeccion): ?>
<a href="<?=$escClientes(asesconUrl($grupoConfigurado['ruta']).'?'.http_build_query(['seccion'=>$claveSeccion]))?>" <?=$seccionClientes===$claveSeccion?'aria-current="page"':''?>><?=$escClientes($opcionSeccion['titulo'])?></a>
<?php endforeach; ?></nav>
<?php require __DIR__.'/../secciones/'.$seccionClientes.'.php'; ?>
<footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer>
</section></main></div>
<?php foreach(['navegacion/menu_lateral','navegacion/busqueda_clientes','navegacion/menu_movil','interfaz/componentes_interfaz','interfaz/tablas_adaptables'] as $scriptClientes): ?>
<script src="<?=$escClientes(asesconUrl('publico/js/'.$scriptClientes.'.js'))?>?v=<?=(int)filemtime(__DIR__.'/../../../publico/js/'.$scriptClientes.'.js')?>"></script>
<?php endforeach; ?>
<?php if($seccionClientes==='recordatorios'): ?><script src="<?=$escClientes(asesconUrl('publico/js/clientes/recordatorio_whatsapp.js'))?>"></script><?php endif; ?>
<?php if($seccionClientes==='cotizaciones'&&($puedeCrear||asesconTienePermiso($conexion,'COTIZACIONES','editar'))): ?><script src="<?=$escClientes(asesconUrl('publico/js/clientes/cotizaciones.js'))?>?v=<?=(int)filemtime(__DIR__.'/../../../publico/js/clientes/cotizaciones.js')?>"></script><?php endif; ?>
</body></html>
