<?php if(asesconTienePermiso($conexion,'COMPROBANTES_SUNAT','ejecutar')):?>
<section class="reference-panel compact">
  <h2>Descarga por lista</h2>
  <p>Reúne los PDF, XML o CDR que estén vinculados en ASESCON. El ZIP incluye un resultado por comprobante; los archivos faltantes aparecen como pendientes.</p>
  <form class="module-form" method="post" enctype="multipart/form-data" action="<?php echo htmlspecialchars(asesconUrl('controladores/sunat/ControladorArchivoComprobante.php'));?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(asesconCsrf('csrf_archivos_comprobantes'));?>">
    <input type="hidden" name="accion" value="descarga_masiva"><input type="hidden" name="modulo" value="COMPROBANTES_SUNAT">
    <div class="grid">
      <label>Cliente<select name="id_cliente" required><option value="">Seleccione</option><?php foreach($clientes as $c):?><option value="<?php echo (int)$c['id_cliente'];?>"><?php echo htmlspecialchars($c['razon_social'].' · '.$c['ruc']);?></option><?php endforeach;?></select></label>
      <label>Tipo de archivo<select name="tipo_archivo"><option>PDF</option><option>XML</option><option>CDR</option></select></label>
      <label>Lista CSV o XLSX<input type="file" name="lista" accept=".csv,.xlsx" required></label>
    </div>
    <div class="form-actions"><a class="reference-btn" download href="<?php echo htmlspecialchars(asesconUrl('publico/plantillas/comprobantes_masivos.csv'));?>">Descargar plantilla</a><button class="primary-btn">Crear ZIP de archivos disponibles</button></div>
  </form>
</section>
<?php endif;?>
