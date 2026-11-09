(() => {
  const menus = [...document.querySelectorAll('details.consultas-oficiales')];
  for (const menu of menus) {
    menu.addEventListener('toggle', () => {
      if (menu.open) for (const otro of menus) if (otro !== menu) otro.open = false;
    });
  }
  document.addEventListener('click', evento => {
    for (const menu of menus) if (!menu.contains(evento.target)) menu.open = false;
  });
  document.addEventListener('keydown', evento => {
    if (evento.key !== 'Escape') return;
    for (const menu of menus) {
      if (menu.open) { menu.open = false; menu.querySelector('summary')?.focus(); }
    }
  });
})();
