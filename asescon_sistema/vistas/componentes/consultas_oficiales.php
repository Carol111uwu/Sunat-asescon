<?php if (!isset($conexion)) { http_response_code(404);exit; } ?>
<details class="consultas-oficiales"><summary>Consultas <span aria-hidden="true">⌄</span></summary><nav aria-label="Consultas oficiales">
<?php foreach(require __DIR__.'/../../configuracion/consultas_oficiales.php' as [$consultaNombre,$consultaUrl]):?>
<a href="<?=htmlspecialchars($consultaUrl,ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener noreferrer"><?=htmlspecialchars($consultaNombre,ENT_QUOTES,'UTF-8')?></a>
<?php endforeach;?>
<?php if(asesconTienePermiso($conexion,'TIPO_CAMBIO','ver')):?><a href="<?=htmlspecialchars(asesconUrl('vistas/sunat/tipo_cambio.php'),ENT_QUOTES,'UTF-8')?>">Tipo de cambio en ASESCON</a><?php endif;?>
</nav></details>
