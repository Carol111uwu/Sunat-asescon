<?php
$accesosRelacionados=[
    ['DECLARACIONES','Control de declaraciones','vistas/sunat/control_declaraciones.php'],
    ['PDT_0621','Formulario 0621','vistas/sunat/pdt_0621.php'],
    ['PLAME_0601','PLAME 0601','vistas/sunat/plame_0601.php'],
    ['DETRACCIONES','Detracciones','vistas/sunat/detracciones.php'],
];
?>
<div class="reference-actions accesos-relacionados-oficiales" aria-label="Accesos relacionados">
    <?php foreach($accesosRelacionados as [$codigo,$texto,$ruta]): if(!asesconTienePermiso($conexion,$codigo,'ver'))continue; ?>
    <a class="reference-btn" href="<?php echo htmlspecialchars(asesconUrl($ruta)); ?>"><?php echo htmlspecialchars($texto); ?></a>
    <?php endforeach; ?>
</div>
