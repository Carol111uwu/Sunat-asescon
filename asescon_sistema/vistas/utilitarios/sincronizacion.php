<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/sincronizacion/ColaNocturna.php';
asesconExigirLogin();if(!asesconEsPrincipal($conexion)){http_response_code(403);exit('Solo la cuenta principal puede ver la programación de todas las empresas.');}
$moduloActivo='UTILITARIOS';$colaPanel=new ColaNocturna();$pausada=$colaPanel->estaPausada();$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$mensajeSync=$_SESSION['mensaje_sync_general']??null;unset($_SESSION['mensaje_sync_general']);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sincronización total - ASESCON</title><link rel="stylesheet" href="<?=$esc(asesconUrl('publico/css/base/estructura_panel.css'))?>"><link rel="stylesheet" href="<?=$esc(asesconUrl('publico/css/modulos/sunat.css'))?>"></head><body><div class="app"><?php require __DIR__.'/../componentes/menu_lateral.php';?><main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?><section class="content"><h1>Sincronización total</h1>
<?php if(is_array($mensajeSync)):?><div class="notice <?=$mensajeSync['tipo']==='ok'?'good':($mensajeSync['tipo']==='error'?'bad':'')?>" role="status"><?=$esc($mensajeSync['texto']??'')?></div><?php endif;?>
<section class="reference-panel compact sync-programacion" aria-label="Sincronización programada" style="margin:14px 0;padding:20px">
 <h2 style="margin-top:0">Sincronización programada</h2>
 <p><strong>Horario Perú:</strong> limpieza de copias de ayer entre 00:40 y 01:50; sincronización diaria desde las 02:00 (ventana de planificación hasta las 03:00). Una consulta remota puede demorar más.</p>
 <ol class="sync-orden">
  <li><strong>Padrones:</strong> intentar actualización de Padrón RUC y SSCO; no marcar éxito sin datos oficiales.</li>
  <li><strong>Buzones SOL y SUNAFIL:</strong> atender los clientes con credenciales habilitadas, respetando los errores y sin inventar documentos.</li>
  <li><strong>Ventas RVIE y Compras RCE:</strong> consultar exclusivamente el <strong>mes calendario actual</strong> de las empresas habilitadas, todos los días.</li>
  <li><strong>Declaraciones:</strong> continúan en la cola existente. <strong>Detracciones SPOT:</strong> aún falta un conector autenticado; no se presenta como sincronización exitosa.</li>
 </ol>
 <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">
  <form method="post" action="<?=$esc(asesconUrl('controladores/sincronizacion/ControladorSincronizacionGeneral.php'))?>" onsubmit="return confirm('¿Programar las consultas del mes actual y los demás módulos disponibles?');">
   <input type="hidden" name="csrf_token" value="<?=$esc(asesconCsrf('csrf_sync_general'))?>">
   <button class="reference-btn primary" data-sync-solicitar type="submit" <?=$pausada?'disabled aria-disabled="true"':''?>>Solicitar sincronización</button>
  </form>
  <form data-sync-pausa method="post" action="<?=$esc(asesconUrl('controladores/sincronizacion/ControladorPausaGeneral.php'))?>" onsubmit="return confirm('<?=$pausada?'¿Reanudar trabajos pendientes sin borrar los documentos?':'¿Pausar la asignación de nuevas tareas? Los trabajos en curso podrán finalizar.'?>');">
   <input type="hidden" name="csrf_token" value="<?=$esc(asesconCsrf('csrf_pausa_sync'))?>">
   <input type="hidden" name="accion" value="<?=$pausada?'reanudar':'pausar'?>">
   <button class="reference-btn" type="submit"><?=$pausada?'Reanudar sincronización':'Pausar sincronización'?></button>
  </form>
 </div>
 <small>Pausar detiene la asignación de nuevas tareas y conserva la pausa manual. La limpieza diaria elimina automáticamente copias antiguas; la pausa no bloquea la limpieza.</small>
</section>
<?php require __DIR__.'/componentes/progreso_global.php'; ?>
<p class="reference-subtle">La barra muestra tareas reales. Si la ejecución nocturna no está activada en cPanel, las solicitudes permanecerán pendientes; no se simula su finalización.</p>
</section></main></div>
<script src="<?=$esc(asesconUrl('publico/js/navegacion/menu_lateral.js'))?>"></script>
<script src="<?=$esc(asesconUrl('publico/js/navegacion/busqueda_clientes.js'))?>"></script>
<script src="<?=$esc(asesconUrl('publico/js/navegacion/menu_movil.js'))?>"></script>
<script src="<?=$esc(asesconUrl('publico/js/interfaz/componentes_interfaz.js'))?>"></script>
</body></html>
