<?php if (!isset($conexion)) { http_response_code(404);exit; } ?>
<?php if ($sscoBarra !== null):?>
<details class="consultas-oficiales alertas-ssco-menu"><summary aria-label="Ver padrón SSCO">△ <span><?=min(99,(int)$sscoBarra['total'])?><?=$sscoBarra['total']>99?'+':''?></span></summary>
<nav aria-label="Registros SSCO recientes"><strong>Últimos registros del padrón SSCO</strong>
<?php if(!$sscoBarra['filas']):?><p>Todavía no hay un padrón cargado.</p><?php endif;?>
<?php foreach($sscoBarra['filas'] as $sscoReciente):?>
<a href="<?=htmlspecialchars(asesconUrl('vistas/alertas/alertas_sunat.php').'?ssco_buscar='.rawurlencode($sscoReciente['ruc']).'#padronSsco',ENT_QUOTES,'UTF-8')?>"><strong><?=htmlspecialchars($sscoReciente['razon_social'],ENT_QUOTES,'UTF-8')?></strong><small><?=htmlspecialchars($sscoReciente['ruc'].' · '.$sscoReciente['resolucion'],ENT_QUOTES,'UTF-8')?></small></a>
<?php endforeach;?><a href="<?=htmlspecialchars(asesconUrl('vistas/alertas/alertas_sunat.php').'#padronSsco',ENT_QUOTES,'UTF-8')?>">Ver lista y buscar</a></nav></details>
<?php endif;?>
