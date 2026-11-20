<?php
// Usa los registros y permisos ya resueltos por ControladorBuzonSunafil.
$ordenes=[];
foreach($filas as $fila){
    $c=json_decode((string)($fila['campos_oficiales']??''),true)?:[];
    $numero=trim((string)($c['Orden de Inspección']??''));
    if($numero==='')continue;
    $clave=(int)$fila['id_cliente'].'|'.$numero;
    if(!isset($ordenes[$clave]))$ordenes[$clave]=['numero'=>$numero,'cliente'=>$fila['razon_social'],'ruc'=>$fila['ruc'],'intendencia'=>$c['Intendencia']??'','estado'=>$c['Estado de orden']??$c['Estado']??'','docs'=>[]];
    if(isset($c['Número de documento']))$ordenes[$clave]['docs'][]=[$fila,$c];
}
$seleccion=(string)($_GET['orden_detalle']??'');
$baseFiscal=['categoria'=>'FISCALIZACION','id_cliente'=>$idCliente,'pagina'=>$pagina_actual];
$columnasFiscal=['Tipo de documento','Número de documento','Fecha de depósito','Fecha de notificación','Fecha de comparecencia','Fecha límite de respuesta','Envío de respuesta'];
?>
<section class="reference-panel buzon-tabla-panel">
<div class="reference-table-title"><div><h2>Notificaciones de Fiscalización</h2><p>Órdenes guardadas de la empresa mostrada. Los estados oficiales son independientes de la revisión interna.</p></div></div>
<?php if(isset($ordenes[$seleccion])):$orden=$ordenes[$seleccion];?>
<a class="reference-btn" href="?<?=$escBuzon(http_build_query($baseFiscal))?>">← Volver a órdenes</a>
<h3>Orden de inspección <?=$escBuzon($orden['numero'])?></h3>
<p><?=$escBuzon($orden['cliente'])?> · RUC <?=$escBuzon($orden['ruc'])?> · <?=$escBuzon($orden['intendencia'])?></p>
<form id="fiscalFiltros" class="reference-filter-grid">
<label class="reference-field">Tipo de documento<input data-fiscal-filtro="0"></label>
<label class="reference-field">Número de documento<input data-fiscal-filtro="1"></label>
<label class="reference-field">Fecha de depósito desde<input type="date" data-fiscal-desde></label>
<label class="reference-field">Fecha de depósito hasta<input type="date" data-fiscal-hasta></label>
<label class="reference-field">Año<input type="number" min="1900" max="2100" data-fiscal-anio></label>
<div class="reference-actions"><button class="reference-btn primary">Buscar</button><button type="reset" class="reference-btn">Limpiar</button></div></form>
<div class="table-container"><table id="tablaSunafil" class="buzon-tabla-simple"><thead><tr><?php foreach($columnasFiscal as $col):?><th><?=$escBuzon($col)?></th><?php endforeach;?><th>Estado ASESCON</th><th>PDF / acción</th></tr></thead><tbody>
<?php foreach($orden['docs'] as [$d,$c]):$idDocumento=(int)$d['id_documento'];?>
<tr data-fiscal-fila><?php foreach($columnasFiscal as $col):?><td data-label="<?=$escBuzon($col)?>"><?=$escBuzon($c[$col]??'—')?></td><?php endforeach;?>
<td data-label="Estado ASESCON"><?=$d['estado']==='REVISADO'?'Revisado':'Pendiente'?></td>
<td data-label="PDF / acción"><div class="buzon-acciones-sunafil">
<button class="reference-btn" type="button" data-buzon-detalle="SUNAFIL" data-id-documento="<?=$idDocumento?>">Ver documento</button>
<details class="buzon-mas-acciones"><summary class="reference-btn">Más acciones</summary><div class="buzon-mas-menu">
<?php if($puedeConsultar||!empty($d['archivo'])):?><button class="reference-btn" type="button" data-pdf-sunafil="<?=$idDocumento?>" data-csrf="<?=$escBuzon($csrf)?>">PDF</button><small data-estado-pdf-sunafil="<?=$idDocumento?>" role="status"></small><?php endif;?>
<button class="reference-btn" type="button" data-buzon-detalle="SUNAFIL" data-accion-inicial="whatsapp" data-id-documento="<?=$idDocumento?>">WhatsApp Web</button>
<?php if($puedeConsultar):?><a class="reference-btn" href="caso_sunafil.php?id_documento=<?=$idDocumento?>#correo-sunafil">Correo</a><?php endif;?>
<a class="reference-btn" href="caso_sunafil.php?id_documento=<?=$idDocumento?>#seguimiento-sunafil">Seguimiento</a></div></details>
<?php if($d['estado']==='NO_REVISADO'&&$puedeConsultar):?><form method="post" action="../../controladores/buzones/ControladorBuzonSunafil.php"><input type="hidden" name="csrf_token" value="<?=$escBuzon($csrf)?>"><input type="hidden" name="accion" value="revisado"><input type="hidden" name="categoria" value="FISCALIZACION"><input type="hidden" name="id_cliente" value="<?=$idCliente?>"><input type="hidden" name="id_documento" value="<?=$idDocumento?>"><button class="reference-btn" type="submit">Marcar revisado</button></form><?php endif;?></div></td></tr>
<?php endforeach;?>
<?php if(!$orden['docs']):?><tr><td colspan="9">No hay documentos guardados para esta orden. El detalle oficial puede estar pendiente de verificación.</td></tr><?php endif;?>
</tbody></table></div>
<?php else:?>
<form id="fiscalFiltros" class="reference-filter-grid">
<label class="reference-field">Orden de inspección<input data-fiscal-filtro="1"></label>
<label class="reference-field">Año<input type="number" min="1900" max="2100" data-fiscal-anio></label>
<label class="reference-field">Intendencia<input data-fiscal-filtro="2"></label>
<label class="reference-field">Estado oficial<input data-fiscal-filtro="3"></label>
<label class="reference-field">Fecha desde<input type="date" data-fiscal-desde></label>
<label class="reference-field">Fecha hasta<input type="date" data-fiscal-hasta></label>
<div class="reference-actions"><button class="reference-btn primary">Buscar</button><button type="reset" class="reference-btn">Limpiar</button></div></form>
<p>Las fechas de este listado se comparan con las fechas de depósito de los documentos guardados de cada orden.</p>
<div class="table-container"><table id="tablaSunafil" class="buzon-tabla-simple"><thead><tr><th>Cliente</th><th>Orden de inspección</th><th>Intendencia</th><th>Estado</th><th>PDF / acción</th></tr></thead><tbody>
<?php foreach($ordenes as $clave=>$orden):?><tr data-fiscal-fila data-fiscal-fechas="<?=$escBuzon(json_encode(array_map(static fn($doc)=>$doc[1]['Fecha de depósito']??'', $orden['docs'])))?>"><td data-label="Cliente"><?=$escBuzon($orden['cliente'])?></td><td data-label="Orden de inspección"><?=$escBuzon($orden['numero'])?></td><td data-label="Intendencia"><?=$escBuzon($orden['intendencia']?:'—')?></td><td data-label="Estado"><?=$escBuzon($orden['estado']?:'—')?></td><td><a class="reference-btn" href="?<?=$escBuzon(http_build_query($baseFiscal+['orden_detalle'=>$clave]))?>">Ver documentos (<?=count($orden['docs'])?>)</a></td></tr><?php endforeach;?>
<?php if(!$ordenes):?><tr><td colspan="5">No hay órdenes guardadas. Consulta la casilla; una consulta parcial no confirma que esté vacía.</td></tr><?php endif;?>
</tbody></table></div>
<?php endif;?>
<p id="fiscalResultados" role="status"></p>
</section>
<script defer src="../../publico/js/sunafil/fiscalizacion.js?v=20261120"></script>
