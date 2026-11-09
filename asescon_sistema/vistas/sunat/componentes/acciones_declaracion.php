<?php
if (!isset($conexion, $d)) { http_response_code(404); exit; }
require_once __DIR__.'/../../../servicios/sunat/PortalesSunat.php';
$idDeclaracionAccion = (int)($d['id_declaraciones_sunat'] ?? 0);
$idClienteAccion = (int)$d['id_cliente'];
?>
<?php if ($idDeclaracionAccion > 0 && asesconTienePermiso($conexion, 'DECLARACIONES', 'ver')): ?>
<button class="mini-btn" type="button" data-pagos-declaracion="<?=$idDeclaracionAccion?>">Pagos / constancias</button>
<?php endif; ?>
<?php if (PortalesSunat::puedeAcceder($conexion, $idClienteAccion)): ?>
<a class="mini-btn" href="acceso_portal.php?cliente=<?=$idClienteAccion?>&amp;destino=declaraciones">Abrir SUNAT</a>
<?php endif; ?>
