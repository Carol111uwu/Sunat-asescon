        <form class="reference-filter-grid" id="formConsultaOficial">
            <label class="reference-field"><span>Empresa</span><select name="id_cliente" required><option value="">Selecciona una empresa</option><?php foreach($clientesOficiales as $c): ?><option value="<?php echo (int)$c['id_cliente']; ?>" <?php echo (int)($_GET['id_cliente']??0)===(int)$c['id_cliente']?'selected':''; ?> data-ruc="<?php echo htmlspecialchars($c['ruc']); ?>" data-correo="<?php echo htmlspecialchars((string)$c['correo']); ?>"><?php echo htmlspecialchars($c['ruc'].' — '.$c['razon_social']); ?></option><?php endforeach; ?></select></label>
            <label class="reference-field"><span>Presentadas desde</span><input type="date" name="inicio" value="<?php echo date('Y-m-01'); ?>" required></label>
            <label class="reference-field"><span>Hasta</span><input type="date" name="fin" value="<?php echo date('Y-m-d'); ?>" required></label>
            <div class="reference-actions"><button class="reference-btn primary" <?php echo $puedeConsultarOficial?'':'disabled'; ?>>Consultar SUNAT</button></div>
        </form>
