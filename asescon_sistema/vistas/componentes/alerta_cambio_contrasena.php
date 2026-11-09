<?php
$mostrarRecordatorioPassword = (bool)($recordatorioContrasena['mostrar'] ?? false);
if ($mostrarRecordatorioPassword):
    $diasPassword = (int)($recordatorioContrasena['dias'] ?? 0);
?>
<div class="password-reminder" role="status" aria-live="polite">
    <div class="password-reminder-copy">
        <strong>Recomendación de seguridad</strong>
        <span>Han pasado <?php echo $diasPassword; ?> días desde tu último cambio de contraseña. Te recomendamos actualizarla.</span>
        <a href="<?php echo htmlspecialchars(asesconUrl('vistas/seguridad/mi_perfil.php#contrasena')); ?>">Cambiar contraseña</a>
    </div>
    <form method="post" action="<?php echo htmlspecialchars(asesconUrl('controladores/seguridad/ControladorRecordatorioContrasena.php')); ?>" class="password-reminder-close">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$csrfRecordatorioContrasena); ?>">
        <input type="hidden" name="accion" value="descartar">
        <button type="submit" aria-label="Cerrar recordatorio" title="Cerrar">×</button>
    </form>
</div>
<?php endif; ?>
