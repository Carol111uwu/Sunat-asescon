<?php
if (!isset($avisoModulo) || !is_array($avisoModulo)) { return; }
$etiqueta = (string)($avisoModulo['etiqueta'] ?? 'Estado del módulo');
$descripcion = (string)($avisoModulo['descripcion'] ?? '');
?>
<div class="integration-note" role="status" style="margin:12px 0 18px;line-height:1.5">
  <strong><?php echo htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?>:</strong>
  <?php echo htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8'); ?>
</div>
