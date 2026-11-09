document.addEventListener("DOMContentLoaded", function () {
    const boton = document.getElementById("busquedaRapidaToggle");
    const capa = document.getElementById("quickSearch");
    const entrada = document.getElementById("quickSearchInput");
    const resultados = document.getElementById("quickSearchResults");
    const cerrar = document.getElementById("quickSearchClose");
    let temporizador = null;
    let controladorConsulta = null;
    let versionConsulta = 0;

    if (!boton || !capa || !entrada || !resultados) return;

    const escapar = valor => String(valor ?? "").replace(/[&<>"']/g, caracter => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[caracter]));

    function cerrarBusqueda() {
        clearTimeout(temporizador);
        versionConsulta++;
        controladorConsulta?.abort();
        capa.hidden = true;
        document.body.classList.remove("busqueda-abierta");
    }

    function rutaBase() {
        const ruta = window.location.pathname.replace(/\\/g, "/");
        const marca = "/vistas/";
        const posicion = ruta.indexOf(marca);
        return posicion >= 0 ? ruta.slice(0, posicion) : ruta.replace(/\/[^/]*$/, "");
    }

    function enlaceDeCliente(base, id) {
        const url = new URL(window.location.href);
        const ruta = url.pathname;
        const conservaContexto = /\/vistas\/(?:buzones\/(?:buzon_sol_sunat|buzon_sunafil)|sunat\/(?:declaraciones_pagos\/principal|rvie|rce|panel_sire|dashboard_sire))\.php$/i.test(ruta);
        if (conservaContexto) {
            const parametro=/\/sunat\/(?:rvie|rce|panel_sire|dashboard_sire)\.php$/i.test(ruta)?'cliente':'id_cliente';
            url.searchParams.set(parametro, String(id));
            url.searchParams.delete(parametro==='cliente'?'id_cliente':'cliente');
            url.searchParams.delete('pagina');
            url.searchParams.delete('ver_documento');
            url.searchParams.delete('solicitud');
            url.searchParams.delete('error');
            return url.pathname + url.search;
        }
        return base + '/vistas/clientes/ficha_cliente.php?id=' + Number(id);
    }

    async function buscar(mostrarIniciales = false) {
        const versionActual = ++versionConsulta;
        controladorConsulta?.abort();
        const consulta = entrada.value.trim();
        if (!mostrarIniciales && consulta.length < 2) {
            resultados.innerHTML = '<div class="quick-search-empty">Escribe al menos 2 caracteres.</div>';
            return;
        }
        resultados.innerHTML = '<div class="asescon-skeleton-list" role="status" aria-label="Buscando clientes" aria-busy="true">' +
            Array.from({length:3}, () => '<div class="asescon-skeleton-item"><span class="asescon-skeleton avatar"></span><div class="asescon-skeleton-lines"><span class="asescon-skeleton wide"></span><span class="asescon-skeleton medium"></span></div></div>').join('') + '</div>';
        controladorConsulta = new AbortController();
        try {
            const base = rutaBase();
            const respuesta = await fetch(base + "/api/clientes/buscar_clientes.php?" + (mostrarIniciales && !consulta ? "listar=1" : "q=" + encodeURIComponent(consulta)), {
                headers: { "Accept": "application/json" },
                signal: controladorConsulta.signal
            });
            const texto = await respuesta.text();
            if (versionActual !== versionConsulta) return;
            let json = null;
            try { json = JSON.parse(texto); } catch (_) { json = null; }
            if (!json || typeof json !== "object") {
                if (respuesta.status === 404) throw new Error("No se encontró la ruta interna de búsqueda.");
                if (respuesta.status === 403) throw new Error("No tienes permiso para buscar clientes.");
                throw new Error("El servidor devolvió una respuesta no válida en la búsqueda.");
            }
            if (!respuesta.ok || !json.ok) throw new Error(json.mensaje || "No se pudo buscar.");
            if (!Array.isArray(json.data) || json.data.length === 0) {
                resultados.innerHTML = '<div class="quick-search-empty">No se encontraron clientes.</div>';
                return;
            }
            resultados.innerHTML = json.data.map(cliente => {
                const estado = String(cliente.estado || "");
                return '<a class="quick-search-item" href="' + enlaceDeCliente(base, cliente.id_cliente) + '">' +
                    '<span class="quick-search-main"><strong>' + escapar(cliente.razon_social) + '</strong><small>RUC ' + escapar(cliente.ruc) + '</small></span>' +
                    '<span class="quick-search-status ' + (estado === "ACTIVO" ? "active" : "inactive") + '">' + escapar(estado) + '</span></a>';
            }).join("");
        } catch (error) {
            if (error.name === "AbortError" || versionActual !== versionConsulta) return;
            resultados.innerHTML = '<div class="quick-search-empty">' + escapar(error.message || error) + '</div>';
        }
    }

    boton.addEventListener("click", function () {
        capa.hidden = false;
        document.body.classList.add("busqueda-abierta");
        entrada.value = "";
        setTimeout(() => entrada.focus(), 0);
        buscar(true);
    });
    if (cerrar) cerrar.addEventListener("click", cerrarBusqueda);
    capa.addEventListener("click", evento => { if (evento.target === capa) cerrarBusqueda(); });
    entrada.addEventListener("input", function () {
        clearTimeout(temporizador);
        versionConsulta++;
        controladorConsulta?.abort();
        temporizador = setTimeout(buscar, 220);
    });
    entrada.addEventListener("keydown", evento => {
        if (evento.key === "Enter") {
            evento.preventDefault();
            clearTimeout(temporizador);
            buscar();
        }
    });
    document.addEventListener("keydown", evento => { if (evento.key === "Escape") cerrarBusqueda(); });
});
