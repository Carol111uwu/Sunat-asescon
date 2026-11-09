import { crearClienteConsulta } from './cliente_api.js';
import { VisorPdfDeclaracion, descargarArchivo } from './visor_pdf.js';
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
    let generation = 0, selected = null, current = null;
    const visor = new VisorPdfDeclaracion(pdf);
    const api = crearClienteConsulta(root);
    const download = descargarArchivo;
    const money = value => new Intl.NumberFormat('es-PE', {style: 'currency', currency: 'PEN'}).format(Number(value || 0));
    const text = value => value === null || value === undefined || value === '' ? '—' : String(value);
    function node(tag, value, parent) {
        const n = document.createElement(tag);
        if (value !== undefined) n.textContent = text(value);
        if (parent) parent.append(n);
        return n;
    }
    function clearPdf() { visor.limpiar(); }
    function reset() {
        generation++; selected = null; current = null; table.hidden = true;
        for (const t of root.querySelectorAll('table[id]')) { t.hidden = true; t.tBodies[0].replaceChildren(); }
        root.querySelectorAll('[data-sin-consulta]').forEach(n => n.hidden = false); status.textContent = '';
        document.getElementById('exportacionesOficiales').hidden = true;
        if (dialog.open) dialog.close(); clearPdf();
    }
    form.elements.id_cliente.addEventListener('change', reset);
    form.addEventListener('input', event => {
        if (event.target.type === 'search' && !form.elements.id_cliente.value) reset();
    });
    dialog.querySelector('[data-cerrar-dialogo]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { current = null; clearPdf(); });
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
    function render(consulta, client) {
        selected = {client, consulta}; table.tBodies[0].replaceChildren();
        const option = [...form.elements.id_cliente.options].find(o => o.value === client);
        status.textContent = `Consulta oficial completada: ${consulta.filas.length} registros. Empresa: ${option?.dataset.ruc || ''}. Fechas de presentación: ${consulta.inicio} a ${consulta.fin}. Consultado: ${new Date(consulta.consultado_en).toLocaleString('es-PE')}.`;
        document.getElementById('exportacionesOficiales').hidden = !canExecute || !consulta.filas.length;
        for (const row of consulta.filas) {
            const tr = node('tr', undefined, table.tBodies[0]);
            [row.strPerTri, row.codFor, row.desFor, row.numOrd, row.strFecPres, row.nomEntFin, money(row.mtoPag)].forEach(v => node('td', v, tr));
            const td = node('td', undefined, tr);
            button(canExecute ? 'Ver tributos y pago' : 'Ver detalle guardado', () => openDetail(row, client), td);
            if (canExecute && row.ecmFormulario) button('Constancia PDF', () => openDocument(row, client, 'constancia'), td);
            if (canExecute && row.ecmCasillas) button('PDF de casillas', () => openDocument(row, client, 'casillas'), td);
        }
        table.hidden = false;
        renderDocumentAccess(consulta.filas, client);
    }
    function renderDocumentAccess(rows, client) {
        root.querySelectorAll('[data-sin-consulta]').forEach(n => n.hidden = true);
        const constancias = document.getElementById('tablaConstancias');
        const casillas = document.getElementById('tablaCasillasAnexos');
        constancias.tBodies[0].replaceChildren(); casillas.tBodies[0].replaceChildren();
        for (const row of rows) {
            for (const [target, kind] of [[constancias, 'constancias'], [casillas, 'casillas']]) {
                const tr = node('tr', undefined, target.tBodies[0]);
                [row.strPerTri, row.codFor, row.numOrd].forEach(v => node('td', v, tr));
                if (kind === 'constancias') node('td', row.strFecPres, tr);
                const td = node('td', undefined, tr);
                if (!canExecute) { button('Ver detalle guardado', () => openDetail(row, client), td); continue; }
                if (kind === 'constancias') {
                    if (row.ecmFormulario) {
                        button('Ver constancia PDF', () => openDocument(row, client, 'constancia'), td);
                        button('Descargar constancia PDF', () => openDocument(row, client, 'constancia', true), td);
                        button('Enviar constancia por correo', () => {
                            prepareDialog(row, client); detailStatus.textContent = 'Revisa el destinatario y confirma el envío de esta constancia.';
                            mail.elements.destinatario.focus();
                        }, td);
                    } else node('span', 'SUNAT no informó una constancia PDF.', td);
                } else {
                    button('Ver casillas declaradas', () => openDetail(row, client), td);
                    if (row.ecmCasillas) {
                        button('Ver PDF de casillas', () => openDocument(row, client, 'casillas'), td);
                        button('Descargar PDF de casillas', () => openDocument(row, client, 'casillas', true), td);
                    }
                    button('Solicitar anexos ZIP', () => openDocument(row, client, 'anexos'), td);
                }
            }
        }
        constancias.hidden = false; casillas.hidden = false;
    }
    function activateView(name) {
        if (!['consulta', 'constancias', 'casillas', 'reportes'].includes(name)) name = 'consulta';
        root.querySelectorAll('[data-panel-oficial]').forEach(p => p.hidden = p.dataset.panelOficial !== name);
        root.querySelectorAll('[data-vista-oficial]').forEach(b => {
            const active = b.dataset.vistaOficial === name; b.classList.toggle('active', active); b.setAttribute('aria-pressed', String(active));
        });
        const url = new URL(location.href); url.searchParams.set('seccion', name); history.replaceState(null, '', url);
    }
    for (const button of root.querySelectorAll('[data-vista-oficial]')) button.addEventListener('click', () => activateView(button.dataset.vistaOficial));
    activateView(root.dataset.seccion);
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
    function prepareDialog(row, client) {
        current = {row, client, tipoCorreo: 'constancia'}; clearPdf(); detail.replaceChildren(); actions.replaceChildren();
        detailStatus.textContent = 'Cargando detalle…';
        document.getElementById('tituloDetalleOficial').textContent = `Formulario ${row.codFor} · Orden ${row.numOrd} · ${row.strPerTri}`;
        mail.hidden = !canExecute || !row.ecmFormulario;
        mail.querySelector('[data-correo-documento]').textContent = 'Enviar constancia oficial a';
        mail.elements.destinatario.value = [...form.elements.id_cliente.options].find(o => o.value === client)?.dataset.correo || '';
        if (canExecute) {
            for (const [type, label] of [['constancia', 'Ver constancia PDF'], ['casillas', 'Ver PDF de casillas'], ['anexos', 'Descargar anexos ZIP']]) {
                if ((type === 'constancia' && !row.ecmFormulario) || (type === 'casillas' && !row.ecmCasillas)) continue;
                button(label, () => fileAction(type), actions);
            }
            button('Actualizar detalle desde SUNAT', () => loadDetail(true), actions);
        }
        if (!dialog.open) dialog.showModal();
    }
    function openDetail(row, client) { prepareDialog(row, client); loadDetail(canExecute); }
    function openDocument(row, client, type, save = false) {
        prepareDialog(row, client);
        document.getElementById('tituloDetalleOficial').textContent = `${type === 'casillas' ? 'Detalle de casillas' : type === 'anexos' ? 'Anexos oficiales' : 'Constancia oficial'} · ${row.codFor} · Orden ${row.numOrd}`;
        current.tipoCorreo = type;
        mail.hidden = !canExecute || type === 'anexos';
        mail.querySelector('[data-correo-documento]').textContent = type === 'casillas' ? 'Enviar PDF de casillas a' : 'Enviar constancia oficial a';
        fileAction(type, save);
    }
    async function loadDetail(live) {
        const active = current; if (!active) return;
        await busy(async () => {
            const r = await api({accion: live ? 'detalle' : 'detalle_guardado', id_cliente: active.client, formulario: active.row.codFor, numero_orden: active.row.numOrd});
            if (current !== active) return;
            if (r.detalle) renderDetail(r.detalle); else detailStatus.textContent = 'Aún no hay un detalle oficial guardado. Un operador con permiso de consulta debe actualizarlo.';
        }, detailStatus);
    }
    async function fileAction(type, save = false) {
        const active = current; if (!active) return;
        await busy(async () => {
            detailStatus.textContent = 'Obteniendo el archivo oficial de SUNAT…';
            const file = await api({accion: 'documento', id_cliente: active.client, formulario: active.row.codFor, numero_orden: active.row.numOrd, tipo: type}, true);
            if (current !== active) return;
            if (type === 'anexos') download(file);
            else {
                visor.mostrar(file);
                if (save) download(file);
                for (const b of actions.querySelectorAll('[data-archivo-pdf]')) b.remove();
                const saveButton = button('Guardar PDF', () => download(file), actions); saveButton.dataset.archivoPdf = '1';
                const print = button('Abrir PDF para imprimir', () => visor.abrirParaImprimir(), actions); print.dataset.archivoPdf = '1';
            }
            detailStatus.textContent = 'Archivo oficial recibido. Puedes guardarlo o usar las opciones de impresión del visor PDF.';
        }, detailStatus);
    }
    function showReport(file, client) {
        current = {row: null, client}; clearPdf(); detail.replaceChildren(); actions.replaceChildren(); mail.hidden = true;
        document.getElementById('tituloDetalleOficial').textContent = 'Reporte oficial de declaraciones y pagos';
        visor.mostrar(file);
        button('Guardar reporte PDF', () => download(file), actions);
        button('Abrir PDF para imprimir', () => visor.abrirParaImprimir(), actions);
        detailStatus.textContent = 'Reporte PDF emitido por SUNAT para la empresa y fechas consultadas.';
        if (!dialog.open) dialog.showModal();
    }
    for (const b of root.querySelectorAll('[data-reporte]')) b.addEventListener('click', () => busy(async () => {
        if (!selected) return;
        const client = selected.client;
        status.textContent = 'Obteniendo el reporte oficial…';
        const file = await api({accion: 'reporte', id_cliente: client, tipo: b.dataset.reporte}, true);
        if (b.dataset.operacion === 'ver') showReport(file, client); else download(file);
        status.textContent = b.dataset.operacion === 'ver' ? 'Reporte oficial abierto.' : 'Reporte oficial recibido. Revisa las descargas de tu navegador.';
    }));
    mail.addEventListener('submit', e => {
        e.preventDefault(); const active = current; if (!active || !active.row || !canExecute) return;
        const recipient = mail.elements.destinatario.value.trim();
        if (!window.confirm(`Se enviará ${active.tipoCorreo === 'casillas' ? 'el PDF de casillas' : 'la constancia'} ${active.row.codFor}, orden ${active.row.numOrd}, a ${recipient}. ¿Confirmas el envío?`)) return;
        busy(async () => {
            detailStatus.textContent = 'Solicitando el envío de la constancia…';
            const r = await api({accion: 'correo', id_cliente: active.client, formulario: active.row.codFor, numero_orden: active.row.numOrd, destinatario: recipient, tipo_documento: active.tipoCorreo, confirmado: '1'});
            if (current === active) detailStatus.textContent = r.mensaje;
        }, detailStatus);
    });
})();
