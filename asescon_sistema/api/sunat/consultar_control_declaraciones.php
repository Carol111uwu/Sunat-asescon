<?php
declare(strict_types=1);

require_once __DIR__.'/../../configuracion/inicializacion.php';
require_once __DIR__.'/../../configuracion/respuesta_api.php';
asesconPrepararRespuestaApi();
require_once __DIR__.'/../../servicios/sunat/ServicioControlDeclaracionesSunat.php';

header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
try {
    asesconExigirPermiso($conexion, 'DECLARACIONES', 'ejecutar');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        throw new DomainException('Usa el botón Consultar SUNAT del módulo.');
    }
    if (!asesconValidarCsrf((string)($_POST['csrf_token'] ?? ''), 'csrf_declaraciones')) {
        http_response_code(403);
        throw new DomainException('Tu sesión venció. Recarga el módulo y vuelve a consultar.');
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (!$https && !in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost','127.0.0.1'], true)) {
        http_response_code(403);
        throw new DomainException('La consulta requiere una conexión HTTPS.');
    }
    $id = filter_var($_POST['id_cliente'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    $periodo = preg_replace('/\D+/', '', (string)($_POST['periodo'] ?? '')) ?? '';
    if ($id <= 0 || !asesconPuedeGestionarCliente($conexion, $id)) {
        http_response_code(403);
        throw new DomainException('Selecciona una empresa autorizada para consultar con Clave SOL.');
    }
    @set_time_limit(120);
    $resultado = (new ServicioControlDeclaracionesSunat($conexion))->sincronizar($id, $periodo);
    asesconRegistrarAuditoria($conexion, 'CONSULTAR_CONTROL_SOL', 'DECLARACIONES', $id,
        'declaraciones_sunat', $periodo, 'Coincidencias: '.$resultado['coincidencias'].'; sin vincular: '.$resultado['sin_vincular']);
    echo json_encode(['ok'=>true, 'resultado'=>$resultado], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if (http_response_code() < 400) http_response_code($e instanceof DomainException ? 422 : 500);
    if (!($e instanceof DomainException)) error_log('[ASESCON Control SOL API] '.get_class($e));
                                                                                       
    $mensaje = $e instanceof DomainException ? $e->getMessage() :
        'No se completó la consulta. Revisa las credenciales SOL, el acceso de SUNAT y los errores PHP de cPanel.';
    echo json_encode(['ok'=>false, 'mensaje'=>$mensaje], JSON_UNESCAPED_UNICODE);
}
