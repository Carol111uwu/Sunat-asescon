<?php
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
$avisosCasos=[];
if(asesconTienePermiso($conexion,'BUZON_SOL','ver')){
    try{$repoAvisos=AccesoCasosSol::repositorio();$avisosCasos=$repoAvisos?$repoAvisos->notificaciones((int)asesconUsuarioActual($conexion)['id_usuario'],asesconEsPrincipal($conexion)):[];}catch(Throwable $e){error_log('Avisos de casos: '.get_class($e));}
    $nuevosCasos=count(array_filter($avisosCasos,static fn(array $n):bool=>!(int)$n['leida']));
?>
<?php $pendientesSol=max(0,(int)($sinLeerTop??0)); ?>
<button class="notification-top" id="abrirAvisosCasosSol" type="button" data-pendientes-sol="<?=$pendientesSol?>" title="Notificaciones SUNAT y casos" aria-label="Notificaciones SUNAT y casos" style="background:none;border:0;padding:0;cursor:pointer;text-decoration:none">🔔<span><?=$pendientesSol+(int)$nuevosCasos?></span></button>
<dialog class="ventana-sol" id="avisosCasosSol" data-url="<?=htmlspecialchars(asesconUrl('api/buzones/notificaciones_casos_sol.php'))?>" data-casos="<?=htmlspecialchars(asesconUrl('vistas/buzones/mis_casos_sol.php'))?>" data-csrf="<?=htmlspecialchars(asesconCsrf('csrf_avisos_casos'))?>"><header><h2>Mis notificaciones</h2><button type="button" data-cerrar-avisos aria-label="Cerrar">×</button></header><div class="ventana-sol-acciones" style="margin:12px 0"><p style="margin:0">Buzón SOL: <strong><?=$pendientesSol?></strong> sin leer</p><a class="reference-btn primary" href="<?=htmlspecialchars(asesconUrl('vistas/buzones/buzon_sol_sunat.php'))?>">Abrir Buzón SOL</a></div><h3>Mis avisos de casos</h3><p data-mensaje-avisos role="status"></p><div data-lista-avisos></div></dialog>
<link rel="stylesheet" href="<?=htmlspecialchars(asesconUrl('publico/css/modulos/ventanas_buzon_sol.css'))?>?v=20261022_5"><script defer src="<?=htmlspecialchars(asesconUrl('publico/js/buzones/avisos_casos_sol.js'))?>?v=20261028_1"></script>
<?php } ?>
