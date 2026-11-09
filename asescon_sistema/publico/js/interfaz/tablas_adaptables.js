(function () {
    "use strict";

    const clasesConResponsivePropio = [
        "buzon-sol-responsive-table",
        "clientes-responsive-table",
        "team-reference-table",
        "sunat-responsive-cards"
    ];

    function textoCabecera(celda) {
        return (celda?.textContent || "")
            .replace(/\s+/g, " ")
            .trim();
    }

    function tieneResponsivePropio(tabla) {
        return clasesConResponsivePropio.some(clase => tabla.classList.contains(clase));
    }

    function prepararFilas(tabla, cabeceras) {
        const cuerpo = tabla.tBodies && tabla.tBodies.length ? tabla.tBodies[0] : null;
        if (!cuerpo) return;

        Array.from(cuerpo.rows).forEach(fila => {
            let indice = 0;
            Array.from(fila.cells).forEach(celda => {
                const colspan = Math.max(1, Number.parseInt(celda.getAttribute("colspan") || "1", 10) || 1);

                if (colspan === 1 && !celda.hasAttribute("data-label")) {
                    celda.setAttribute("data-label", cabeceras[indice] || "");
                } else if (colspan > 1 && !celda.hasAttribute("data-label")) {
                    celda.setAttribute("data-label", "");
                }

                indice += colspan;
            });
        });
    }

    function prepararTabla(tabla) {
        if (!(tabla instanceof HTMLTableElement)) return;
        if (tabla.dataset.responsive === "no") return;
        if (tieneResponsivePropio(tabla)) return;
        if (!tabla.tHead || !tabla.tHead.rows.length) return;

        const filaCabecera = tabla.tHead.rows[tabla.tHead.rows.length - 1];
        const cabeceras = Array.from(filaCabecera.cells).map(textoCabecera);
        if (cabeceras.length < 2) return;

        tabla.classList.add("tabla-adaptable-global");
        tabla.style.setProperty("--columnas-tabla", String(cabeceras.length));

        const contenedor = tabla.closest(".table-container, .tabla-riesgo");
        if (contenedor) contenedor.classList.add("contenedor-tabla-adaptable");

        prepararFilas(tabla, cabeceras);
    }

    function prepararTodas() {
        document.querySelectorAll("table").forEach(prepararTabla);
    }

                                                                            
                                                                              
                                                                               
    function paginarListasPorCliente() {
        const vistas = {
            "vencimientos_sunat.php": { tabla: "#tablaVencimientos", columna: 0, rucColumna: 1, selector: 'select[name="cliente"]' },
            "control_declaraciones.php": { tabla: "#tablaDeclaraciones", columna: 1, rucColumna: 0, selector: 'select[name="cliente"]' },
                                                                                    
            "recibos_honorarios.php": { tabla: "#tablaRhe", columna: 0, selector: 'select[name="cliente"]' },
            "afp.php": { tabla: "#tablaAfp", columna: 1 },
            "pagos_tributarios.php": { tabla: ".module-panel .table-container > table", columna: 0 },
            "pdt_0621.php": { tabla: ".module-panel .table-container > table", columna: 0 },
            "plame_0601.php": { tabla: ".module-panel .table-container > table", columna: 0 },
            "liquidaciones_impuestos.php": { tabla: "#tablaLiquidaciones", columna: 0, rucColumna: 1 },
            "tareas.php": {
                tabla: "#tablaTareas", columna: 1, selector: 'select[name="cliente"]',
                adicionales: [{ tabla: "#tablaSeguimientosSol", columna: 0 }]
            }
        };
        const ruta = window.location.pathname.split("/").pop();
        const cfg = vistas[ruta];
        if (!cfg) return;
        if (cfg.selector && Number(document.querySelector(cfg.selector)?.value || 0) > 0) return;
        const tabla = document.querySelector(cfg.tabla);
        const cuerpo = tabla?.tBodies?.[0];
        if (!cuerpo) return;
        const conjuntos = [{ tabla, columna: cfg.columna, rucColumna: cfg.rucColumna }];
        for (const extra of cfg.adicionales || []) {
            const tablaExtra = document.querySelector(extra.tabla);
            if (tablaExtra?.tBodies?.[0]) conjuntos.push({ tabla: tablaExtra, columna: extra.columna, rucColumna: extra.rucColumna });
        }

        const empresas = new Map();
        for (const conjunto of conjuntos) {
            for (const fila of conjunto.tabla.tBodies[0].rows) {
                if (fila.cells.length <= conjunto.columna || fila.cells[0]?.colSpan > 1) continue;
                const celda = fila.cells[conjunto.columna];
                const nombreDirecto = [...(celda.childNodes || [])]
                    .find(nodo => nodo.nodeType === 3 && nodo.textContent.trim())?.textContent;
                const nombre = (celda.querySelector("strong")?.textContent || nombreDirecto || celda.textContent || "")
                    .trim().replace(/\s+/g, " ");
                const textoRuc = conjunto.rucColumna === undefined
                    ? celda.textContent || ""
                    : fila.cells[conjunto.rucColumna]?.textContent || "";
                const ruc = (textoRuc.match(/\b\d{11}\b/) || [""])[0];
                if (!nombre) continue;
                const clave = ruc || fila.dataset?.idCliente || nombre.toLocaleUpperCase("es");
                if (!empresas.has(clave)) empresas.set(clave, { nombre: nombre === "—" ? "Sin empresa asignada" : nombre, ruc, filas: [] });
                empresas.get(clave).filas.push(fila);
            }
        }
        if (!empresas.size) return;

        const grupos = [...empresas.values()].sort((a, b) =>
            a.nombre.localeCompare(b.nombre, "es", { sensitivity: "base" }) || a.ruc.localeCompare(b.ruc));
        const parametros = new URLSearchParams(window.location.search);
        const solicitada = Number.parseInt(parametros.get("pagina") || "1", 10);
        const pagina = Number.isFinite(solicitada) ? Math.max(1, Math.min(grupos.length, solicitada)) : 1;
        const actual = grupos[pagina - 1];
        for (const grupo of grupos) {
            for (const fila of grupo.filas) {
                const ocultar = grupo !== actual;
                fila.hidden = ocultar;
                fila.style.display = ocultar ? "none" : "";
            }
        }
        for (const conjunto of conjuntos) conjunto.tabla.dataset.paginacionCliente = "1";

        const contenedor = tabla.closest(".table-container");
        if (!contenedor) return;
        const etiqueta = document.createElement("p");
        etiqueta.className = "reference-subtle";
        etiqueta.style.margin = "10px 16px";
        etiqueta.textContent = `Empresa ${pagina} de ${grupos.length}: ${actual.nombre}${actual.ruc ? " · RUC " + actual.ruc : ""} · ${actual.filas.length} registros. Se muestran todos los de esta empresa.`;
        contenedor.before(etiqueta);

        const navegacion = document.createElement("nav");
        navegacion.className = "reference-actions";
        navegacion.setAttribute("aria-label", "Páginas por empresa");
        Object.assign(navegacion.style, { display: "flex", gap: "12px", justifyContent: "center", alignItems: "center", flexWrap: "wrap", padding: "16px" });
        const enlace = (destino, titulo) => {
            const params = new URLSearchParams(window.location.search);
            params.set("pagina", String(destino));
            const a = document.createElement("a");
            a.className = "reference-btn";
            a.href = "?" + params.toString() + window.location.hash;
            a.textContent = titulo;
            return a;
        };
        if (pagina > 1) navegacion.append(enlace(pagina - 1, "Anterior empresa"));
        const indicador = document.createElement("span");
        indicador.textContent = `Empresa ${pagina} de ${grupos.length}`;
        navegacion.append(indicador);
        if (pagina < grupos.length) navegacion.append(enlace(pagina + 1, "Siguiente empresa"));
        contenedor.after(navegacion);
    }

    document.addEventListener("DOMContentLoaded", function () {
        prepararTodas();
        paginarListasPorCliente();

        const observador = new MutationObserver(cambios => {
            let hayFilasNuevas = false;
            for (const cambio of cambios) {
                if (cambio.addedNodes && cambio.addedNodes.length) {
                    hayFilasNuevas = true;
                    break;
                }
            }
            if (hayFilasNuevas) prepararTodas();
        });

        observador.observe(document.body, { childList: true, subtree: true });
    });
})();
