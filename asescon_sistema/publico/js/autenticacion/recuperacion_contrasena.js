document.addEventListener('DOMContentLoaded', () => {
  const tarjeta = document.querySelector('.tarjeta-recuperacion');
  if (!tarjeta) return;

  const botones = [...tarjeta.querySelectorAll('[data-canal-selector]')];
  const formularios = [...tarjeta.querySelectorAll('[data-formulario-canal]')];
  const inicial = tarjeta.dataset.canalInicial === 'sms' ? 'sms' : 'correo';

  const mostrar = canal => {
    botones.forEach(boton => {
      const activo = boton.dataset.canalSelector === canal;
      boton.classList.toggle('activo', activo);
      boton.setAttribute('aria-selected', activo ? 'true' : 'false');
    });
    formularios.forEach(formulario => {
      formulario.hidden = formulario.dataset.formularioCanal !== canal;
    });
  };

  botones.forEach(boton => boton.addEventListener('click', () => mostrar(boton.dataset.canalSelector || 'correo')));

  const telefono = tarjeta.querySelector('#telefono');
  telefono?.addEventListener('input', () => {
    telefono.value = telefono.value.replace(/\D+/g, '').slice(0, 9);
  });

  mostrar(inicial);
});
