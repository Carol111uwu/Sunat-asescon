(() => {
  'use strict';
                                                                        
  document.querySelectorAll('.spot-disclosure[data-toggle-label]').forEach(detalle => {
    const texto = detalle.querySelector('.spot-summary-text');
    const icono = detalle.querySelector('.spot-summary-indicator');
    const actualizar = () => {
      if (texto) texto.textContent = detalle.open ? 'Ocultar importación de archivos SUNAT' : 'Mostrar importación de archivos SUNAT';
      if (icono) icono.textContent = detalle.open ? '−' : '+';
    };
    detalle.addEventListener('toggle', actualizar);
    actualizar();
  });

  const boton = document.getElementById('spotExportarBorrador');
  if (!boton) return;
  const concepto = document.getElementById('spotConcepto');
  const porcentaje = document.getElementById('spotPorcentaje');
  const resumen = document.getElementById('spotSeleccionResumen');
  const controles = [...document.querySelectorAll('#spotCandidatos input.spot-pick')];
  const seleccionadas = () => controles.filter(entrada => entrada.checked);

  function actualizar() {
    const n = seleccionadas().length;
    const tasa = Number(porcentaje?.value);
    const valido = n > 0 && concepto?.value.trim() && Number.isFinite(tasa) && tasa > 0 && tasa <= 100 && /^\d{1,3}(?:\.\d{1,2})?$/.test(porcentaje.value.trim());
    boton.disabled = !valido;
    if (resumen) resumen.textContent = n
      ? `${n} CPE seleccionado(s). ${valido ? 'Borrador listo para exportar; revisar porcentaje y operación antes de pagar.' : 'Indica código/concepto y porcentaje válido (0–100) para exportar.'}`
      : 'Marca los CPE para preparar un borrador. Ninguno está marcado.';
  }
  controles.forEach(c => c.addEventListener('change', actualizar));
  [concepto, porcentaje].forEach(c => c?.addEventListener('input', actualizar));

  const seguroCsv = valor => {
    let texto = String(valor ?? '');
    if (/^[\s]*[=+@\t\r-]/.test(texto)) texto = "'" + texto;
    return '"' + texto.replace(/"/g, '""') + '"';
  };
  boton.addEventListener('click', () => {
    actualizar();
    if (boton.disabled) return;
    const tasa = Number(porcentaje.value);
    const nombre = concepto.value.trim();
    const cabeceras = ['Periodo SIRE', 'Fecha emisión', 'Tipo CPE', 'Serie', 'Número', 'RUC proveedor', 'Razón social proveedor', 'Total CPE', 'Código o concepto SPOT por revisar', 'Tasa estimada %', 'Monto estimado', 'Estado'];
    const filas = seleccionadas().map(input => {
      const d = JSON.parse(input.dataset.registro || '{}');
      const total = Number(d.importe_total);
      const importe = Number.isFinite(total) && total > 0 ? (Math.round(total * tasa) / 100).toFixed(2) : '';
      return [d.periodo, d.fecha_emision, d.tipo_comprobante, d.serie, d.numero, d.ruc_proveedor, d.razon_social_proveedor, d.importe_total, nombre, tasa.toFixed(2), importe, 'BORRADOR SIN PAGO'];
    });
    const contenido = '\uFEFF' + [cabeceras, ...filas].map(row => row.map(seguroCsv).join(';')).join('\r\n');
    const url = URL.createObjectURL(new Blob([contenido], {type:'text/csv;charset=utf-8'}));
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = 'detracciones_borrador_sin_pago_' + new Date().toISOString().slice(0, 10) + '.csv';
    enlace.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 1500);
    if (resumen) resumen.textContent = 'Borrador descargado. No se ha pagado ni enviado nada a SUNAT; verifica el código SPOT y tasa con un responsable.';
  });
  actualizar();
})();
