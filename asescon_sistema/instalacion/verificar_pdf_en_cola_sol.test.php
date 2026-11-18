<?php
declare(strict_types=1);
require_once __DIR__.'/../servicios/sunat/ServicioColaBuzonSol.php';

function exigir(bool $condicion, string $descripcion): void {
    if (!$condicion) { throw new RuntimeException($descripcion); }
}
$base=sys_get_temp_dir().'/asescon_cola_pdf_prueba_'.bin2hex(random_bytes(8));
$cola=new ServicioColaBuzonSol($base);
try {
    $consulta=$cola->solicitar(5, 1);
    exigir($consulta['estado']==='PENDIENTE', 'Consulta inicial pendiente');
    $pdf=$cola->solicitar(5, 1, 27);
    exigir($pdf['estado']==='EN_COLA', 'PDF diferido durante consulta');
    $repeticion=$cola->solicitar(5, 1, 27);
    exigir($repeticion['id_trabajo']===$pdf['id_trabajo'], 'Sin duplicar PDF');
    exigir($cola->obtenerEstadoDocumento(5,27)['estado']==='EN_COLA', 'Estado por documento');
    $actual=$cola->tomarSiguienteSolicitud();
    exigir((int)$actual['id_documento']===0, 'Consulta activa tiene prioridad');
    $cola->completar(5,(string)$actual['id_trabajo'],25,3);
    $siguiente=$cola->tomarSiguienteSolicitud();
    exigir((int)$siguiente['id_documento']===27, 'PDF pasa a la cola cuando termina la consulta');
    exigir($cola->obtenerEstadoDocumento(5,27)['estado']==='PROCESANDO','PDF en ejecución');
    $cola->completarDocumento(5,(string)$siguiente['id_trabajo']);
    exigir($cola->obtenerEstadoDocumento(5,27)['estado']==='COMPLETADO','PDF finalizado');
    echo "OK: cola de PDF diferido y deduplicación\n";
} finally {
    $iterador=new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterador as $elemento) {
        if ($elemento->isDir()) { rmdir($elemento->getPathname()); }
        else { unlink($elemento->getPathname()); }
    }
    rmdir($base);
}
