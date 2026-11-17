(() => {
    'use strict';

    const raiz = document.documentElement;
    if (raiz.dataset.tema) return;

    const clave = 'asescon:tema';
    let tema = 'claro';
    try {
        if (localStorage.getItem(clave) === 'oscuro') tema = 'oscuro';
    } catch (_) {
        // El tema se puede cambiar aunque el navegador bloquee el almacenamiento.
    }
    raiz.dataset.tema = tema;

    const actualizarBotones = () => {
        document.querySelectorAll('[data-elegir-tema]').forEach(boton => {
            boton.setAttribute('aria-pressed', String(boton.dataset.elegirTema === raiz.dataset.tema));
        });
    };
    document.addEventListener('DOMContentLoaded', actualizarBotones);
    document.addEventListener('click', evento => {
        const boton = evento.target.closest('[data-elegir-tema]');
        if (!boton) return;
        raiz.dataset.tema = boton.dataset.elegirTema;
        try {
            localStorage.setItem(clave, raiz.dataset.tema);
        } catch (_) {
            // La selección sigue activa en esta página.
        }
        actualizarBotones();
    });
    window.addEventListener('storage', evento => {
        if (evento.key !== clave) return;
        raiz.dataset.tema = evento.newValue === 'oscuro' ? 'oscuro' : 'claro';
        actualizarBotones();
    });
})();
