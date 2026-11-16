<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../controladores/buzones/ControladorCasoSunafil.php';

$id = (int)($_GET['id_documento'] ?? 0);
$postCaso = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? $_POST : null;
extract((new ControladorCasoSunafil($conexion))->preparar($id, $postCaso), EXTR_SKIP);
$moduloActivo = 'BUZON_SUNAFIL';
$esc = static fn($x): string => htmlspecialchars((string)$x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Caso SUNAFIL - ASESCON</title><link rel="stylesheet" href="<?= $esc(asesconRecurso('publico/css/base/estructura_panel.css')) ?>"><link rel="stylesheet" href="<?= $esc(asesconRecurso('publico/css/modulos/sunat.css')) ?>"><link rel="stylesheet" href="<?= $esc(asesconRecurso('publico/css/modulos/buzones_ordenados.css')) ?>"></head><body><div class="app"><?php require __DIR__.'/../componentes/menu_lateral.php';?><main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?><section class="content buzon-renovado">
<div class="page-header"><h1>Gestión de caso SUNAFIL</h1><a class="reference-btn" href="buzon_sunafil.php?id_cliente=<?=$cliente?>">Volver al buzón</a></div>
<?php if($mensaje):?><p class="notice" role="status"><?=$esc($mensaje)?></p><?php endif;?>
<section class="reference-panel"><h2><?=$esc($doc['asunto'])?></h2><p><?=$esc($doc['razon_social'])?> · RUC <?=$esc($doc['ruc'])?></p><p><?=$esc($doc['observaciones']??'')?></p></section>
<?php $token='<input type="hidden" name="csrf_token" value="'.$esc($csrf).'">';?>
<section class="reference-panel" id="pdf-sunafil"><h2>PDF oficial</h2>
<?php if($archivo):?><a class="reference-btn primary" href="../../api/buzones/archivo_documento.php?buzon=SUNAFIL&amp;id_documento=<?=$id?>&amp;descargar=1">Descargar PDF</a>
<?php elseif($editar):?><form method="post"><?=$token?><button class="reference-btn primary" name="accion" value="pdf">Solicitar PDF a SUNAFIL</button></form><p>La solicitud usa los enlaces directos de la casilla oficial. Si SUNAFIL no entrega el archivo, puedes vincular el PDF desde Ver documento en la bandeja.</p><?php endif;?>
<?php if((int)($estadoPdf['id_documento']??0)===$id):?><p><?=$esc($estadoPdf['mensaje']??'')?> <a href="?id_documento=<?=$id?>">Actualizar</a></p><?php endif;?>
<?php if($editar):?><h3 id="correo-sunafil">Enviar correo con PDF</h3><?php if($archivo):?><form method="post"><?=$token?><label class="reference-field">Destinatario<input type="email" name="destinatario" value="<?=$esc($doc['correo_cliente']??'')?>" required></label><label class="reference-field">Mensaje<textarea name="mensaje" required>Adjunto la notificación SUNAFIL para su revisión.</textarea></label><button class="reference-btn primary" name="accion" value="correo">Confirmar envío</button></form><?php else:?><p>Primero solicita o vincula el PDF oficial para adjuntarlo al correo.</p><?php endif;?><?php endif;?>
</section><section class="reference-panel" id="seguimiento-sunafil"><h2>Seguimiento del caso</h2>
<?php if(!$seguimientoDisponible):?><p>El seguimiento necesita prepararse una vez.</p><?php if($gestionar):?><form method="post"><?=$token?><button class="reference-btn primary" name="accion" value="habilitar">Habilitar seguimiento SUNAFIL</button></form><?php endif;?>
<?php else:?><p>Estado: <?=$esc($caso['estado']??'ABIERTO')?> · Responsable: <?=$esc($caso['responsable']??'Sin asignar')?></p>
<?php if($gestionar):?><form method="post"><?=$token?><label class="reference-field">Asignar miembro de Mi equipo<select name="responsable"><option value="0">Sin asignar</option><?php foreach($responsables as $u):?><option value="<?=(int)$u['id_usuario']?>" <?=(int)($caso['id_responsable']??0)===(int)$u['id_usuario']?'selected':''?>><?=$esc($u['usuario'])?></option><?php endforeach;?></select></label><button class="reference-btn primary" name="accion" value="asignar">Asignar responsable</button></form>
<form method="post"><?=$token?><button class="reference-btn" name="accion" value="<?=($caso['estado']??'')==='ABIERTO'?'cerrar':'reabrir'?>"><?=match($caso['estado']??''){'CERRADO'=>'Reabrir caso','ABIERTO'=>'Cerrar caso',default=>'Abrir caso'}?></button></form><?php endif;?>
<?php if($verAvances):?><h3>Historial de avances</h3><?php foreach($avances as $a):?><p><strong><?=$esc($a['usuario'])?></strong> · <?=$esc($a['creado_en'])?><br><?=nl2br($esc($a['contenido']))?></p><?php endforeach;?><?php endif;?>
<?php if($reportar):?><form method="post"><?=$token?><label class="reference-field">Nota o avance<textarea name="nota" required></textarea></label><button class="reference-btn primary" name="accion" value="nota">Guardar avance</button></form><?php endif;?>
<?php endif;?><p>El seguimiento es interno de ASESCON, no modifica el estado oficial de SUNAFIL.</p></section>
</section></main></div>
<script src="<?= $esc(asesconRecurso('publico/js/navegacion/menu_lateral.js')) ?>" defer></script>
<script src="<?= $esc(asesconRecurso('publico/js/navegacion/menu_movil.js')) ?>" defer></script>
<script src="<?= $esc(asesconRecurso('publico/js/navegacion/busqueda_clientes.js')) ?>" defer></script>
</body></html>
