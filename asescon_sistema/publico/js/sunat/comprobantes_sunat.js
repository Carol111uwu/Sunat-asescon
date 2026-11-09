const tipoArchivo=document.getElementById('individualTipoArchivo');
const botonDescarga=document.getElementById('individualDownloadBtn');
tipoArchivo?.addEventListener('change',()=>{if(botonDescarga)botonDescarga.textContent='DESCARGAR '+tipoArchivo.value;});
