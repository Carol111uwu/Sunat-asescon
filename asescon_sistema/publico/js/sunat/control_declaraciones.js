const anioControl = document.getElementById('anioPeriodo');
const mesControl = document.getElementById('mesPeriodo');
const periodoControl = document.getElementById('periodoControl');

function sincronizarPeriodo() {
  if (anioControl && mesControl && periodoControl) {
    periodoControl.value = anioControl.value + mesControl.value;
  }
}
anioControl?.addEventListener('change', sincronizarPeriodo);
mesControl?.addEventListener('change', sincronizarPeriodo);

                                                                                          
const formularioSol = document.getElementById('consultaControlSol');
const resultadoSol = document.getElementById('resultadoControlSol');
if (formularioSol && resultadoSol) {
  const boton = formularioSol.querySelector('button[type="submit"]');
  let consultando = false;
  const aviso = (mensaje) => {
    const p = document.createElement('p');
    p.textContent = mensaje;
    resultadoSol.append(p);
  };

  formularioSol.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    if (consultando || !confirm('Se consultarán las declaraciones oficiales del cliente con Clave SOL. ¿Continuar?')) return;
    consultando = true;
    boton.disabled = true;
    resultadoSol.replaceChildren();
    aviso('Conectando con SUNAT. La consulta puede tardar hasta dos minutos…');
    try {
      const respuesta = await fetch(formularioSol.dataset.api, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        body: new FormData(formularioSol),
      });
      const datos = await respuesta.json();
      if (!respuesta.ok || !datos.ok) throw new Error(datos.mensaje || 'SUNAT no completó la consulta.');
      const r = datos.resultado;
      resultadoSol.replaceChildren();
      aviso(`Consulta SUNAT realizada: ${r.oficiales} formulario(s) del período; ${r.coincidencias} obligación(es) vinculada(s) en el control y ${r.sin_vincular} formulario(s) sin obligación local.`);
      aviso(`Fechas de presentación consultadas: ${r.inicio} a ${r.fin}.`);
      if (!r.filas?.length) {
        aviso('SUNAT no devolvió presentaciones de ese período. Esto no confirma un incumplimiento.');
        return;
      }
      const contenedor = document.createElement('div');
      contenedor.className = 'table-container';
      const tabla = document.createElement('table');
      const cabecera = document.createElement('thead');
      const trTitulo = document.createElement('tr');
      ['Formulario oficial', 'Número de orden', 'Presentado el'].forEach(titulo => {
        const th = document.createElement('th');
        th.textContent = titulo;
        trTitulo.append(th);
      });
      cabecera.append(trTitulo);
      const cuerpo = document.createElement('tbody');
      r.filas.forEach(fila => {
        const tr = document.createElement('tr');
        [fila.formulario, fila.orden, fila.fecha].forEach(valor => {
          const td = document.createElement('td');
          td.textContent = String(valor ?? '');
          tr.append(td);
        });
        cuerpo.append(tr);
      });
      tabla.append(cabecera, cuerpo);
      contenedor.append(tabla);
      resultadoSol.append(contenedor);
      aviso('Estos resultados no generan PDF ni historial descargado. Pulsa Buscar si deseas actualizar también la tabla del control.');
    } catch (error) {
      resultadoSol.replaceChildren();
      aviso(error instanceof Error ? error.message : 'No se completó la consulta SOL.');
    } finally {
      consultando = false;
      boton.disabled = false;
    }
  });
}
