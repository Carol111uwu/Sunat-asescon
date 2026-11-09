<nav class="reference-tabs navegacion-oficial" aria-label="Secciones de declaraciones y pagos">
    <?php foreach(['consulta'=>'Declaraciones y pagos','constancias'=>'Constancias','casillas'=>'Casillas y anexos','reportes'=>'Reportes PDF y Excel'] as $codigo=>$nombre): ?>
    <button type="button" class="reference-tab<?php echo $seccionOficial===$codigo?' active':''; ?>" data-vista-oficial="<?php echo $codigo; ?>" aria-controls="vistaOficial_<?php echo $codigo; ?>" aria-pressed="<?php echo $seccionOficial===$codigo?'true':'false'; ?>"><?php echo htmlspecialchars($nombre); ?></button>
    <?php endforeach; ?>
</nav>
