'use strict';
document.addEventListener('DOMContentLoaded', () => {
    const aviso = document.querySelector('[data-sunafil-estado]');
    if (aviso && ['PENDIENTE', 'PROCESANDO'].includes(aviso.dataset.sunafilEstado)) {
        window.setTimeout(() => window.location.reload(), 10000);
    }
    document.querySelectorAll('form').forEach(form => {
        if (form.querySelector('input[name="accion"][value="sincronizar"]')) {
            form.addEventListener('submit', () => {
                const boton = form.querySelector('button[type="submit"]');
                if (boton) { boton.disabled = true; boton.textContent = 'Registrando…'; }
            });
        }
    });
});
