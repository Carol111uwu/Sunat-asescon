document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-account-menu]').forEach(menu => {
    const toggle = menu.querySelector('[data-account-menu-toggle]');
    const panel = menu.querySelector('[data-account-menu-panel]');
    if (!toggle || !panel) return;

    const cerrar = () => {
      panel.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', event => {
      event.stopPropagation();
      const abrir = panel.hidden;
      document.querySelectorAll('[data-account-menu-panel]').forEach(otro => {
        otro.hidden = true;
      });
      panel.hidden = !abrir;
      toggle.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    });

    panel.addEventListener('click', event => event.stopPropagation());
    document.addEventListener('click', cerrar);
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') cerrar();
    });
  });
});
