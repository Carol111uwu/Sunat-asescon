import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {resolve, dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
import vm from 'node:vm';

const raiz = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const leer = ruta => readFileSync(resolve(raiz, ruta), 'utf8');
const respuesta = (status, data) => ({
  ok: status >= 200 && status < 300,
  status,
  text: async () => data === null ? '' : JSON.stringify(data)
});

function crearSincronizador(responder) {
  const acciones = [];
  const window = {};
  const contexto = {
    window, AbortController,
    document: {querySelector: () => ({content:'csrf-de-prueba'})},
    fetch: async (_url, opciones) => {
      const datos = JSON.parse(opciones.body);
      acciones.push(datos);
      return responder(datos);
    },
    setTimeout: (fn, ms) => {
                                                                               
      if (ms < 20000) queueMicrotask(fn);
      return 1;
    },
    clearTimeout: () => {},
  };
  vm.runInNewContext(leer('publico/js/sunat/sincronizacion_sire.js'), contexto);
  return {sincronizador:window.AsesconSireSync, acciones};
}

test('Ante un 504 al solicitar, recupera el ticket local sin emitir uno nuevo', async () => {
  const {sincronizador, acciones} = crearSincronizador(datos => {
    if (datos.accion === 'solicitar') return respuesta(504, null);
    if (datos.accion === 'ticket_reciente') return respuesta(200, {ok:true,data:{num_ticket:'ticket-uno',procesado:false}});
    if (datos.accion === 'estado') return respuesta(200, {ok:true,data:{terminado:true}});
    if (datos.accion === 'descargar') return respuesta(200, {ok:true,data:{descargado:true,procesado:true,cantidad_registros:0,sin_comprobantes:true}});
    if (datos.accion === 'inconsistencias') return respuesta(200, {ok:true,data:{}});
    throw Error('Acción inesperada: ' + datos.accion);
  });
  const resultado = await sincronizador.sincronizarLibro(1, 'RVIE', '202601');
  assert.equal(resultado.sin_comprobantes, true);
  assert.equal(acciones.filter(a => a.accion === 'solicitar').length, 1);
  assert.ok(acciones.some(a => a.accion === 'estado' && a.ticket === 'ticket-uno'));
});

test('Si un 504 no permite recuperar el ticket, marca ese mes como pendiente', async () => {
  const {sincronizador, acciones} = crearSincronizador(datos => {
    if (datos.accion === 'solicitar') return respuesta(504, null);
    if (datos.accion === 'ticket_reciente') return respuesta(200, {ok:true,data:{num_ticket:''}});
    throw Error('No debería pedir otro ticket ni descargar.');
  });
  await assert.rejects(sincronizador.sincronizarLibro(1, 'RCE', '202601'), error => {
    assert.equal(error.pendiente, true);
    assert.match(error.message, /no se volverá a solicitar ese mismo mes/);
    return true;
  });
  assert.equal(acciones.filter(a => a.accion === 'solicitar').length, 1);
  assert.equal(acciones.filter(a => a.accion === 'ticket_reciente').length, 11);
});

test('Un 504 no puede presentar un ticket anterior ya procesado como una nueva consulta', async () => {
  const {sincronizador, acciones} = crearSincronizador(datos => {
    if (datos.accion === 'solicitar') return respuesta(504, null);
    if (datos.accion === 'ticket_reciente') return respuesta(200, {
      ok:true, data:{num_ticket:'ticket-viejo',procesado:true,cantidad_registros:9}
    });
    throw Error('No se deben consultar ni descargar tickets viejos.');
  });
  await assert.rejects(sincronizador.sincronizarLibro(1, 'RVIE', '202601'), error => error.pendiente === true);
  assert.equal(acciones.filter(a => a.accion === 'solicitar').length, 1);
  assert.equal(acciones.filter(a => a.accion === 'estado').length, 0);
  assert.equal(acciones.filter(a => a.accion === 'descargar').length, 0);
});

class Elemento {
  constructor(id = '') {
    this.id = id;
    this.value = '';
    this.hidden = false;
    this.disabled = false;
    this.checked = false;
    this.dataset = {};
    this.children = [];
    this.listeners = {};
    this.textContent = '';
  }
  addEventListener(evento, callback) { (this.listeners[evento] ||= []).push(callback); }
  disparar(evento) {
    const acciones = this.listeners[evento] || [];
    const resultados = acciones.map(accion => accion({currentTarget:this, preventDefault(){}}));
    return resultados.at(-1);
  }
  append(...hijos) { this.children.push(...hijos); }
  replaceChildren(...hijos) { this.children = hijos; }
  querySelectorAll(selector) {
    const descendientes = this.children.flatMap(hijo =>
      hijo instanceof Elemento ? [hijo, ...hijo.querySelectorAll(selector)] : []);
    return selector === '.sire-reintentar-mes'
      ? descendientes.filter(hijo => hijo.className?.split(' ').includes('sire-reintentar-mes')) : [];
  }
  requestSubmit() { this.enCurso = this.disparar('submit'); return this.enCurso; }
  setAttribute(nombre, valor) { this[nombre] = valor; }
  removeAttribute(nombre) { delete this[nombre]; }
  scrollIntoView() {}
}

function crearVista(modulo, sincronizarLibro, {almacenamiento = new Map(), inventario = []} = {}) {
  const ids = [
    `${modulo}Cliente`, `${modulo}Mes`, `${modulo}Anio`, 'toggleExcluirAnteriores',
    `${modulo}VerConsultar`, `${modulo}ForzarSunat`, `${modulo}Inventario`,
    `${modulo}InventarioContenido`, `${modulo}Proceso`, `syncStatus${modulo.toUpperCase()}`,
    `${modulo}BarraProgreso`, `${modulo}Contadores`, `${modulo}ListaProcesos`,
    `${modulo}Pausar`, `${modulo}VerEstado`, `${modulo}AvisoSeleccion`,
    `${modulo}ConsultaFormulario`, `${modulo}VerGuardados`, `${modulo}Resultados`,
    `${modulo}PendientesAnuales`, `${modulo}PendientesTexto`, `${modulo}RecuperarPendientes`
  ];
  const elementos = new Map(ids.map(id => [id, new Elemento(id)]));
  const elegir = id => elementos.get(id);
  const opciones = [
    {value:'1', dataset:{nombre:'Empresa A'},textContent:'Empresa A'},
    {value:'2', dataset:{nombre:'Empresa B'},textContent:'Empresa B'}
  ];
  elegir(`${modulo}Cliente`).value = '1';
  elegir(`${modulo}Cliente`).options = opciones;
  elegir(`${modulo}Cliente`).selectedOptions = [opciones[0]];
  elegir(`${modulo}Mes`).value = '00';
  elegir(`${modulo}Anio`).value = '2021';
  elegir(`${modulo}Proceso`).hidden = true;
  elegir(`${modulo}PendientesAnuales`).hidden = true;
  elegir(`${modulo}ConsultaFormulario`).dataset.sireUsuario = '1';
  elegir(`${modulo}Resultados`).querySelectorAll = () => [];
  elegir(`${modulo}Resultados`).querySelector = () => null;
  const ventanas = [];
  const window = {
    location: {href:`https://sistema.asescon.net/vistas/sunat/${modulo}.php`, assign(url){this.assigned=url;}},
    confirm: () => true,
    localStorage: {
      getItem: key => almacenamiento.get(key) ?? null,
      setItem: (key, value) => almacenamiento.set(key, value),
    },
    open: url => { ventanas.push(url); return {}; },
    AsesconSireSync: {
      post: async accion => accion === 'inventario' ? {periodos:inventario} : {},
      sincronizarLibro,
    }
  };
  const document = {
    getElementById: elegir,
    querySelectorAll: () => [],
    createElement: tag => new Elemento(tag),
    createTextNode: text => ({textContent:text}),
  };
  const contexto = {
    window, document, URL, URLSearchParams, Intl,
    AsesconSireSync: window.AsesconSireSync,
    setTimeout: callback => { queueMicrotask(callback); return 1; },
  };
  vm.runInNewContext(leer('publico/js/sunat/pendientes_anuales_sire.js'), contexto);
  vm.runInNewContext(leer(`publico/js/sunat/${modulo}_centro.js`), contexto);
  return {elegir, ventanas, window, almacenamiento};
}

for (const modulo of ['rvie', 'rce']) {
  test(`${modulo.toUpperCase()}: el código no ofrece volver al modo de datos guardados`, async () => {
    const {elegir} = crearVista(modulo, async () => ({descargado:true,procesado:true,cantidad_registros:0,sin_comprobantes:true}));
    assert.equal(elegir(`${modulo}VerGuardados`).listeners.click, undefined);
    assert.equal(elegir(`${modulo}ForzarSunat`).listeners.click, undefined);
  });

  test(`${modulo.toUpperCase()}: si solo falla mayo la lista muestra el número 5`, async () => {
    const {elegir} = crearVista(modulo, async () => {
      const error = new Error('Sin confirmación del servidor');
      error.pendiente = true;
      throw error;
    });
    elegir(`${modulo}Mes`).value = '05';
    await elegir(`${modulo}ConsultaFormulario`).disparar('submit');
    assert.equal(elegir(`${modulo}ListaProcesos`).children.length, 1);
    assert.equal(elegir(`${modulo}ListaProcesos`).children[0].value, 5);
  });

  test(`${modulo.toUpperCase()}: un único botón no inicia dos lotes simultáneos`, async () => {
    const llamadas = [];
    let completar;
    const vista = crearVista(modulo, (id, libro, periodo) => {
      llamadas.push({id, libro, periodo});
      return new Promise(resolve => { completar = resolve; });
    });
    const {elegir, ventanas} = vista;
    elegir(`${modulo}Mes`).value = '01';
    const formulario = elegir(`${modulo}ConsultaFormulario`);
    const lote = formulario.disparar('submit');
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(llamadas.length, 1);
    assert.equal(elegir(`${modulo}VerConsultar`).textContent, 'Consultando…');
    assert.equal(elegir(`${modulo}VerConsultar`).disabled, true);
    formulario.disparar('submit');
    elegir(`${modulo}Cliente`).value = '2';
    elegir(`${modulo}Mes`).disparar('change');
    elegir('toggleExcluirAnteriores').checked = true;
    elegir('toggleExcluirAnteriores').disparar('change');
    assert.equal(ventanas.length, 0, 'Los filtros no abren pestañas extra.');
    assert.equal(llamadas.length, 1, 'Solo existe un lote en curso.');
    completar({descargado:true,procesado:true,cantidad_registros:2,sin_comprobantes:false});
    await lote;
    assert.equal(elegir(`${modulo}VerConsultar`).textContent, 'Consultar');
    assert.equal(elegir(`${modulo}VerConsultar`).disabled, false);
  });

  test(`${modulo.toUpperCase()}: mayo incierto continúa junio–diciembre y ofrece reintentar solo mes 5`, async () => {
    const llamadas = [];
    const vista = crearVista(modulo, async (_id, _libro, periodo) => {
      llamadas.push(periodo);
      if (periodo === '202105' && llamadas.filter(p => p === periodo).length === 1) {
        const error = new Error('Ticket de mayo sin confirmar');
        error.pendiente = true;
        throw error;
      }
      return {descargado:true,procesado:true,cantidad_registros:1,sin_comprobantes:false};
    });
    const {elegir} = vista;
    await elegir(`${modulo}ConsultaFormulario`).disparar('submit');
    assert.deepEqual(llamadas, Array.from({length:12}, (_,i) => '2021'+String(i+1).padStart(2,'0')));
    assert.match(elegir(`${modulo}Contadores`).textContent, /0 errores · 1 sin confirmar/);
    assert.match(elegir(`syncStatus${modulo.toUpperCase()}`).textContent, /sin detener el lote/);
    const filas = elegir(`${modulo}ListaProcesos`).children;
    const mayo = filas.find(f => f.value === 5);
    const junio = filas.find(f => f.value === 6);
    assert.ok(mayo && junio, 'Las listas conservan el número real del mes.');
    assert.equal(mayo.className, `${modulo}-pendiente`);
    const retry = mayo.querySelectorAll('.sire-reintentar-mes')[0];
    assert.ok(retry, 'Mayo ofrece reintento individual.');
    assert.equal(retry.textContent, 'Reintentar solo mes 5');
    assert.equal(retry.disabled, false);
    retry.disparar('click');
    await elegir(`${modulo}ConsultaFormulario`).enCurso;
    assert.equal(llamadas.length, 13);
    assert.equal(llamadas.at(-1), '202105', 'El reintento no repite enero, febrero, marzo ni abril.');
    assert.equal(elegir(`${modulo}Mes`).value, '05');
    assert.equal(elegir(`${modulo}VerConsultar`).disabled, false);
  });

  test(`${modulo.toUpperCase()}: no inventa pendientes de otros meses y recuerda solo el mes solicitado con fallo`, async () => {
    const almacenamiento = new Map();
    const inventario = Array.from({length:12}, (_,i) => i + 1)
      .filter(mes => ![3, 7].includes(mes))
      .map(mes => ({id_cliente:1, periodo:'2021'+String(mes).padStart(2,'0'), cantidad_registros:4}));
    const llamadas = [];
    const primera = crearVista(modulo, async (_id, _libro, periodo) => {
      llamadas.push(periodo);
      if (periodo === '202103') {
        const fallo = new Error('SUNAT no confirmó marzo');
        fallo.pendiente = true;
        throw fallo;
      }
      return {descargado:true,procesado:true,cantidad_registros:2,sin_comprobantes:false};
    }, {almacenamiento, inventario});
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(primera.elegir(`${modulo}PendientesAnuales`).hidden, true, 'No mostrar otros meses por inventario.');
    primera.elegir(`${modulo}Mes`).value = '03';
    await primera.elegir(`${modulo}ConsultaFormulario`).disparar('submit');
    assert.deepEqual(llamadas, ['202103'], 'Solo se consultó el período elegido.');
    assert.match(primera.elegir(`${modulo}PendientesTexto`).textContent, /1 mes pendiente/);
    assert.equal(primera.elegir(`${modulo}RecuperarPendientes`).textContent, 'Recuperar 1 mes pendiente');

    const recargada = crearVista(modulo, async (_id, _libro, periodo) => {
      llamadas.push(periodo);
      return {descargado:true,procesado:true,cantidad_registros:3,sin_comprobantes:false};
    }, {almacenamiento, inventario:[...inventario, {id_cliente:1,periodo:'202107',cantidad_registros:2}]});
    await new Promise(resolve => setImmediate(resolve));
    assert.match(recargada.elegir(`${modulo}PendientesTexto`).textContent, /1 mes pendiente/);
    recargada.elegir(`${modulo}RecuperarPendientes`).disparar('click');
    await recargada.elegir(`${modulo}ConsultaFormulario`).enCurso;
    assert.deepEqual(llamadas, ['202103','202103']);
    assert.equal(recargada.elegir(`${modulo}PendientesAnuales`).hidden, true);
  });

  test(`${modulo.toUpperCase()}: pausar después de un mes y reanudar conserva el lote`, async () => {
    const llamadas = [];
    let terminarPrimero;
    const {elegir} = crearVista(modulo, async (_id, _libro, periodo) => {
      llamadas.push(periodo);
      if (llamadas.length === 1) {
        return new Promise(resolve => { terminarPrimero = resolve; });
      }
      return {descargado:true,procesado:true,cantidad_registros:1,sin_comprobantes:false};
    });
    const lote = elegir(`${modulo}ConsultaFormulario`).disparar('submit');
    await new Promise(resolve => setImmediate(resolve));
    const pausa = elegir(`${modulo}Pausar`);
    assert.equal(pausa.hidden, false);
    pausa.disparar('click');
    terminarPrimero({descargado:true,procesado:true,cantidad_registros:1,sin_comprobantes:false});
    await new Promise(resolve => setImmediate(resolve));
    assert.deepEqual(llamadas, ['202101'], 'No debe iniciar febrero si el usuario pidió pausa.');
    assert.equal(pausa.textContent, 'Reanudar consultas');
    pausa.disparar('click');
    await lote;
    assert.equal(llamadas.length, 12);
    assert.equal(pausa.hidden, true);
  });

  test(`${modulo.toUpperCase()}: Todos los clientes recupera solo el fallo de una consulta explícita`, async () => {
    const llamadas = [];
    let primerFallo = true;
    const {elegir} = crearVista(modulo, async (id, _libro, periodo) => {
      llamadas.push(`${id}|${periodo}`);
      if (id === 2 && primerFallo) {
        primerFallo = false;
        const error = new Error('Pendiente SUNAT cliente 2');
        error.pendiente = true;
        throw error;
      }
      return {descargado:true,procesado:true,cantidad_registros:1,sin_comprobantes:false};
    });
    elegir(`${modulo}Cliente`).value = '0';
    elegir(`${modulo}Mes`).value = '03';
    elegir(`${modulo}Cliente`).disparar('change');
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(elegir(`${modulo}PendientesAnuales`).hidden,true);
    await elegir(`${modulo}ConsultaFormulario`).disparar('submit');
    assert.match(elegir(`${modulo}PendientesTexto`).textContent, /1 mes pendiente/);
    elegir(`${modulo}RecuperarPendientes`).disparar('click');
    await elegir(`${modulo}ConsultaFormulario`).enCurso;
    assert.deepEqual(llamadas, ['1|202103', '2|202103', '2|202103']);
    assert.equal(elegir(`${modulo}PendientesAnuales`).hidden,true);
  });
}
