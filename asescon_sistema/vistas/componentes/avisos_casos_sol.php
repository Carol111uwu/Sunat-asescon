<?php
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
$avisosCasos=[];
if(asesconTienePermiso($conexion,'BUZON_SOL','ver') || asesconTienePermiso($conexion,'BUZON_SUNAFIL','ver')){
    if(asesconTienePermiso($conexion,'BUZON_SOL','ver')){
        try{$repoAvisos=AccesoCasosSol::repositorio();$avisosCasos=$repoAvisos?$repoAvisos->notificaciones((int)asesconUsuarioActual($conexion)['id_usuario'],asesconEsPrincipal($conexion)):[];}catch(Throwable $e){error_log('Avisos de casos: '.get_class($e));}
    }
    $nuevosCasos=count(array_filter($avisosCasos,static fn(array $n):bool=>!(int)$n['leida']));
?>
<?php $pendientesSol=max(0,(int)($sinLeerTop??0)); ?>
<button class="notification-top" id="abrirAvisosCasosSol" type="button" data-pendientes-sol="<?=$pendientesSol?>" title="Notificaciones SUNAT y casos" aria-label="Notificaciones SUNAT y casos" style="background:none;border:0;padding:0;cursor:pointer;text-decoration:none">🔔<span><?=$pendientesSol+(int)$nuevosCasos?></span><span data-buzon-novedades-badge hidden aria-label="Nuevas notificaciones de buzones"></span></button>
<dialog class="ventana-sol" id="avisosCasosSol" data-url="<?=htmlspecialchars(asesconUrl('api/buzones/notificaciones_casos_sol.php'))?>" data-casos="<?=htmlspecialchars(asesconUrl('vistas/buzones/mis_casos_sol.php'))?>" data-csrf="<?=htmlspecialchars(asesconCsrf('csrf_avisos_casos'))?>"><header><h2>Mis notificaciones</h2><button type="button" data-cerrar-avisos aria-label="Cerrar">×</button></header><div class="ventana-sol-acciones" style="margin:12px 0"><p style="margin:0">Buzón SOL: <strong><?=$pendientesSol?></strong> sin leer</p><a class="reference-btn primary" href="<?=htmlspecialchars(asesconUrl('vistas/buzones/buzon_sol_sunat.php'))?>">Abrir Buzón SOL</a></div><section class="buzon-novedades" data-buzon-novedades
     data-url="<?=htmlspecialchars(asesconUrl('api/buzones/avisos_nuevos.php'))?>"
     data-csrf="<?=htmlspecialchars(asesconCsrf('csrf_novedades_buzones'))?>">
<h3>Nuevas notificaciones SOL y SUNAFIL</h3>
<p class="buzon-novedades-ayuda">Se actualizan periódicamente. Las notificaciones antiguas se conservan como historial.</p>
<div data-buzon-novedades-lista role="status"></div></section>
<h3>Mis avisos de casos</h3><p data-mensaje-avisos role="status"></p><div data-lista-avisos></div></dialog>
<link rel="stylesheet" href="<?=htmlspecialchars(asesconUrl('publico/css/modulos/ventanas_buzon_sol.css'))?>?v=20261022_5"><script defer src="<?=htmlspecialchars(asesconUrl('publico/js/buzones/avisos_casos_sol.js'))?>?v=20261028_1"></script>
<style>
.notification-top [data-buzon-novedades-badge]{position:absolute;top:12px;right:-29px;width:auto;height:auto;min-width:19px;background:#ab2a76;color:#fff;border-radius:999px;font-size:10px;padding:2px 5px;z-index:3}
.notification-top [data-buzon-novedades-badge][hidden]{display:none!important}
.buzon-novedades{border-top:1px solid #dbe3ef;padding:10px 0;margin:10px 0}
.buzon-novedad-fila{display:flex;justify-content:space-between;align-items:center;gap:8px;border-bottom:1px solid #e5eaf3;padding:8px 0}
.buzon-novedad-fila p{margin:4px 0;font-size:.88rem}
.buzon-novedad-fila a,.buzon-novedad-fila button{white-space:nowrap}
</style>
<script defer src="<?=htmlspecialchars(asesconUrl('publico/js/buzones/avisos_nuevos.js'))?>?v=<?=(int)@filemtime(__DIR__.'/../../publico/js/buzones/avisos_nuevos.js')?>"></script>
<?php } ?>
