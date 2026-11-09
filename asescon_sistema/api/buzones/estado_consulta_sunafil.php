<?php
declare(strict_types=1);
require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../servicios/sunafil/ServicioColaBuzonSunafil.php';
require_once __DIR__.'/../../servicios/buzones/EstadoCronBuzon.php';
asesconExigirLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
try {
    if (!asesconTienePermiso($conexion,'BUZON_SUNAFIL','ver')) {
        http_response_code(403);throw new DomainException('No tienes permiso para ver esta casilla.');
    }
    $idCliente=(int)($_GET['id_cliente']??0);
    if ($idCliente<1||!asesconPuedeVerCliente($conexion,$idCliente)) {
        http_response_code(403);throw new DomainException('La empresa no está autorizada.');
    }
    $cola=new ServicioColaBuzonSunafil();
    $estado=$cola->obtenerEstadoCliente($idCliente);
    $cron=EstadoCronBuzon::consultar($cola->directorioBase());
    $detalleCola=EstadoCronBuzon::detalleCola($cola->directorioBase(),$estado);
    echo json_encode([
        'ok'=>true,'estado'=>(string)($estado['estado']??'SIN_SOLICITUD'),
        'mensaje'=>(string)($estado['mensaje']??'No hay consultas anteriores.'),
        'total'=>(int)($estado['total']??0),'nuevos'=>(int)($estado['nuevos']??0),
        'cron_reciente'=>$cron['reciente'],'mensaje_cron'=>$cron['mensaje'],
        'cron_ultima'=>$cron['ultima'],'solicitado_en'=>$estado['solicitado_en']??null,
        'actualizado_en'=>$estado['actualizado_en']??null,'cola'=>$detalleCola,
    ],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (DomainException $e) {
    if (http_response_code()<400)http_response_code(400);
    echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Estado Buzón SUNAFIL: '.get_class($e));
    http_response_code(500);
    echo json_encode(['ok'=>false,'mensaje'=>'No se pudo comprobar el estado de SUNAFIL.'],JSON_UNESCAPED_UNICODE);
}
