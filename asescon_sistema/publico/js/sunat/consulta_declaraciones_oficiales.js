(() => {
    'use strict';
    const root = document.getElementById('consultaOficial');
    const form = document.getElementById('formConsultaOficial');
    if (!root || !form) return;
    const status = document.getElementById('estadoConsultaOficial');
    const table = document.getElementById('tablaOficial');
    const dialog = document.getElementById('detalleOficial');
    const detail = document.getElementById('contenidoDetalleOficial');
    const detailStatus = document.getElementById('estadoDetalleOficial');
    const actions = document.getElementById('accionesDetalleOficial');
    const pdf = document.getElementById('pdfOficial');
    const mail = document.getElementById('formCorreoConstancia');
    const canExecute = root.dataset.ejecutar === '1';
    let generation = 0, selected = null, current = null, objectUrl = null;
    const money = value => new Intl.NumberFormat('es-PE', {style: 'currency', currency: 'PEN'}).format(Number(value || 0));
    const text = value => value === null || value === undefined || value === '' ? '—' : String(value);
    function node(tag, value, parent) {
        const n = document.createElement(tag);
        if (value !== undefined) n.textContent = text(value);
        if (parent) parent.append(n);
        return n;
    }
    function clearPdf() {
        pdf.hidden = true; pdf.removeAttribute('src');
        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
    }
    function reset() {
        generation++; selected = null; current = null; table.hidden = true;
        table.tBodies[0].replaceChildren(); status.textContent = '';
        document.getElementById('exportacionesOficiales').hidden = true;
        if (dialog.open) dialog.close(); clearPdf();
    }
    form.elements.id_cliente.addEventListener('change', reset);
    form.addEventListener('input', event => {
        if (event.target.type === 'search' && !form.elements.id_cliente.value) reset();
    });
    dialog.querySelector('[data-cerrar-dialogo]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { current = null; clearPdf(); });
    async function api(params, binary = false) {
        const data = new URLSearchParams({csrf_token: root.dataset.csrf, ...params});
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 115000);
        try {
            const response = await fetch(root.dataset.api, {method: 'POST', body: data, credentials: 'same-origin', signal: controller.signal});
            const type = response.headers.get('content-type') || '';
            if (type.includes('application/json')) {
                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.mensaje || 'No se pudo completar la consulta.');
                if (binary) throw new Error('SUNAT no devolvió el archivo esperado.');
                return result;
            }
            if (!response.ok || !binary || response.redirected) throw new Error('La sesión o la respuesta del servidor no son válidas. Recarga la página.');
            const blob = await response.blob();
            const disposition = response.headers.get('content-disposition') || '';
            const name = /filename="([A-Za-z0-9_.-]+)"/.exec(disposition)?.[1] || 'documento';
            return {blob, name};
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('La consulta tardó demasiado. El historial anterior se conserva. Revisa el estado antes de reintentar.');
            throw error;
        } finally { clearTimeout(timer); }
    }
    async function busy(fn, target = status) {
        const controls = [...root.querySelectorAll('button,input,select')].filter(x => !x.hasAttribute('data-cerrar-dialogo'));
        const disabled = controls.map(x => x.disabled); controls.forEach(x => x.disabled = true);
        root.setAttribute('aria-busy', 'true');
        try { await fn(); } catch (error) { target.textContent = error.message; }
        finally { controls.forEach((x, i) => x.disabled = disabled[i]); root.removeAttribute('aria-busy'); }
    }
    function button(label, handler, parent) {
        const b = node('button', label, parent); b.type = 'button'; b.className = 'reference-btn';
        b.addEventListener('click', handler); return b;
    }
    function download(file) {
        const url = URL.createObjectURL(file.blob); const a = node('a');
        a.href = url; a.download = file.name; document.body.append(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 60000);
    }
    function render(consulta, client) {
        selected = {client, consulta}; table.tBodies[0].replaceChildren();
        const option = [...form.elements.id_cliente.options].find(o => o.value === client);
        status.textContent = `Consulta oficial completada: ${consulta.filas.length} registros. Empresa: ${option?.dataset.ruc || ''}. Fechas de presentación: ${consulta.inicio} a ${consulta.fin}. Consultado: ${new Date(consulta.consultado_en).toLocaleString('es-PE')}.`;
        document.getElementById('exportacionesOficiales').hidden = !canExecute || !consulta.filas.length;
        for (const row of consulta.filas) {
            const tr = node('tr', undefined, table.tBodies[0]);
            [row.strPerTri, row.codFor, row.desFor, row.numOrd, row.strFecPres, row.nomEntFin, money(row.mtoPag)].forEach(v => node('td', v, tr));
            const td = node('td', undefined, tr);
            button(canExecute ? 'Ver detalle y documentos' : 'Ver detalle guardado', () => openDetail(row, client), td);
        }
        table.hidden = false;
    }
    async function consult() {
        const client = form.elements.id_cliente.value;
        if (!client) { status.textContent = 'Selecciona una empresa.'; return; }
        const id = ++generation;
        await busy(async () => {
            status.textContent = 'Consultando la información oficial de SUNAT…';
            const r = await api({accion: 'consultar', id_cliente: client, inicio: form.elements.inicio.value, fin: form.elements.fin.value});
            if (id !== generation || form.elements.id_cliente.value !== client) return;
            if (r.consulta) render(r.consulta, client);
            else { selected = null; table.hidden = true; status.textContent = 'SUNAT no devolvió resultados para esta empresa.'; }
        });
    }
    form.addEventListener('submit', e => { e.preventDefault(); if (canExecute) consult(); });
    function renderDetail(data) {
        detail.replaceChildren();
        const c = data.constancia;
        node('h3', 'Constancia y datos del pago', detail);
        const dl = node('dl', undefined, detail);
        [['Tipo de declaración', c.tipoDeclaracion], ['Banco', c.banco], ['Medio de pago', c.formaPago], ['N.º operación bancaria', c.numeroOperacion], ['Fecha de pago', c.fechaPago],
            ...(c.form0601 ? [['Trabajadores', c.trabajadores], ['Pensionistas', c.pensionistas], ['Personal de cuarta categoría', c.personalCuarta], ['Modalidad formativa', c.modalidadForm], ['Terceros', c.terceros], ['Regímenes especiales', c.normasEspeciales]] : [])]
            .forEach(([k, v]) => { node('dt', k, dl); node('dd', v, dl); });
        const container = node('div', undefined, detail); container.className = 'table-container';
        const t = node('table', undefined, container); const h = node('tr', undefined, node('thead', undefined, t));
        ['Tributo', 'Deuda declarada', 'Pago aplicado en constancia', 'Saldo informado'].forEach(v => node('th', v, h));
        const body = node('tbody', undefined, t);
        for (const tax of c.tributos) {
            const r = node('tr', undefined, body);
                                                                                                  
            [tax.codTri + ' — ' + tax.descCodTri, money(tax.mtoTotDeu), money(Number(tax.mtoTotDeu) - Number(tax.mtoResPag)), money(tax.mtoResPag)].forEach(v => node('td', v, r));
        }
        const casillas = node('details', undefined, detail); node('summary', 'Ver todas las casillas informadas', casillas);
        const wrap = node('div', undefined, casillas); wrap.className = 'table-container'; const ct = node('table', undefined, wrap);
        for (const [key, value] of Object.entries(data.casillas).sort((a, b) => Number(a[0].slice(1)) - Number(b[0].slice(1)))) {
            const r = node('tr', undefined, ct); node('th', 'Casilla ' + key.slice(1), r); node('td', value, r);
        }
        detailStatus.textContent = 'Detalle consultado: ' + new Date(data.consultado_en).toLocaleString('es-PE');
    }
    function openDetail(row, client) {
        current = {row, client}; clearPdf(); detail.replaceChildren(); actions.replaceChildren();
        detailStatus.textContent = 'Cargando detalle…';
        document.getElementById('tituloDetalleOficial').textContent = `Formulario ${row.codFor} · Orden ${row.numOrd} · ${row.strPerTri}`;
        mail.hidden = !canExecute || !row.ecmFormulario;
        mail.elements.destinatario.value = [...form.elements.id_cliente.options].find(o => o.value === client)?.dataset.correo || '';
        if (canExecute) {
            for (const [type, label] of [['constancia', 'Ver constancia PDF'], ['casillas', 'Ver PDF de casillas'], ['anexos', 'Descargar anexos ZIP']]) {
                if ((type === 'constancia' && !row.ecmFormulario) || (type === 'casillas' && !row.ecmCasillas)) continue;
                button(label, () => fileAction(type), actions);
            }
            button('Actualizar detalle desde SUNAT', () => loadDetail(true), actions);
        }
        if (!dialog.open) dialog.showModal();
        loadDetail(canExecute);
    }
    async function loadDetail(live) {
        const active = current; if (!active) return;
        await busy(async () => {
            const r = await api({accion: live ? 'detalle' : 'detalle_guardado', id_cliente: active.client, formulario: active.row.codFor, numero_orden: active.row.numOrd});
            if (current !== active) return;
            if (r.detalle) renderDetail(r.detalle); else detailStatus.textContent = 'Aún no hay un detalle oficial guardado. Un operador con permiso de consulta debe actualizarlo.';
        }, detailStatus);
    }
    async function fileAction(type) {
        const active = current; if (!active) return;
        await busy(async () => {
            detailStatus.textContent = 'Obteniendo el archivo oficial de SUNAT…';
            const file = await api({accion: 'documento', id_cliente: active.client, formulario: active.row.codFor, numero_orden: active.row.numOrd, tipo: type}, true);
            if (current !== active) return;
            if (type === 'anexos') download(file);
            else {
                clearPdf(); objectUrl = URL.createObjectURL(file.blob); pdf.src = objectUrl; pdf.hidden = false;
                for (const b of actions.querySelectorAll('[data-archivo-pdf]')) b.remove();
                const save = button('Guardar PDF', () => download(file), actions); save.dataset.archivoPdf = '1';
                const print = button('Abrir PDF para imprimir', () => window.open(objectUrl, '_blank', 'noopener'), actions); print.dataset.archivoPdf = '1';
            }
            detailStatus.textContent = 'Archivo oficial recibido. Puedes guardarlo o usar las opciones de impresión del visor PDF.';
        }, detailStatus);
    }
    for (const b of root.querySelectorAll('[data-reporte]')) b.addEventListener('click', () => busy(async () => {
        if (!selected) return;
        status.textContent = 'Obteniendo el reporte oficial…';
        download(await api({accion: 'reporte', id_cliente: selected.client, tipo: b.dataset.reporte}, true));
        status.textContent = 'Reporte oficial recibido. Revisa las descargas de tu navegador.';
    }));
    mail.addEventListener('submit', e => {
        e.preventDefault(); const active = current; if (!active || !canExecute) return;
        const recipient = mail.elements.destinatario.value.trim();
        if (!window.confirm(`Se enviará la constancia ${active.row.codFor}, orden ${active.row.numOrd}, a ${recipient}. ¿Confirmas el envío?`)) return;
        busy(async () => {
            detailStatus.textContent = 'Solicitando el envío de la constancia…';
            const r = await api({accion: 'correo', id_cliente: active.client, formulario: active.row.codFor, numero_orden: active.row.numOrd, destinatario: recipient, confirmado: '1'});
            if (current === active) detailStatus.textContent = r.mensaje;
        }, detailStatus);
    });
})();
