<?php
if (!isset($conexion)) { http_response_code(404);exit; }
require_once __DIR__.'/../../../modelos/comunicaciones/ConfiguracionComunicaciones.php';
require_once __DIR__.'/../../../modelos/sunat/PadronSsco.php';
require_once __DIR__.'/../../../servicios/sunat/LectorPadronSsco.php';
$sscoDisponible=(new ConfiguracionComunicaciones($conexion))->disponible();
$sscoBuscar=trim((string)($_GET['ssco_buscar']??''));
$sscoDatos=$sscoDisponible?(new PadronSsco($conexion))->datos($sscoBuscar,(int)($_GET['ssco_pagina']??1)):['filas'=>[],'total'=>0,'pagina'=>1,'paginas'=>1,'ultima'=>null];
$sscoEsc=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$sscoCsrf=asesconCsrf('csrf_ssco');
$sscoMensaje=$_SESSION['mensaje_ssco']??'';$sscoError=$_SESSION['error_ssco']??'';unset($_SESSION['mensaje_ssco'],$_SESSION['error_ssco']);
?>
<section class="reference-panel" id="padronSsco"><div class="reference-table-title"><div><h2>Lista SSCO · <?=(int)$sscoDatos['total']?> resultados</h2><p>Busca por RUC, razón social, resolución, domicilio o representante.</p></div><a class="reference-btn" href="<?=$sscoEsc(LectorPadronSsco::FUENTE)?>" target="_blank" rel="noopener noreferrer">Ver fuente SUNAT</a></div>
<?php if($sscoMensaje):?><p class="comunicaciones-aviso" role="status"><?=$sscoEsc($sscoMensaje)?></p><?php endif;?>
<?php if($sscoError):?><p class="comunicaciones-aviso error" role="alert"><?=$sscoEsc($sscoError)?></p><?php endif;?>
<?php if(!$sscoDisponible):?><p>La cuenta principal debe preparar el módulo para cargar el padrón.</p><?php if(asesconEsPrincipal($conexion)):?><a class="reference-btn" href="<?=$sscoEsc(asesconUrl('vistas/comunicaciones/configuracion_correo.php'))?>">Preparar configuración</a><?php endif;?>
<?php else:?>
<?php if($sscoDatos['ultima']):?><p class="reference-subtle">Última carga: <?=$sscoEsc($sscoDatos['ultima']['actualizado_en'])?> · <?=$sscoEsc($sscoDatos['ultima']['origen'])?>. La lista conserva el historial de RUC y resoluciones importadas.</p><?php endif;?>
<form method="get" class="ssco-acciones" action="alertas_sunat.php#padronSsco"><input type="hidden" name="buscar" value="<?=$sscoEsc($buscar)?>"><input type="hidden" name="estado" value="<?=$sscoEsc($estado)?>"><label class="reference-field">Buscar en SSCO<input type="search" name="ssco_buscar" value="<?=$sscoEsc($sscoBuscar)?>" placeholder="RUC, nombre o resolución..."></label><button class="reference-btn primary">Buscar</button></form>
<?php if(asesconTienePermiso($conexion,'ALERTAS_SUNAT','editar')):?>
<div class="ssco-acciones"><form method="post" action="<?=$sscoEsc(asesconUrl('controladores/sunat/ControladorPadronSsco.php'))?>"><input type="hidden" name="csrf_token" value="<?=$sscoEsc($sscoCsrf)?>"><button class="reference-btn" name="accion" value="actualizar">Actualizar desde SUNAT</button></form>
<form method="post" enctype="multipart/form-data" action="<?=$sscoEsc(asesconUrl('controladores/sunat/ControladorPadronSsco.php'))?>"><input type="hidden" name="csrf_token" value="<?=$sscoEsc($sscoCsrf)?>"><label class="reference-field">Importar padrón XLSX o CSV<input type="file" name="padron" accept=".csv,.xlsx" required></label><button class="reference-btn" name="accion" value="importar">Importar padrón</button></form><a class="reference-btn" href="<?=$sscoEsc(LectorPadronSsco::ARCHIVO_OFICIAL)?>" target="_blank" rel="noopener noreferrer">XLSX oficial SUNAT</a><a class="reference-btn" href="<?=$sscoEsc(asesconUrl('publico/plantillas/padron_ssco.csv'))?>" download>Plantilla CSV</a></div><?php endif;?>
<div class="table-container"><table><thead><tr><th>RUC</th><th>Razón social</th><th>Resolución</th><th>Fecha firme</th><th>Fecha publicación</th><th>Domicilio fiscal</th><th>Representante legal</th></tr></thead><tbody>
<?php if(!$sscoDatos['filas']):?><tr><td colspan="7"><?=$sscoBuscar!==''?'No hay coincidencias con esa búsqueda.':'Todavía no hay registros cargados. Actualiza desde SUNAT o importa el padrón CSV.'?></td></tr><?php endif;?>
<?php foreach($sscoDatos['filas'] as $sscoFila):?><tr><?php foreach(LectorPadronSsco::CAMPOS as $sscoCampo):?><td><?=$sscoEsc($sscoFila[$sscoCampo]??'—')?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div>
<nav class="ssco-acciones" aria-label="Páginas del padrón"><span>Página <?=(int)$sscoDatos['pagina']?> de <?=(int)$sscoDatos['paginas']?></span>
<?php foreach(['Anterior'=>-1,'Siguiente'=>1] as $sscoTexto=>$sscoPaso):$sscoPagina=$sscoDatos['pagina']+$sscoPaso;if($sscoPagina<1||$sscoPagina>$sscoDatos['paginas'])continue;?>
<a class="reference-btn" href="?<?=$sscoEsc(http_build_query(['ssco_buscar'=>$sscoBuscar,'ssco_pagina'=>$sscoPagina,'buscar'=>$buscar,'estado'=>$estado]))?>#padronSsco"><?=$sscoTexto?></a><?php endforeach;?></nav><?php endif;?></section>
