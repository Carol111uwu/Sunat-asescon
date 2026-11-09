(() => {
 'use strict';
 window.asesconImprimirDocumento = (visor, direccion, aviso) => {
  const url = new URL(direccion, location.href);
  if (url.origin !== location.origin) return;
  try {
   if (visor.dataset.pdfCargado !== '1') throw new Error('Visor no disponible');
   visor.contentWindow.focus();
   visor.contentWindow.print();
   aviso.textContent = 'Si tu navegador no abre la impresión, usa el icono de impresora del PDF o «Abrir PDF en otra pestaña».';
  } catch {
   window.open(url.href, '_blank', 'noopener,noreferrer');
   aviso.textContent = 'Usa el icono de impresora del visor PDF en la nueva pestaña. Si no se abrió, pulsa «Abrir PDF en otra pestaña».';
  }
 };
})();
