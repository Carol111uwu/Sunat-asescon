export function descargarArchivo(file) {
    const url = URL.createObjectURL(file.blob);
    const a = document.createElement('a');
    a.href = url; a.download = file.name; document.body.append(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 60000);
}

export class VisorPdfDeclaracion {
    constructor(iframe) { this.iframe = iframe; this.url = null; }
    limpiar() {
        this.iframe.hidden = true; this.iframe.removeAttribute('src');
        if (this.url) { URL.revokeObjectURL(this.url); this.url = null; }
    }
    mostrar(file) {
        this.limpiar(); this.url = URL.createObjectURL(file.blob);
        this.iframe.src = this.url; this.iframe.hidden = false;
    }
    abrirParaImprimir() { if (this.url) window.open(this.url, '_blank', 'noopener'); }
}
