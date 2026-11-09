document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-tabs]').forEach(root => {
    const buttons = root.querySelectorAll('[data-tab-target]');
    buttons.forEach(btn => btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-tab-target');
      buttons.forEach(b => b.classList.toggle('active', b === btn));
      root.querySelectorAll('[data-tab-panel]').forEach(panel => {
        panel.hidden = panel.getAttribute('data-tab-panel') !== id;
      });
    }));
  });

  function resolveExportTable(rawSelector) {
    const value = (rawSelector || '').trim();
    if (!value) return null;

    const byId = document.getElementById(value.replace(/^#/, ''));
    if (byId) return byId;

    try {
      return document.querySelector(value);
    } catch (_) {
      return null;
    }
  }

  function csvValue(value) {
    const text = String(value ?? '')
      .replace(/\r?\n|\r/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .replace(/"/g, '""');
    return '"' + text + '"';
  }

  document.querySelectorAll('[data-export-table]').forEach(btn => {
    btn.addEventListener('click', event => {
      event.preventDefault();

      const table = resolveExportTable(btn.getAttribute('data-export-table'));
      if (!table) {
        console.error('ASESCON: no se encontró la tabla indicada para exportar.', btn.getAttribute('data-export-table'));
        return;
      }

      const delimiter = ';';
      const filasExportables = [...table.querySelectorAll('tr')].filter(tr =>
        table.dataset.paginacionCliente !== '1' || !tr.hidden
      );
      const rows = filasExportables.map(tr =>
        [...tr.children].map(cell => csvValue(cell.innerText)).join(delimiter)
      );
      const csv = '\ufeffsep=;\r\n' + rows.join('\r\n');
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = (btn.getAttribute('data-filename') || 'asescon_exportacion') + '.csv';
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
  });

  document.querySelectorAll('[data-refresh]').forEach(btn => {
    btn.addEventListener('click', () => window.location.reload());
  });
});
