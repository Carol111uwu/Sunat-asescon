(() => {
    const formulario = document.getElementById('enviarAccesoSunat');
    if (!formulario) return;
    const destino = new URL(formulario.action);
    if (destino.protocol !== 'https:' || destino.hostname !== 'api-seguridad.sunat.gob.pe') return;
    HTMLFormElement.prototype.submit.call(formulario);
                                                                                          
    formulario.querySelectorAll('input').forEach(campo => {
        campo.value = '';
        campo.removeAttribute('value');
    });
})();
