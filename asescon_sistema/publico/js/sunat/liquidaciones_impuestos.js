(() => {
    const anio = document.getElementById('liqAnio');
    const mes = document.getElementById('liqMes');
    const busqueda = document.querySelector('.liquidacion-busqueda-campo input[name="q"]');
    const formularios = document.querySelectorAll('[data-liquidacion-filtros]');
    if (!anio || !mes || !busqueda) return;

    function sincronizarFiltros() {
        const periodo = anio.value + mes.value;
        formularios.forEach(formulario => {
            formulario.querySelectorAll('input[name="periodo"]').forEach(entrada => { entrada.value = periodo; });
            formulario.querySelectorAll('input[type="hidden"][name="q"]').forEach(entrada => { entrada.value = busqueda.value.trim(); });
        });
    }

    anio.addEventListener('change', sincronizarFiltros);
    mes.addEventListener('change', sincronizarFiltros);
    busqueda.addEventListener('input', sincronizarFiltros);
    formularios.forEach(formulario => formulario.addEventListener('submit', sincronizarFiltros));
    document.querySelectorAll('[data-liquidacion-modo]').forEach(boton => {
        boton.addEventListener('click', evento => {
            evento.preventDefault();
            sincronizarFiltros();
            const url = new URL(window.location.href);
            url.search = '';
            url.searchParams.set('periodo', anio.value + mes.value);
            url.searchParams.set('q', busqueda.value.trim());
            const modo = boton.dataset.liquidacionModo;
            const actual = document.querySelector('.liquidacion-busqueda-toolbar input[name="modo"]')?.value || '';
            if (modo !== actual) url.searchParams.set('modo', modo);
            window.location.assign(url.toString());
        });
    });
    sincronizarFiltros();
})();
