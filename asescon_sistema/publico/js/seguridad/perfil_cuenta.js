document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('perfilModalConfirmacion');
  const texto = document.getElementById('perfilModalTexto');
  const confirmar = document.getElementById('perfilModalConfirmar');
  let formularioPendiente = null;

  const cerrar = () => {
    if (modal) modal.hidden = true;
    formularioPendiente = null;
  };

  document.querySelectorAll('.perfil-formulario-confirmable').forEach(formulario => {
    formulario.addEventListener('submit', evento => {
      const bandera = formulario.querySelector('[data-confirmacion-campo]');
      if (bandera && bandera.value === '1') return;
      evento.preventDefault();
      formularioPendiente = formulario;
      const etiqueta = formulario.getAttribute('data-etiqueta') || 'dato';
      if (texto) texto.textContent = `¿Confirmas que deseas actualizar ${etiqueta}?`;
      if (modal) modal.hidden = false;
    });
  });

  document.querySelectorAll('[data-cerrar-modal]').forEach(boton => boton.addEventListener('click', cerrar));

  confirmar?.addEventListener('click', () => {
    if (!formularioPendiente) return;
    const bandera = formularioPendiente.querySelector('[data-confirmacion-campo]');
    if (bandera) bandera.value = '1';
    formularioPendiente.requestSubmit();
  });

  document.querySelectorAll('.perfil-input-telefono').forEach(campo => {
    campo.addEventListener('input', () => {
      campo.value = campo.value.replace(/\D+/g, '').slice(0, 9);
    });
  });
});
