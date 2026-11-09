export function crearClienteConsulta(root) {
    return async function api(params, binary = false) {
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
            if (!/^(application\/pdf|application\/zip|application\/vnd\.ms-excel)(?:;|$)/i.test(type)) {
                throw new Error('El servidor no devolvió un PDF, Excel o ZIP válido.');
            }
            const blob = await response.blob();
            const disposition = response.headers.get('content-disposition') || '';
            const name = /filename="([A-Za-z0-9_.-]+)"/.exec(disposition)?.[1] || 'documento';
            return {blob, name};
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('La consulta tardó demasiado. El historial anterior se conserva. Revisa el estado antes de reintentar.');
            throw error;
        } finally { clearTimeout(timer); }
    };
}
