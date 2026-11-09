<?php
if (!isset($conexion, $idClienteSol)) { http_response_code(404); exit; }
require_once __DIR__.'/../../../servicios/sunat/AccesoDirectoSol.php';
if (AccesoDirectoSol::autorizado($conexion, (int)$idClienteSol)):
?>
<form class="inline-form" method="post" target="_blank" rel="noopener" action="<?=htmlspecialchars(asesconUrl('api/sunat/abrir_sesion_sol.php'), ENT_QUOTES, 'UTF-8')?>">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars(asesconCsrf('csrf_acceso_sol'), ENT_QUOTES, 'UTF-8')?>">
<input type="hidden" name="id_cliente" value="<?=(int)$idClienteSol?>"><input type="hidden" name="destino" value="declaraciones">
<button class="mini-btn" type="submit">SUNAT</button>
</form>
<?php endif; ?>
