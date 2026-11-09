<?php

declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../controladores/clientes/ControladorCotizaciones.php';
asesconExigirPermiso($conexion,'COTIZACIONES','ver');
$moduloActivo='COTIZACIONES';
$datos=(new ControladorCotizaciones($conexion))->datos();extract($datos);
$csrf=asesconCsrf('csrf_cotizaciones');$puedeCrear=asesconTienePermiso($conexion,'COTIZACIONES','crear');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ventas / Cotizaciones - ASESCON</title>
<link rel="stylesheet" href="../../publico/css/base/estructura_panel.css?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/css/base/estructura_panel.css');?>"><link rel="stylesheet" href="../../publico/css/modulos/sunat.css?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/css/modulos/sunat.css');?>"></head><body><div class="app">
<?php require __DIR__.'/../componentes/menu_lateral.php';?><main class="main-content"><?php require __DIR__.'/../componentes/barra_superior.php';?><section class="content">
<div class="reference-head-actions"><div class="page-header"><div><h1>Ventas / Cotizaciones</h1><p class="quote-page-subtitle">Gestiona prospectos y genera cotizaciones en PDF.</p></div></div><?php if($puedeCrear):?><button type="button" class="reference-btn primary quote-new-btn" id="abrirCotizacion">＋ Nueva cotización</button><?php endif;?></div>
<?php if(isset($_GET['guardado'])):?><div class="notice ok">Cotización guardada correctamente.</div><?php endif;?><?php if(isset($_GET['error'])):?><div class="notice bad">No se pudo guardar la cotización. Revisa el prospecto y los ítems.</div><?php endif;?>

<div class="reference-tabs quote-tabs"><span class="reference-tab active">Cotizaciones</span><span class="reference-tab">Prospectos</span><span class="reference-tab">Servicios</span></div>
<section class="panel module-panel"><div class="table-container"><table><thead><tr><th>Código</th><th>Prospecto / Cliente</th><th>Emisión</th><th>Válida hasta</th><th>Moneda</th><th>Total</th><th>Estado</th></tr></thead><tbody>
<?php if($filas===[]):?><tr><td colspan="7"><div class="reference-empty">Aún no hay cotizaciones registradas.</div></td></tr><?php else:foreach($filas as $d):?><tr><td><strong><?php echo htmlspecialchars((string)$d['numero_cotizacion']);?></strong></td><td><?php echo htmlspecialchars((string)$d['destino']);?></td><td><?php echo htmlspecialchars(date('d/m/Y',strtotime((string)$d['fecha_emision'])));?></td><td><?php echo !empty($d['fecha_vencimiento'])?htmlspecialchars(date('d/m/Y',strtotime((string)$d['fecha_vencimiento']))):'—';?></td><td><?php echo htmlspecialchars((string)($d['moneda']??'PEN'));?></td><td><strong><?php echo (($d['moneda']??'PEN')==='USD'?'US$ ':'S/ ').number_format((float)$d['total'],2);?></strong></td><td><span class="reference-badge"><?php echo htmlspecialchars((string)$d['estado']);?></span></td></tr><?php endforeach;endif;?></tbody></table></div></section>

<?php if($puedeCrear):?>
<dialog id="cotizacionDialog" class="quote-dialog"><form method="post" action="../../controladores/clientes/ControladorCotizaciones.php" id="cotizacionForm" class="quote-form">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf);?>"><input type="hidden" name="accion" value="crear_cotizacion">
<div class="quote-dialog-head"><h2>Nueva cotización</h2><button type="button" class="quote-close" data-close-dialog="cotizacionDialog">×</button></div>
<div class="quote-grid two">
<label>Prospecto *<div class="quote-inline-field"><select name="prospecto" id="prospectoSelect" required><option value="">Selecciona un prospecto</option><?php foreach($prospectos as $p):?><option value="P:<?php echo (int)$p['id_prospecto'];?>"><?php echo htmlspecialchars($p['nombre'].($p['ruc']?' · '.$p['ruc']:''));?></option><?php endforeach;?><?php if($clientes!==[]):?><optgroup label="Clientes registrados"><?php foreach($clientes as $c):?><option value="C:<?php echo (int)$c['id_cliente'];?>"><?php echo htmlspecialchars($c['razon_social'].' · '.$c['ruc']);?></option><?php endforeach;?></optgroup><?php endif;?></select><button type="button" class="quote-square-btn" id="abrirProspecto" title="Nuevo prospecto">＋</button></div></label>
<label>Código (opcional)<input name="codigo" placeholder="<?php echo htmlspecialchars($codigoSugerido);?>"></label>
<label>Fecha de emisión<input type="date" name="fecha_emision" value="<?php echo date('Y-m-d');?>" required></label>
<label>Válida hasta<input type="date" name="valida_hasta"></label>
<label>Moneda<select name="moneda" id="quoteCurrency"><option value="PEN">PEN (S/)</option><option value="USD">USD (US$)</option></select></label>
</div>

<section class="quote-items-box"><div class="quote-items-head"><h3>Ítems</h3><div class="quote-item-actions"><select id="catalogoSelect"><option value="">＋ Desde catálogo</option><?php foreach($servicios as $s):?><option value="<?php echo (int)$s['id_servicio'];?>" data-name="<?php echo htmlspecialchars($s['nombre'],ENT_QUOTES,'UTF-8');?>" data-price="<?php echo htmlspecialchars((string)$s['precio_referencial']);?>" data-currency="<?php echo htmlspecialchars($s['moneda']);?>"><?php echo htmlspecialchars($s['nombre'].' · '.$s['moneda'].' '.number_format((float)$s['precio_referencial'],2));?></option><?php endforeach;?></select><button type="button" class="reference-btn" id="agregarLibre">＋ Ítem libre</button><button type="button" class="reference-btn" id="abrirServicio">＋ Nuevo servicio</button></div></div>
<div id="quoteItems" class="quote-items-empty">Agrega ítems desde tu catálogo o crea uno libre.</div>
<div class="quote-total"><span>Total:</span><strong id="quoteTotal">S/ 0.00</strong></div></section>
<label class="quote-notes">Notas / Condiciones<textarea name="notas_condiciones" rows="4" placeholder="Forma de pago, vigencia, condiciones..."></textarea></label>
<div class="quote-footer"><button type="button" class="reference-btn" data-close-dialog="cotizacionDialog">Cancelar</button><button class="reference-btn primary" type="submit">Guardar cotización</button></div>
</form></dialog>

<dialog id="prospectoDialog" class="mini-reference-dialog"><form id="prospectoForm"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf);?>"><input type="hidden" name="accion" value="crear_prospecto"><input type="hidden" name="ajax" value="1"><div class="quote-dialog-head"><h2>Nuevo prospecto</h2><button type="button" class="quote-close" data-close-dialog="prospectoDialog">×</button></div><div class="quote-grid two"><label>Nombre *<input name="nombre_prospecto" required></label><label>RUC<input name="ruc_prospecto" maxlength="11"></label><label>Contacto<input name="contacto_prospecto"></label><label>Teléfono<input name="telefono_prospecto"></label><label class="span-2">Correo<input type="email" name="correo_prospecto"></label></div><div class="quote-footer"><button type="button" class="reference-btn" data-close-dialog="prospectoDialog">Cancelar</button><button class="reference-btn primary">Guardar prospecto</button></div></form></dialog>

<dialog id="servicioDialog" class="mini-reference-dialog"><form id="servicioForm"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf);?>"><input type="hidden" name="accion" value="crear_servicio"><input type="hidden" name="ajax" value="1"><div class="quote-dialog-head"><h2>Nuevo servicio</h2><button type="button" class="quote-close" data-close-dialog="servicioDialog">×</button></div><div class="quote-grid two"><label>Nombre *<input name="nombre" required placeholder="Ej. Libros contables"></label><label>Precio referencial<input type="number" min="0" step="0.01" name="precio_referencial" value="0.00"></label><label>Moneda<select name="moneda"><option value="PEN">PEN (S/)</option><option value="USD">USD (US$)</option></select></label><label class="span-2">Descripción<textarea name="descripcion_servicio" rows="3"></textarea></label></div><div class="quote-footer"><button type="button" class="reference-btn" data-close-dialog="servicioDialog">Cancelar</button><button class="reference-btn primary">Guardar servicio</button></div></form></dialog>
<?php endif;?>
<footer>© <?= (int)(new DateTimeImmutable("now", new DateTimeZone("America/Lima")))->format("Y") ?> ASESCON - Todos los derechos reservados.</footer></section></main></div>
<script src="../../publico/js/navegacion/menu_lateral.js"></script><script src="../../publico/js/navegacion/busqueda_clientes.js?v=20261014_1"></script><script src="../../publico/js/navegacion/menu_movil.js"></script><script src="../../publico/js/interfaz/componentes_interfaz.js?v=20260915_3"></script>
<script src="../../publico/js/clientes/cotizaciones.js"></script><script src="../../publico/js/interfaz/tablas_adaptables.js?v=<?php echo (int)@filemtime(__DIR__.'/../../publico/js/interfaz/tablas_adaptables.js'); ?>"></script></body></html>
