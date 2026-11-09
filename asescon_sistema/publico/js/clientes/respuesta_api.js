async function asesconLeerRespuestaApi(respuesta, operacion) {
    const contenido = await respuesta.text();
    let datos;
    try {
        datos = JSON.parse(contenido);
    } catch (_) {
        datos = null;
    }

    if (!datos || typeof datos !== 'object' || typeof datos.ok !== 'boolean') {
        const codigo = respuesta.status;
        const mensaje = respuesta.redirected && respuesta.url.includes('/inicio_sesion.php')
            ? 'Tu sesión terminó. Vuelve a iniciar sesión e intenta nuevamente.'
            : codigo === 403
                ? 'El hosting bloqueó la solicitud (HTTP 403). Solicita al proveedor revisar ModSecurity para la ruta de esta consulta.'
                : codigo === 404
                    ? 'No se encontró la ruta de la API (HTTP 404). Revisa que la carpeta api esté subida al mismo nivel que views.'
                    : codigo >= 500
                        ? 'El servidor devolvió un error HTTP ' + codigo + '. Revisa cPanel → Errores o los registros PHP del hosting.'
                        : 'El servidor no devolvió una respuesta JSON válida (HTTP ' + codigo + '). Revisa la ruta de la API y los registros de errores PHP.';
        throw new Error(operacion + ': ' + mensaje);
    }

    if (!respuesta.ok || !datos.ok) {
        throw new Error(datos.mensaje || (operacion + ': error HTTP ' + respuesta.status + '.'));
    }
    return datos;
}
