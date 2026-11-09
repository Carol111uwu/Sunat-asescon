<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';
require_once __DIR__.'/../../servicios/sunat/ServicioColaBuzonSol.php';
require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
require_once __DIR__.'/../../servicios/casos_sunat/AccesoCasosSol.php';
asesconExigirLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        throw new DomainException('Método no permitido.');
    }
    asesconExigirPermiso($conexion, 'BUZON_SOL', 'ver');
    $id = (int)($_GET['id_documento'] ?? 0);
    $documento = (new RepositorioDocumentoBuzon($conexion))->obtener('SOL', $id, AccesoCasosSol::filtro($conexion,asesconFiltroClientesSql($conexion,'c')));
    if (!$documento || (int)$documento['id_cliente'] < 1) {
        http_response_code(404);
        throw new DomainException('La notificación no está disponible para tu cuenta.');
    }
    $archivo = (new ServicioArchivosPrivados())->resolver((string)($documento['archivo']??''),'SOL');
    $estado = (new ServicioColaBuzonSol())->obtenerEstadoCliente((int)$documento['id_cliente']);
    if ((int)($estado['id_documento']??0) !== $id) $estado = [];
    if ($archivo !== null) {
        $respuesta = [
            'ok'=>true, 'disponible'=>true, 'estado'=>'DISPONIBLE',
            'mensaje'=>'El PDF está listo para descargar.',
            'tamano_bytes'=>(int)filesize($archivo),
            'url'=>asesconUrl('api/buzones/archivo_documento.php').'?'.http_build_query(['buzon'=>'SOL','id_documento'=>$id,'descargar'=>1]),
        ];
    } else {
        $respuesta = [
            'ok'=>true, 'disponible'=>false,
            'estado'=>(string)($estado['estado']??'SIN_SOLICITUD'),
            'mensaje'=>(string)($estado['mensaje']??'El PDF todavía no está descargado.'),
            'bytes_descargados'=>(int)($estado['bytes_descargados']??0),
            'bytes_totales'=>(int)($estado['bytes_totales']??0),
            'etapa'=>(string)($estado['etapa']??''),
            'actualizado_en'=>(string)($estado['actualizado_en']??''),
        ];
    }
    echo json_encode($respuesta,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (DomainException $e) {
    if (http_response_code() < 400) http_response_code(400);
    echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('Estado PDF SOL: '.get_class($e));
    http_response_code(500);
    echo json_encode(['ok'=>false,'mensaje'=>'No se pudo consultar el estado de la descarga.'],JSON_UNESCAPED_UNICODE);
}
